create extension if not exists pgcrypto;

create table if not exists public.social_connections (
    id uuid primary key default gen_random_uuid(),
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    provider_key text not null,
    platform_label text not null default '',
    connection_label text not null default '',
    display_name text not null,
    handle text not null default '',
    external_id text not null default '',
    asset_url text not null default '',
    notes text not null default '',
    connection_status text not null default 'pending_auth' check (connection_status in ('pending_auth', 'connected', 'error', 'paused')),
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create index if not exists social_connections_owner_idx on public.social_connections (owner_user_id, created_at desc);
create index if not exists social_connections_provider_idx on public.social_connections (provider_key);

create or replace function public.set_social_connections_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_social_connections_updated_at on public.social_connections;

create trigger trg_social_connections_updated_at
before update on public.social_connections
for each row
execute function public.set_social_connections_updated_at();

alter table public.social_connections enable row level security;

drop policy if exists "Users can view own social connections" on public.social_connections;
create policy "Users can view own social connections"
on public.social_connections
for select
to authenticated
using (owner_user_id = auth.uid());

drop policy if exists "Users can insert own social connections" on public.social_connections;
create policy "Users can insert own social connections"
on public.social_connections
for insert
to authenticated
with check (owner_user_id = auth.uid());

drop policy if exists "Users can update own social connections" on public.social_connections;
create policy "Users can update own social connections"
on public.social_connections
for update
to authenticated
using (owner_user_id = auth.uid())
with check (owner_user_id = auth.uid());

drop policy if exists "Users can delete own social connections" on public.social_connections;
create policy "Users can delete own social connections"
on public.social_connections
for delete
to authenticated
using (owner_user_id = auth.uid());

grant select, insert, update, delete on public.social_connections to authenticated;
