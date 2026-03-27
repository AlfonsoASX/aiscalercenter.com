create extension if not exists pgcrypto;

create table if not exists public.courses (
    id uuid primary key default gen_random_uuid(),
    title text not null,
    slug text not null unique,
    description text not null default '',
    status text not null default 'draft' check (status in ('draft', 'published')),
    sections jsonb not null default '[]'::jsonb,
    loose_items jsonb not null default '[]'::jsonb,
    author_user_id uuid null references auth.users (id) on delete set null,
    author_name text not null default '',
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create index if not exists courses_status_idx on public.courses (status);
create index if not exists courses_updated_at_idx on public.courses (updated_at desc);

create or replace function public.set_courses_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_courses_updated_at on public.courses;

create trigger trg_courses_updated_at
before update on public.courses
for each row
execute function public.set_courses_updated_at();

alter table public.courses enable row level security;

drop policy if exists "Admins can manage courses" on public.courses;
create policy "Admins can manage courses"
on public.courses
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

grant select, insert, update, delete on public.courses to authenticated;
