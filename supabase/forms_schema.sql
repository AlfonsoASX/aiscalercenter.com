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

create table if not exists public.forms (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    public_id text not null unique default encode(gen_random_bytes(9), 'hex'),
    slug text not null,
    title text not null,
    description text not null default '',
    status text not null default 'draft' check (status in ('draft', 'published', 'archived')),
    fields jsonb not null default '[]'::jsonb check (jsonb_typeof(fields) = 'array'),
    settings jsonb not null default '{}'::jsonb check (jsonb_typeof(settings) = 'object'),
    metadata jsonb not null default '{}'::jsonb check (jsonb_typeof(metadata) = 'object'),
    response_count integer not null default 0,
    version integer not null default 1,
    published_at timestamptz,
    archived_at timestamptz,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    deleted_at timestamptz,
    constraint forms_project_slug_unique unique (project_id, slug)
);

create table if not exists public.form_responses (
    id uuid primary key default gen_random_uuid(),
    form_id uuid not null references public.forms (id) on delete cascade,
    project_id uuid not null references public.projects (id) on delete cascade,
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    answers jsonb not null default '{}'::jsonb check (jsonb_typeof(answers) = 'object'),
    field_snapshot jsonb not null default '[]'::jsonb check (jsonb_typeof(field_snapshot) = 'array'),
    metadata jsonb not null default '{}'::jsonb check (jsonb_typeof(metadata) = 'object'),
    source text not null default 'public_form',
    submitted_at timestamptz not null default timezone('utc', now()),
    created_at timestamptz not null default timezone('utc', now())
);

alter table public.forms
    drop constraint if exists forms_business_slug_unique,
    drop column if exists business_id cascade,
    add column if not exists project_id uuid,
    add column if not exists metadata jsonb not null default '{}'::jsonb;

alter table public.form_responses
    drop column if exists business_id cascade,
    add column if not exists project_id uuid;

drop table if exists public.business_members cascade;
drop table if exists public.businesses cascade;

drop index if exists public.forms_business_status_idx;
drop index if exists public.form_responses_business_idx;
create unique index if not exists forms_project_slug_unique on public.forms (project_id, slug);
create index if not exists forms_project_status_idx on public.forms (project_id, status, updated_at desc);
create index if not exists forms_public_id_idx on public.forms (public_id);
create index if not exists form_responses_form_idx on public.form_responses (form_id, submitted_at desc);
create index if not exists form_responses_project_idx on public.form_responses (project_id, submitted_at desc);

create or replace function public.set_forms_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_forms_updated_at on public.forms;
create trigger trg_forms_updated_at
before update on public.forms
for each row
execute function public.set_forms_updated_at();

create or replace function public.increment_form_response_count()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
    update public.forms
    set response_count = response_count + 1,
        updated_at = timezone('utc', now())
    where id = new.form_id;

    return new;
end;
$$;

drop trigger if exists trg_form_response_count on public.form_responses;
create trigger trg_form_response_count
after insert on public.form_responses
for each row
execute function public.increment_form_response_count();

create or replace function public.get_public_form_definition(p_identifier text)
returns table (
    public_id text,
    slug text,
    title text,
    description text,
    fields jsonb,
    settings jsonb
)
language sql
stable
security definer
set search_path = public
as $$
    select
        f.public_id,
        f.slug,
        f.title,
        f.description,
        f.fields,
        f.settings
    from public.forms f
    where f.deleted_at is null
      and f.status = 'published'
      and (f.public_id = trim(p_identifier) or f.slug = trim(p_identifier))
    order by f.published_at desc nulls last, f.updated_at desc
    limit 1;
$$;

create or replace function public.submit_public_form_response(
    p_public_id text,
    p_answers jsonb,
    p_metadata jsonb default '{}'::jsonb
)
returns table (
    response_id uuid,
    submitted_at timestamptz
)
language plpgsql
security definer
set search_path = public
as $$
declare
    v_form public.forms%rowtype;
    v_response public.form_responses%rowtype;
begin
    select *
    into v_form
    from public.forms
    where public_id = trim(p_public_id)
      and status = 'published'
      and deleted_at is null
    limit 1;

    if not found then
        raise exception 'El formulario no esta disponible.';
    end if;

    if jsonb_typeof(coalesce(p_answers, '{}'::jsonb)) <> 'object' then
        raise exception 'Las respuestas deben enviarse como JSON object.';
    end if;

    insert into public.form_responses (
        form_id,
        project_id,
        owner_user_id,
        answers,
        field_snapshot,
        metadata
    )
    values (
        v_form.id,
        v_form.project_id,
        v_form.owner_user_id,
        coalesce(p_answers, '{}'::jsonb),
        v_form.fields,
        coalesce(p_metadata, '{}'::jsonb)
    )
    returning *
    into v_response;

    response_id := v_response.id;
    submitted_at := v_response.submitted_at;
    return next;
end;
$$;

alter table public.forms enable row level security;
alter table public.form_responses enable row level security;

drop policy if exists "Business members can view forms" on public.forms;
drop policy if exists "Business members can create forms" on public.forms;
drop policy if exists "Business members can update forms" on public.forms;
drop policy if exists "Business members can view form responses" on public.form_responses;

drop policy if exists "Project members can view forms" on public.forms;
create policy "Project members can view forms"
on public.forms
for select
to authenticated
using (
    deleted_at is null
    and project_id is not null
    and public.can_access_project(project_id)
);

drop policy if exists "Project members can create forms" on public.forms;
create policy "Project members can create forms"
on public.forms
for insert
to authenticated
with check (
    owner_user_id = auth.uid()
    and project_id is not null
    and public.can_access_project(project_id)
);

drop policy if exists "Project members can update forms" on public.forms;
create policy "Project members can update forms"
on public.forms
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

drop policy if exists "Project members can view form responses" on public.form_responses;
create policy "Project members can view form responses"
on public.form_responses
for select
to authenticated
using (
    project_id is not null
    and public.can_access_project(project_id)
);

grant select, insert, update on public.forms to authenticated;
grant select on public.form_responses to authenticated;
grant execute on function public.get_public_form_definition(text) to anon, authenticated;
grant execute on function public.submit_public_form_response(text, jsonb, jsonb) to anon, authenticated;
