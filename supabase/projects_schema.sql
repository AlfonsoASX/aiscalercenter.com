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
    cover_image_url text not null default '',
    cover_image_storage_path text not null default '',
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

create table if not exists public.user_profiles (
    user_id uuid primary key references auth.users (id) on delete cascade,
    email text not null default '',
    full_name text not null default '',
    avatar_url text not null default '',
    status text not null default 'active' check (status in ('active', 'disabled')),
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

alter table public.projects
    drop column if exists business_id cascade,
    add column if not exists logo_storage_path text not null default '',
    add column if not exists cover_image_url text not null default '',
    add column if not exists cover_image_storage_path text not null default '',
    add column if not exists company_type text not null default '',
    add column if not exists company_goal text not null default '',
    add column if not exists metadata jsonb not null default '{}'::jsonb,
    add column if not exists deleted_at timestamptz;

alter table public.user_profiles
    add column if not exists email text not null default '',
    add column if not exists full_name text not null default '',
    add column if not exists avatar_url text not null default '',
    add column if not exists status text not null default 'active',
    add column if not exists created_at timestamptz not null default timezone('utc', now()),
    add column if not exists updated_at timestamptz not null default timezone('utc', now());

drop table if exists public.business_members cascade;
drop table if exists public.businesses cascade;

drop index if exists public.businesses_owner_idx;
drop index if exists public.projects_business_status_idx;
create index if not exists project_members_project_idx on public.project_members (project_id);
create index if not exists project_members_user_idx on public.project_members (user_id, status);
create index if not exists project_members_email_idx on public.project_members (lower(invited_email), status);
create index if not exists projects_status_updated_idx on public.projects (status, updated_at desc);
create index if not exists projects_owner_idx on public.projects (owner_user_id, status);
drop index if exists public.user_profiles_email_unique;
create unique index if not exists user_profiles_email_unique on public.user_profiles (lower(email))
where email <> '';

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

drop trigger if exists trg_user_profiles_updated_at on public.user_profiles;
create trigger trg_user_profiles_updated_at
before update on public.user_profiles
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

create or replace function public.create_project(
    p_name text,
    p_logo_url text default '',
    p_logo_storage_path text default '',
    p_description text default '',
    p_company_type text default '',
    p_company_goal text default '',
    p_status text default 'active'
)
returns public.projects
language plpgsql
security definer
set search_path = public
as $$
declare
    v_user_id uuid := auth.uid();
    v_project public.projects;
    v_name text := trim(coalesce(p_name, ''));
    v_status text := case
        when lower(trim(coalesce(p_status, 'active'))) = 'archived' then 'archived'
        else 'active'
    end;
begin
    if v_user_id is null then
        raise exception 'Authentication required to create a project.' using errcode = '42501';
    end if;

    if v_name = '' then
        raise exception 'Project name is required.' using errcode = '23514';
    end if;

    insert into public.projects (
        owner_user_id,
        name,
        logo_url,
        logo_storage_path,
        description,
        company_type,
        company_goal,
        status
    )
    values (
        v_user_id,
        v_name,
        coalesce(p_logo_url, ''),
        coalesce(p_logo_storage_path, ''),
        coalesce(p_description, ''),
        coalesce(p_company_type, ''),
        coalesce(p_company_goal, ''),
        v_status
    )
    returning * into v_project;

    insert into public.project_members (project_id, user_id, invited_email, role, status, invited_by)
    values (v_project.id, v_user_id, '', 'owner', 'active', v_user_id)
    on conflict (project_id, user_id) where user_id is not null do update
    set role = 'owner',
        status = 'active',
        updated_at = timezone('utc', now());

    return v_project;
end;
$$;

grant execute on function public.create_project(text, text, text, text, text, text, text) to authenticated;

create or replace function public.sync_user_profile_from_auth()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
declare
    resolved_full_name text;
    resolved_avatar_url text;
    resolved_status text;
begin
    resolved_full_name := trim(coalesce(new.raw_user_meta_data ->> 'full_name', ''));

    if resolved_full_name = '' then
        resolved_full_name := trim(coalesce(new.raw_user_meta_data ->> 'name', ''));
    end if;

    resolved_avatar_url := trim(coalesce(new.raw_user_meta_data ->> 'avatar_url', ''));
    resolved_status := case when new.banned_until is null then 'active' else 'disabled' end;

    insert into public.user_profiles (user_id, email, full_name, avatar_url, status, created_at, updated_at)
    values (
        new.id,
        lower(coalesce(new.email, '')),
        resolved_full_name,
        resolved_avatar_url,
        resolved_status,
        coalesce(new.created_at, timezone('utc', now())),
        timezone('utc', now())
    )
    on conflict (user_id) do update
    set email = excluded.email,
        full_name = excluded.full_name,
        avatar_url = excluded.avatar_url,
        status = excluded.status,
        updated_at = timezone('utc', now());

    return new;
end;
$$;

do $$
begin
    begin
        execute 'drop trigger if exists trg_sync_user_profile_from_auth on auth.users';
        execute '
            create trigger trg_sync_user_profile_from_auth
            after insert or update on auth.users
            for each row
            execute function public.sync_user_profile_from_auth()
        ';
    exception
        when insufficient_privilege or undefined_table or invalid_schema_name then
            raise notice 'No fue posible crear el trigger de sincronizacion de perfiles desde auth.users: %', sqlerrm;
    end;
end;
$$;

do $$
begin
    begin
        insert into public.user_profiles (user_id, email, full_name, avatar_url, status, created_at, updated_at)
        select
            u.id,
            lower(coalesce(u.email, '')),
            trim(
                coalesce(
                    nullif(u.raw_user_meta_data ->> 'full_name', ''),
                    nullif(u.raw_user_meta_data ->> 'name', ''),
                    ''
                )
            ),
            trim(coalesce(u.raw_user_meta_data ->> 'avatar_url', '')),
            case when u.banned_until is null then 'active' else 'disabled' end,
            coalesce(u.created_at, timezone('utc', now())),
            timezone('utc', now())
        from auth.users u
        on conflict (user_id) do update
        set email = excluded.email,
            full_name = excluded.full_name,
            avatar_url = excluded.avatar_url,
            status = excluded.status,
            updated_at = timezone('utc', now());
    exception
        when insufficient_privilege or undefined_table or invalid_schema_name then
            raise notice 'No fue posible ejecutar la sincronizacion inicial de user_profiles desde auth.users: %', sqlerrm;
    end;
end;
$$;

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

create or replace function public.find_registered_user_by_email(p_email text)
returns table (
    user_id uuid,
    email text,
    full_name text,
    avatar_url text,
    status text
)
language sql
stable
security definer
set search_path = public
as $$
    select
        up.user_id,
        up.email,
        up.full_name,
        up.avatar_url,
        up.status
    from public.user_profiles up
    where lower(up.email) = lower(trim(coalesce(p_email, '')))
      and up.status = 'active'
    limit 1;
$$;

create or replace function public.get_project_member_directory(p_project_id uuid)
returns table (
    membership_id uuid,
    user_id uuid,
    email text,
    full_name text,
    avatar_url text,
    role text,
    status text,
    is_owner boolean,
    updated_at timestamptz
)
language sql
stable
security definer
set search_path = public
as $$
    select
        pm.id as membership_id,
        pm.user_id,
        lower(
            coalesce(
                nullif(pm.invited_email, ''),
                nullif(up.email, ''),
                ''
            )
        ) as email,
        coalesce(
            nullif(up.full_name, ''),
            nullif(split_part(coalesce(nullif(pm.invited_email, ''), up.email, ''), '@', 1), ''),
            'Usuario'
        ) as full_name,
        coalesce(up.avatar_url, '') as avatar_url,
        pm.role,
        pm.status,
        (pm.role = 'owner' or p.owner_user_id = pm.user_id) as is_owner,
        pm.updated_at
    from public.project_members pm
    join public.projects p
      on p.id = pm.project_id
     and p.deleted_at is null
    left join public.user_profiles up
      on up.user_id = pm.user_id
    where pm.project_id = p_project_id
      and public.can_access_project(p_project_id)
    order by
        case
            when pm.role = 'owner' then 0
            when pm.role = 'admin' then 1
            else 2
        end,
        lower(coalesce(nullif(pm.invited_email, ''), up.email, ''));
$$;

alter table public.projects enable row level security;
alter table public.project_members enable row level security;
alter table public.user_profiles enable row level security;

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

drop policy if exists "Users can read own profile" on public.user_profiles;
create policy "Users can read own profile"
on public.user_profiles
for select
to authenticated
using (user_id = auth.uid());

drop policy if exists "Users can insert own profile" on public.user_profiles;
create policy "Users can insert own profile"
on public.user_profiles
for insert
to authenticated
with check (user_id = auth.uid());

drop policy if exists "Users can update own profile" on public.user_profiles;
create policy "Users can update own profile"
on public.user_profiles
for update
to authenticated
using (user_id = auth.uid())
with check (user_id = auth.uid());

grant select, insert, update on public.projects to authenticated;
grant select, insert, update, delete on public.project_members to authenticated;
grant select, insert, update on public.user_profiles to authenticated;
grant execute on function public.find_registered_user_by_email(text) to authenticated;
grant execute on function public.get_project_member_directory(uuid) to authenticated;
