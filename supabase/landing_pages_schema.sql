create extension if not exists pgcrypto;

-- Ejecuta supabase/projects_schema.sql antes de este archivo.

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

create table if not exists public.landing_pages (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    public_id text not null unique default encode(gen_random_bytes(9), 'hex'),
    slug text not null,
    title text not null,
    description text not null default '',
    status text not null default 'draft' check (status in ('draft', 'published', 'archived')),
    blocks jsonb not null default '[]'::jsonb check (jsonb_typeof(blocks) = 'array'),
    settings jsonb not null default '{}'::jsonb check (jsonb_typeof(settings) = 'object'),
    metadata jsonb not null default '{}'::jsonb check (jsonb_typeof(metadata) = 'object'),
    view_count integer not null default 0,
    version integer not null default 1,
    published_at timestamptz,
    archived_at timestamptz,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    deleted_at timestamptz,
    constraint landing_pages_project_slug_unique unique (project_id, slug)
);

alter table public.landing_pages
    drop constraint if exists landing_pages_business_slug_unique,
    drop column if exists business_id cascade,
    add column if not exists project_id uuid;

drop table if exists public.business_members cascade;
drop table if exists public.businesses cascade;

drop index if exists public.landing_pages_business_status_idx;
create unique index if not exists landing_pages_project_slug_unique on public.landing_pages (project_id, slug);
create index if not exists landing_pages_project_status_idx on public.landing_pages (project_id, status, updated_at desc);
create index if not exists landing_pages_public_id_idx on public.landing_pages (public_id);
create index if not exists landing_pages_slug_idx on public.landing_pages (slug);

create or replace function public.set_landing_pages_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_landing_pages_updated_at on public.landing_pages;
create trigger trg_landing_pages_updated_at
before update on public.landing_pages
for each row
execute function public.set_landing_pages_updated_at();

create or replace function public.get_public_landing_page_definition(p_identifier text)
returns table (
    public_id text,
    slug text,
    title text,
    description text,
    blocks jsonb,
    settings jsonb,
    metadata jsonb
)
language sql
stable
security definer
set search_path = public
as $$
    select
        lp.public_id,
        lp.slug,
        lp.title,
        lp.description,
        lp.blocks,
        lp.settings,
        lp.metadata
    from public.landing_pages lp
    where lp.deleted_at is null
      and lp.status = 'published'
      and (lp.public_id = trim(p_identifier) or lp.slug = trim(p_identifier))
    order by lp.published_at desc nulls last, lp.updated_at desc
    limit 1;
$$;

alter table public.landing_pages enable row level security;

drop policy if exists "Business members can view landing pages" on public.landing_pages;
drop policy if exists "Business members can create landing pages" on public.landing_pages;
drop policy if exists "Business members can update landing pages" on public.landing_pages;

drop policy if exists "Project members can view landing pages" on public.landing_pages;
create policy "Project members can view landing pages"
on public.landing_pages
for select
to authenticated
using (
    deleted_at is null
    and project_id is not null
    and public.can_access_project(project_id)
);

drop policy if exists "Project members can create landing pages" on public.landing_pages;
create policy "Project members can create landing pages"
on public.landing_pages
for insert
to authenticated
with check (
    owner_user_id = auth.uid()
    and project_id is not null
    and public.can_access_project(project_id)
);

drop policy if exists "Project members can update landing pages" on public.landing_pages;
create policy "Project members can update landing pages"
on public.landing_pages
for update
to authenticated
using (
    deleted_at is null
    and project_id is not null
    and public.can_access_project(project_id)
)
with check (
    project_id is not null
    and public.can_access_project(project_id)
);

grant select, insert, update on public.landing_pages to authenticated;
grant execute on function public.get_public_landing_page_definition(text) to anon, authenticated;
