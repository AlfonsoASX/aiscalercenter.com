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

create table if not exists public.businesses (
    id uuid primary key default gen_random_uuid(),
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    name text not null,
    slug text,
    settings jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    deleted_at timestamptz
);

create table if not exists public.business_members (
    business_id uuid not null references public.businesses (id) on delete cascade,
    user_id uuid not null references auth.users (id) on delete cascade,
    role text not null default 'member' check (role in ('owner', 'admin', 'member')),
    status text not null default 'active' check (status in ('active', 'invited', 'disabled')),
    invited_by uuid references auth.users (id) on delete set null,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    primary key (business_id, user_id)
);

create table if not exists public.forms (
    id uuid primary key default gen_random_uuid(),
    business_id uuid not null references public.businesses (id) on delete cascade,
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
    constraint forms_business_slug_unique unique (business_id, slug)
);

create table if not exists public.form_responses (
    id uuid primary key default gen_random_uuid(),
    form_id uuid not null references public.forms (id) on delete cascade,
    business_id uuid not null references public.businesses (id) on delete cascade,
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    answers jsonb not null default '{}'::jsonb check (jsonb_typeof(answers) = 'object'),
    field_snapshot jsonb not null default '[]'::jsonb check (jsonb_typeof(field_snapshot) = 'array'),
    metadata jsonb not null default '{}'::jsonb check (jsonb_typeof(metadata) = 'object'),
    source text not null default 'public_form',
    submitted_at timestamptz not null default timezone('utc', now()),
    created_at timestamptz not null default timezone('utc', now())
);

create index if not exists businesses_owner_idx on public.businesses (owner_user_id);
create index if not exists business_members_user_idx on public.business_members (user_id, status);
create index if not exists forms_business_status_idx on public.forms (business_id, status, updated_at desc);
create index if not exists forms_public_id_idx on public.forms (public_id);
create index if not exists form_responses_form_idx on public.form_responses (form_id, submitted_at desc);
create index if not exists form_responses_business_idx on public.form_responses (business_id, submitted_at desc);

create or replace function public.set_forms_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_businesses_updated_at on public.businesses;
create trigger trg_businesses_updated_at
before update on public.businesses
for each row
execute function public.set_forms_updated_at();

drop trigger if exists trg_business_members_updated_at on public.business_members;
create trigger trg_business_members_updated_at
before update on public.business_members
for each row
execute function public.set_forms_updated_at();

drop trigger if exists trg_forms_updated_at on public.forms;
create trigger trg_forms_updated_at
before update on public.forms
for each row
execute function public.set_forms_updated_at();

create or replace function public.ensure_business_owner_member()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
    insert into public.business_members (business_id, user_id, role, status)
    values (new.id, new.owner_user_id, 'owner', 'active')
    on conflict (business_id, user_id) do update
    set role = 'owner',
        status = 'active',
        updated_at = timezone('utc', now());

    return new;
end;
$$;

drop trigger if exists trg_business_owner_member on public.businesses;
create trigger trg_business_owner_member
after insert on public.businesses
for each row
execute function public.ensure_business_owner_member();

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

create or replace function public.can_access_business(p_business_id uuid)
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
            from public.businesses b
            where b.id = p_business_id
              and b.owner_user_id = auth.uid()
              and b.deleted_at is null
        )
        or exists (
            select 1
            from public.business_members bm
            where bm.business_id = p_business_id
              and bm.user_id = auth.uid()
              and bm.status = 'active'
        );
$$;

create or replace function public.can_manage_business(p_business_id uuid)
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
            from public.businesses b
            where b.id = p_business_id
              and b.owner_user_id = auth.uid()
              and b.deleted_at is null
        )
        or exists (
            select 1
            from public.business_members bm
            where bm.business_id = p_business_id
              and bm.user_id = auth.uid()
              and bm.status = 'active'
              and bm.role in ('owner', 'admin')
        );
$$;

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
        business_id,
        owner_user_id,
        answers,
        field_snapshot,
        metadata
    )
    values (
        v_form.id,
        v_form.business_id,
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

alter table public.businesses enable row level security;
alter table public.business_members enable row level security;
alter table public.forms enable row level security;
alter table public.form_responses enable row level security;

drop policy if exists "Business members can view businesses" on public.businesses;
create policy "Business members can view businesses"
on public.businesses
for select
to authenticated
using (public.can_access_business(id));

drop policy if exists "Users can create owned businesses" on public.businesses;
create policy "Users can create owned businesses"
on public.businesses
for insert
to authenticated
with check (owner_user_id = auth.uid() or public.is_admin_user());

drop policy if exists "Business owners can update businesses" on public.businesses;
create policy "Business owners can update businesses"
on public.businesses
for update
to authenticated
using (public.can_manage_business(id))
with check (public.can_manage_business(id));

drop policy if exists "Business members can view memberships" on public.business_members;
create policy "Business members can view memberships"
on public.business_members
for select
to authenticated
using (public.can_access_business(business_id));

drop policy if exists "Business owners can manage memberships" on public.business_members;
create policy "Business owners can manage memberships"
on public.business_members
for all
to authenticated
using (public.can_manage_business(business_id))
with check (public.can_manage_business(business_id));

drop policy if exists "Business members can view forms" on public.forms;
create policy "Business members can view forms"
on public.forms
for select
to authenticated
using (deleted_at is null and public.can_access_business(business_id));

drop policy if exists "Business members can create forms" on public.forms;
create policy "Business members can create forms"
on public.forms
for insert
to authenticated
with check (
    owner_user_id = auth.uid()
    and deleted_at is null
    and public.can_access_business(business_id)
);

drop policy if exists "Business members can update forms" on public.forms;
create policy "Business members can update forms"
on public.forms
for update
to authenticated
using (deleted_at is null and public.can_access_business(business_id))
with check (public.can_access_business(business_id));

drop policy if exists "Business members can view form responses" on public.form_responses;
create policy "Business members can view form responses"
on public.form_responses
for select
to authenticated
using (public.can_access_business(business_id));

grant select, insert, update on public.businesses to authenticated;
grant select, insert, update on public.business_members to authenticated;
grant select, insert, update on public.forms to authenticated;
grant select on public.form_responses to authenticated;
grant execute on function public.get_public_form_definition(text) to anon, authenticated;
grant execute on function public.submit_public_form_response(text, jsonb, jsonb) to anon, authenticated;
