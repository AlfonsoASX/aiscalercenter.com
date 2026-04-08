create extension if not exists pgcrypto;

-- Ejecuta supabase/projects_schema.sql antes de este archivo para habilitar public.can_access_project().

create table if not exists public.scheduled_posts (
    id uuid primary key default gen_random_uuid(),
    project_id uuid,
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    title text not null default '',
    body text not null default '',
    notes text not null default '',
    scheduled_at timestamptz not null,
    timezone text not null default 'America/Mexico_City',
    status text not null default 'scheduled' check (status in ('draft', 'scheduled', 'publishing', 'published', 'failed', 'cancelled')),
    auto_publish boolean not null default true,
    preview_provider_key text not null default '',
    asset_items jsonb not null default '[]'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

alter table public.scheduled_posts
add column if not exists project_id uuid;

create table if not exists public.scheduled_post_targets (
    id uuid primary key default gen_random_uuid(),
    post_id uuid not null references public.scheduled_posts (id) on delete cascade,
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    social_connection_id uuid not null references public.social_connections (id) on delete cascade,
    provider_key text not null,
    connection_label text not null default '',
    publication_type text not null default 'post',
    config jsonb not null default '{}'::jsonb,
    validation_snapshot jsonb not null default '[]'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create index if not exists scheduled_posts_owner_scheduled_idx on public.scheduled_posts (owner_user_id, scheduled_at asc);
create index if not exists scheduled_posts_project_scheduled_idx on public.scheduled_posts (project_id, scheduled_at asc);
create index if not exists scheduled_posts_status_idx on public.scheduled_posts (status);
create index if not exists scheduled_post_targets_post_idx on public.scheduled_post_targets (post_id);
create index if not exists scheduled_post_targets_owner_idx on public.scheduled_post_targets (owner_user_id, provider_key);

create or replace function public.set_scheduled_posts_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_scheduled_posts_updated_at on public.scheduled_posts;
create trigger trg_scheduled_posts_updated_at
before update on public.scheduled_posts
for each row
execute function public.set_scheduled_posts_updated_at();

create or replace function public.set_scheduled_post_targets_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_scheduled_post_targets_updated_at on public.scheduled_post_targets;
create trigger trg_scheduled_post_targets_updated_at
before update on public.scheduled_post_targets
for each row
execute function public.set_scheduled_post_targets_updated_at();

alter table public.scheduled_posts enable row level security;
alter table public.scheduled_post_targets enable row level security;

drop policy if exists "Users can view own scheduled posts" on public.scheduled_posts;
create policy "Users can view own scheduled posts"
on public.scheduled_posts
for select
to authenticated
using (
    owner_user_id = auth.uid()
    or (
        project_id is not null
        and public.can_access_project(project_id)
    )
);

drop policy if exists "Users can insert own scheduled posts" on public.scheduled_posts;
create policy "Users can insert own scheduled posts"
on public.scheduled_posts
for insert
to authenticated
with check (
    owner_user_id = auth.uid()
    and (
        project_id is null
        or public.can_access_project(project_id)
    )
);

drop policy if exists "Users can update own scheduled posts" on public.scheduled_posts;
create policy "Users can update own scheduled posts"
on public.scheduled_posts
for update
to authenticated
using (
    owner_user_id = auth.uid()
    or (
        project_id is not null
        and public.can_access_project(project_id)
    )
)
with check (
    owner_user_id = auth.uid()
    or (
        project_id is not null
        and public.can_access_project(project_id)
    )
);

drop policy if exists "Users can delete own scheduled posts" on public.scheduled_posts;
create policy "Users can delete own scheduled posts"
on public.scheduled_posts
for delete
to authenticated
using (
    owner_user_id = auth.uid()
    or (
        project_id is not null
        and public.can_access_project(project_id)
    )
);

drop policy if exists "Users can view own scheduled post targets" on public.scheduled_post_targets;
create policy "Users can view own scheduled post targets"
on public.scheduled_post_targets
for select
to authenticated
using (
    owner_user_id = auth.uid()
    or exists (
        select 1
        from public.scheduled_posts sp
        where sp.id = post_id
          and sp.project_id is not null
          and public.can_access_project(sp.project_id)
    )
);

drop policy if exists "Users can insert own scheduled post targets" on public.scheduled_post_targets;
create policy "Users can insert own scheduled post targets"
on public.scheduled_post_targets
for insert
to authenticated
with check (
    owner_user_id = auth.uid()
    and exists (
        select 1
        from public.scheduled_posts sp
        where sp.id = post_id
          and (
            sp.owner_user_id = auth.uid()
            or (
                sp.project_id is not null
                and public.can_access_project(sp.project_id)
            )
          )
    )
);

drop policy if exists "Users can update own scheduled post targets" on public.scheduled_post_targets;
create policy "Users can update own scheduled post targets"
on public.scheduled_post_targets
for update
to authenticated
using (
    owner_user_id = auth.uid()
    or exists (
        select 1
        from public.scheduled_posts sp
        where sp.id = post_id
          and sp.project_id is not null
          and public.can_access_project(sp.project_id)
    )
)
with check (
    owner_user_id = auth.uid()
    or exists (
        select 1
        from public.scheduled_posts sp
        where sp.id = post_id
          and sp.project_id is not null
          and public.can_access_project(sp.project_id)
    )
);

drop policy if exists "Users can delete own scheduled post targets" on public.scheduled_post_targets;
create policy "Users can delete own scheduled post targets"
on public.scheduled_post_targets
for delete
to authenticated
using (
    owner_user_id = auth.uid()
    or exists (
        select 1
        from public.scheduled_posts sp
        where sp.id = post_id
          and sp.project_id is not null
          and public.can_access_project(sp.project_id)
    )
);

grant select, insert, update, delete on public.scheduled_posts to authenticated;
grant select, insert, update, delete on public.scheduled_post_targets to authenticated;
