create extension if not exists pgcrypto;

create or replace function public.is_admin_user()
returns boolean
language sql
stable
as $$
    select
        coalesce(auth.jwt() ->> 'email', '') = 'a@asx.mx'
        or coalesce(auth.jwt() -> 'app_metadata' ->> 'role', '') = 'admin'
        or (
            jsonb_typeof(auth.jwt() -> 'app_metadata' -> 'roles') = 'array'
            and (auth.jwt() -> 'app_metadata' -> 'roles') ? 'admin'
        );
$$;

create table if not exists public.projects (
    id uuid primary key default gen_random_uuid(),
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    name text not null,
    logo_url text not null default '',
    logo_storage_path text not null default '',
    description text not null default '',
    company_type text not null default '',
    company_goal text not null default '',
    status text not null default 'active' check (status in ('active', 'archived')),
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    deleted_at timestamptz
);

create table if not exists public.project_members (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    user_id uuid references auth.users (id) on delete cascade,
    invited_email text not null default '',
    role text not null default 'member' check (role in ('owner', 'admin', 'member')),
    status text not null default 'active' check (status in ('active', 'invited', 'disabled')),
    invited_by uuid references auth.users (id) on delete set null,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

alter table public.projects
    drop column if exists business_id cascade,
    add column if not exists logo_storage_path text not null default '',
    add column if not exists company_type text not null default '',
    add column if not exists company_goal text not null default '',
    add column if not exists metadata jsonb not null default '{}'::jsonb,
    add column if not exists deleted_at timestamptz;

drop table if exists public.business_members cascade;
drop table if exists public.businesses cascade;

drop index if exists public.businesses_owner_idx;
drop index if exists public.projects_business_status_idx;
create index if not exists project_members_project_idx on public.project_members (project_id);
create index if not exists project_members_user_idx on public.project_members (user_id, status);
create index if not exists project_members_email_idx on public.project_members (lower(invited_email), status);
create index if not exists projects_status_updated_idx on public.projects (status, updated_at desc);
create index if not exists projects_owner_idx on public.projects (owner_user_id, status);

create unique index if not exists project_members_project_user_unique
on public.project_members (project_id, user_id)
where user_id is not null;

create unique index if not exists project_members_project_email_unique
on public.project_members (project_id, lower(invited_email))
where invited_email <> '';

create or replace function public.set_project_records_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_projects_updated_at on public.projects;
create trigger trg_projects_updated_at
before update on public.projects
for each row
execute function public.set_project_records_updated_at();

drop trigger if exists trg_project_members_updated_at on public.project_members;
create trigger trg_project_members_updated_at
before update on public.project_members
for each row
execute function public.set_project_records_updated_at();

create or replace function public.ensure_project_owner_member()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
    insert into public.project_members (project_id, user_id, invited_email, role, status, invited_by)
    values (new.id, new.owner_user_id, '', 'owner', 'active', new.owner_user_id)
    on conflict (project_id, user_id) where user_id is not null do update
    set role = 'owner',
        status = 'active',
        updated_at = timezone('utc', now());

    return new;
end;
$$;

drop trigger if exists trg_project_owner_member on public.projects;
create trigger trg_project_owner_member
after insert on public.projects
for each row
execute function public.ensure_project_owner_member();

create or replace function public.can_access_project(p_project_id uuid)
returns boolean
language sql
stable
security definer
set search_path = public
as $$
    select
        public.is_admin_user()
        or exists (
            select 1
            from public.projects p
            where p.id = p_project_id
              and p.owner_user_id = auth.uid()
              and p.deleted_at is null
        )
        or exists (
            select 1
            from public.project_members pm
            where pm.project_id = p_project_id
              and pm.status = 'active'
              and (
                pm.user_id = auth.uid()
                or lower(pm.invited_email) = lower(coalesce(auth.jwt() ->> 'email', ''))
              )
        );
$$;

create or replace function public.can_manage_project(p_project_id uuid)
returns boolean
language sql
stable
security definer
set search_path = public
as $$
    select
        public.is_admin_user()
        or exists (
            select 1
            from public.projects p
            where p.id = p_project_id
              and p.owner_user_id = auth.uid()
              and p.deleted_at is null
        )
        or exists (
            select 1
            from public.project_members pm
            where pm.project_id = p_project_id
              and pm.status = 'active'
              and pm.role in ('owner', 'admin')
              and (
                pm.user_id = auth.uid()
                or lower(pm.invited_email) = lower(coalesce(auth.jwt() ->> 'email', ''))
              )
        );
$$;

alter table public.projects enable row level security;
alter table public.project_members enable row level security;

drop policy if exists "Users can view accessible projects" on public.projects;
create policy "Users can view accessible projects"
on public.projects
for select
to authenticated
using (deleted_at is null and public.can_access_project(id));

drop policy if exists "Users can create owned projects" on public.projects;
create policy "Users can create owned projects"
on public.projects
for insert
to authenticated
with check (owner_user_id = auth.uid() or public.is_admin_user());

drop policy if exists "Project managers can update projects" on public.projects;
create policy "Project managers can update projects"
on public.projects
for update
to authenticated
using (deleted_at is null and public.can_manage_project(id))
with check (public.can_manage_project(id));

drop policy if exists "Project members can view members" on public.project_members;
create policy "Project members can view members"
on public.project_members
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Project managers can add members" on public.project_members;
create policy "Project managers can add members"
on public.project_members
for insert
to authenticated
with check (public.can_manage_project(project_id));

drop policy if exists "Project managers can update members" on public.project_members;
create policy "Project managers can update members"
on public.project_members
for update
to authenticated
using (public.can_manage_project(project_id))
with check (public.can_manage_project(project_id));

drop policy if exists "Project managers can delete members" on public.project_members;
create policy "Project managers can delete members"
on public.project_members
for delete
to authenticated
using (public.can_manage_project(project_id));

grant select, insert, update on public.projects to authenticated;
grant select, insert, update, delete on public.project_members to authenticated;
