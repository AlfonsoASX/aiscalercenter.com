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

create table if not exists public.form_sessions (
    id uuid primary key default gen_random_uuid(),
    form_id uuid not null references public.forms (id) on delete cascade,
    project_id uuid not null references public.projects (id) on delete cascade,
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    session_key text not null,
    status text not null default 'viewed' check (status in ('viewed', 'started', 'completed', 'abandoned')),
    question_count integer not null default 0,
    answered_count integer not null default 0,
    metadata jsonb not null default '{}'::jsonb check (jsonb_typeof(metadata) = 'object'),
    visited_at timestamptz not null default timezone('utc', now()),
    started_at timestamptz,
    completed_at timestamptz,
    abandoned_at timestamptz,
    last_seen_at timestamptz not null default timezone('utc', now()),
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    constraint form_sessions_form_session_key_unique unique (form_id, session_key)
);

alter table public.forms
    drop constraint if exists forms_business_slug_unique,
    drop column if exists business_id cascade,
    add column if not exists project_id uuid,
    add column if not exists metadata jsonb not null default '{}'::jsonb;

alter table public.form_responses
    drop column if exists business_id cascade,
    add column if not exists project_id uuid;

alter table public.form_sessions
    add column if not exists project_id uuid,
    add column if not exists owner_user_id uuid,
    add column if not exists session_key text,
    add column if not exists status text not null default 'viewed',
    add column if not exists question_count integer not null default 0,
    add column if not exists answered_count integer not null default 0,
    add column if not exists metadata jsonb not null default '{}'::jsonb,
    add column if not exists visited_at timestamptz not null default timezone('utc', now()),
    add column if not exists started_at timestamptz,
    add column if not exists completed_at timestamptz,
    add column if not exists abandoned_at timestamptz,
    add column if not exists last_seen_at timestamptz not null default timezone('utc', now()),
    add column if not exists created_at timestamptz not null default timezone('utc', now()),
    add column if not exists updated_at timestamptz not null default timezone('utc', now());

drop table if exists public.business_members cascade;
drop table if exists public.businesses cascade;

drop index if exists public.forms_business_status_idx;
drop index if exists public.form_responses_business_idx;
create unique index if not exists forms_project_slug_unique on public.forms (project_id, slug);
create index if not exists forms_project_status_idx on public.forms (project_id, status, updated_at desc);
create index if not exists forms_public_id_idx on public.forms (public_id);
create index if not exists form_responses_form_idx on public.form_responses (form_id, submitted_at desc);
create index if not exists form_responses_project_idx on public.form_responses (project_id, submitted_at desc);
create unique index if not exists form_sessions_form_session_key_unique on public.form_sessions (form_id, session_key);
create index if not exists form_sessions_project_idx on public.form_sessions (project_id, visited_at desc);
create index if not exists form_sessions_status_idx on public.form_sessions (form_id, status, last_seen_at desc);

create or replace function public.set_forms_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

create or replace function public.set_form_sessions_updated_at()
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

drop trigger if exists trg_form_sessions_updated_at on public.form_sessions;
create trigger trg_form_sessions_updated_at
before update on public.form_sessions
for each row
execute function public.set_form_sessions_updated_at();

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

drop function if exists public.track_public_form_session(text, text, text, integer, integer, jsonb);

