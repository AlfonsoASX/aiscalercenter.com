create extension if not exists pgcrypto;

create table if not exists public.blog_entries (
    id uuid primary key default gen_random_uuid(),
    title text not null,
    slug text not null unique,
    excerpt text not null default '',
    cover_image_url text not null default '',
    content_blocks jsonb not null default '[]'::jsonb,
    status text not null default 'draft' check (status in ('draft', 'published')),
    author_user_id uuid null references auth.users (id) on delete set null,
    author_name text not null default '',
    view_count bigint not null default 0,
    published_at timestamptz null,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create index if not exists blog_entries_status_idx on public.blog_entries (status);
create index if not exists blog_entries_published_at_idx on public.blog_entries (published_at desc nulls last);

create or replace function public.set_blog_entries_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_blog_entries_updated_at on public.blog_entries;

create trigger trg_blog_entries_updated_at
before update on public.blog_entries
for each row
execute function public.set_blog_entries_updated_at();

alter table public.blog_entries enable row level security;

drop policy if exists "Admins can manage blog entries" on public.blog_entries;
create policy "Admins can manage blog entries"
on public.blog_entries
for all
to authenticated
using (
    coalesce(auth.jwt() ->> 'email', '') = 'a@asx.mx'
    or coalesce(auth.jwt() -> 'app_metadata' ->> 'role', '') = 'admin'
)
with check (
    coalesce(auth.jwt() ->> 'email', '') = 'a@asx.mx'
    or coalesce(auth.jwt() -> 'app_metadata' ->> 'role', '') = 'admin'
);

drop policy if exists "Public can read published blog entries" on public.blog_entries;
create policy "Public can read published blog entries"
on public.blog_entries
for select
to anon
using (status = 'published');

grant select on public.blog_entries to anon;
grant select, insert, update, delete on public.blog_entries to authenticated;

create or replace function public.increment_blog_entry_view(entry_slug text)
returns bigint
language plpgsql
security definer
set search_path = public
as $$
declare
    new_count bigint;
begin
    update public.blog_entries
    set view_count = view_count + 1
    where slug = entry_slug
      and status = 'published'
    returning view_count into new_count;

    return coalesce(new_count, 0);
end;
$$;

grant execute on function public.increment_blog_entry_view(text) to anon;
grant execute on function public.increment_blog_entry_view(text) to authenticated;