create or replace function public.track_public_form_session(
    p_public_id text,
    p_session_key text,
    p_event text default 'view',
    p_answered_count integer default null,
    p_question_count integer default null,
    p_metadata jsonb default '{}'::jsonb
)
returns table (
    tracked_session_key text,
    tracked_status text,
    tracked_updated_at timestamptz
)
language plpgsql
security definer
set search_path = public
as $$
declare
    v_form public.forms%rowtype;
    v_session public.form_sessions%rowtype;
    v_event text := lower(trim(coalesce(p_event, 'view')));
    v_now timestamptz := timezone('utc', now());
    v_started_at timestamptz := null;
    v_completed_at timestamptz := null;
    v_abandoned_at timestamptz := null;
    v_status text := 'viewed';
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

    if trim(coalesce(p_session_key, '')) = '' then
        raise exception 'La sesion publica del formulario es obligatoria.';
    end if;

    if v_event not in ('view', 'start', 'progress', 'complete', 'abandon') then
        raise exception 'Evento de seguimiento no soportado.';
    end if;

    if v_event in ('start', 'progress', 'complete', 'abandon') then
        v_started_at := v_now;
        v_status := 'started';
    end if;

    if v_event = 'complete' then
        v_completed_at := v_now;
        v_status := 'completed';
    elsif v_event = 'abandon' then
        v_abandoned_at := v_now;
        v_status := 'abandoned';
    end if;

    insert into public.form_sessions (
        form_id,
        project_id,
        owner_user_id,
        session_key,
        status,
        question_count,
        answered_count,
        metadata,
        visited_at,
        started_at,
        completed_at,
        abandoned_at,
        last_seen_at
    )
    values (
        v_form.id,
        v_form.project_id,
        v_form.owner_user_id,
        trim(p_session_key),
        v_status,
        greatest(coalesce(p_question_count, 0), 0),
        greatest(coalesce(p_answered_count, 0), 0),
        coalesce(p_metadata, '{}'::jsonb),
        v_now,
        v_started_at,
        v_completed_at,
        v_abandoned_at,
        v_now
    )
    on conflict (form_id, session_key)
    do update set
        status = case
            when public.form_sessions.completed_at is not null or excluded.completed_at is not null then 'completed'
            when excluded.status = 'abandoned' and public.form_sessions.completed_at is null then 'abandoned'
            when public.form_sessions.started_at is not null or excluded.started_at is not null then 'started'
            else 'viewed'
        end,
        question_count = greatest(public.form_sessions.question_count, excluded.question_count),
        answered_count = greatest(public.form_sessions.answered_count, excluded.answered_count),
        metadata = coalesce(public.form_sessions.metadata, '{}'::jsonb) || coalesce(excluded.metadata, '{}'::jsonb),
        visited_at = coalesce(public.form_sessions.visited_at, excluded.visited_at),
        started_at = case
            when excluded.started_at is null then public.form_sessions.started_at
            else coalesce(public.form_sessions.started_at, excluded.started_at)
        end,
        completed_at = case
            when excluded.completed_at is null then public.form_sessions.completed_at
            else coalesce(public.form_sessions.completed_at, excluded.completed_at)
        end,
        abandoned_at = case
            when public.form_sessions.completed_at is not null or excluded.completed_at is not null then null
            when excluded.abandoned_at is null then public.form_sessions.abandoned_at
            else excluded.abandoned_at
        end,
        last_seen_at = excluded.last_seen_at
    returning *
    into v_session;

    tracked_session_key := v_session.session_key;
    tracked_status := v_session.status;
    tracked_updated_at := v_session.updated_at;
    return next;
end;
$$;

alter table public.forms enable row level security;
alter table public.form_responses enable row level security;
alter table public.form_sessions enable row level security;

drop policy if exists "Business members can view forms" on public.forms;
drop policy if exists "Business members can create forms" on public.forms;
drop policy if exists "Business members can update forms" on public.forms;
drop policy if exists "Business members can view form responses" on public.form_responses;
drop policy if exists "Project members can view form sessions" on public.form_sessions;

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

drop policy if exists "Project members can view form sessions" on public.form_sessions;
create policy "Project members can view form sessions"
on public.form_sessions
for select
to authenticated
using (
    project_id is not null
    and public.can_access_project(project_id)
);

grant select, insert, update on public.forms to authenticated;
grant select on public.form_responses to authenticated;
grant select on public.form_sessions to authenticated;
grant execute on function public.get_public_form_definition(text) to anon, authenticated;
grant execute on function public.submit_public_form_response(text, jsonb, jsonb) to anon, authenticated;
grant execute on function public.track_public_form_session(text, text, text, integer, integer, jsonb) to anon, authenticated;
