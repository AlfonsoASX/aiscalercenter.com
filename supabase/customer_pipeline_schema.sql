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

create table if not exists public.customer_pipeline_settings (
    project_id uuid primary key references public.projects (id) on delete cascade,
    public_key text not null unique default encode(gen_random_bytes(18), 'hex'),
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.customer_pipeline_stages (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    key text not null,
    title text not null,
    accent_color text not null default '#1a73e8',
    sort_order integer not null default 0,
    is_system boolean not null default true,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.customer_pipeline_leads (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    stage_id uuid not null references public.customer_pipeline_stages (id) on delete restrict,
    full_name text not null,
    email text not null default '',
    phone text not null default '',
    company_name text not null default '',
    source_label text not null default '',
    source_type text not null default '',
    source_reference text not null default '',
    currency_code text not null default 'MXN',
    estimated_value numeric(12, 2) not null default 0,
    notes text not null default '',
    lost_reason text not null default '',
    tags jsonb not null default '[]'::jsonb,
    metadata jsonb not null default '{}'::jsonb,
    assigned_user_id uuid references auth.users (id) on delete set null,
    follow_up_at timestamptz,
    sort_order numeric(20, 10) not null default 0,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    deleted_at timestamptz
);

create unique index if not exists customer_pipeline_stages_project_key_unique
on public.customer_pipeline_stages (project_id, key);

create index if not exists customer_pipeline_stages_project_order_idx
on public.customer_pipeline_stages (project_id, sort_order);

create index if not exists customer_pipeline_leads_project_stage_sort_idx
on public.customer_pipeline_leads (project_id, stage_id, sort_order, created_at desc);

create index if not exists customer_pipeline_leads_project_name_idx
on public.customer_pipeline_leads (project_id, lower(full_name));

create index if not exists customer_pipeline_leads_project_email_idx
on public.customer_pipeline_leads (project_id, lower(email));

create or replace function public.set_customer_pipeline_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_customer_pipeline_settings_updated_at on public.customer_pipeline_settings;
create trigger trg_customer_pipeline_settings_updated_at
before update on public.customer_pipeline_settings
for each row
execute function public.set_customer_pipeline_updated_at();

drop trigger if exists trg_customer_pipeline_stages_updated_at on public.customer_pipeline_stages;
create trigger trg_customer_pipeline_stages_updated_at
before update on public.customer_pipeline_stages
for each row
execute function public.set_customer_pipeline_updated_at();

drop trigger if exists trg_customer_pipeline_leads_updated_at on public.customer_pipeline_leads;
create trigger trg_customer_pipeline_leads_updated_at
before update on public.customer_pipeline_leads
for each row
execute function public.set_customer_pipeline_updated_at();

create or replace function public.seed_customer_pipeline_defaults(p_project_id uuid)
returns void
language plpgsql
security definer
set search_path = public
as $$
begin
    insert into public.customer_pipeline_settings (project_id)
    values (p_project_id)
    on conflict (project_id) do nothing;

    insert into public.customer_pipeline_stages (project_id, key, title, accent_color, sort_order, is_system)
    values
        (p_project_id, 'nuevo-lead', 'Nuevo Lead', '#1a73e8', 10, true),
        (p_project_id, 'contactado', 'Contactado', '#5f6368', 20, true),
        (p_project_id, 'en-negociacion', 'En Negociacion', '#d97706', 30, true),
        (p_project_id, 'ganado', 'Ganado', '#188038', 40, true),
        (p_project_id, 'perdido', 'Perdido', '#d93025', 50, true)
    on conflict (project_id, key) do nothing;
end;
$$;

create or replace function public.seed_customer_pipeline_for_new_project()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
    perform public.seed_customer_pipeline_defaults(new.id);
    return new;
end;
$$;

drop trigger if exists trg_seed_customer_pipeline_project on public.projects;
create trigger trg_seed_customer_pipeline_project
after insert on public.projects
for each row
execute function public.seed_customer_pipeline_for_new_project();

do $$
declare
    project_row record;
begin
    for project_row in
        select id
        from public.projects
        where deleted_at is null
    loop
        perform public.seed_customer_pipeline_defaults(project_row.id);
    end loop;
end $$;

create or replace function public.submit_public_customer_lead(
    p_public_key text,
    p_payload jsonb default '{}'::jsonb
)
returns table (
    lead_id uuid,
    project_id uuid,
    stage_id uuid,
    created_at timestamptz
)
language plpgsql
security definer
set search_path = public
as $$
declare
    resolved_project_id uuid;
    resolved_stage_id uuid;
    normalized_name text;
    normalized_email text;
    normalized_phone text;
    normalized_company text;
    normalized_source_label text;
    normalized_source_type text;
    normalized_source_reference text;
    normalized_currency text;
    normalized_notes text;
    normalized_lost_reason text;
    normalized_tags jsonb;
    normalized_metadata jsonb;
    normalized_value numeric(12, 2);
    next_sort_order numeric(20, 10);
begin
    normalized_name := trim(coalesce(p_payload ->> 'full_name', p_payload ->> 'name', ''));
    normalized_email := trim(coalesce(p_payload ->> 'email', ''));
    normalized_phone := trim(coalesce(p_payload ->> 'phone', p_payload ->> 'whatsapp', ''));
    normalized_company := trim(coalesce(p_payload ->> 'company_name', p_payload ->> 'company', ''));
    normalized_source_label := trim(coalesce(p_payload ->> 'source_label', p_payload ->> 'origin', 'Entrada automatica'));
    normalized_source_type := trim(coalesce(p_payload ->> 'source_type', 'webhook'));
    normalized_source_reference := trim(coalesce(p_payload ->> 'source_reference', ''));
    normalized_currency := upper(trim(coalesce(p_payload ->> 'currency_code', 'MXN')));
    normalized_notes := trim(coalesce(p_payload ->> 'notes', ''));
    normalized_lost_reason := trim(coalesce(p_payload ->> 'lost_reason', ''));
    normalized_tags := case
        when jsonb_typeof(p_payload -> 'tags') = 'array' then p_payload -> 'tags'
        else '[]'::jsonb
    end;
    normalized_metadata := case
        when jsonb_typeof(p_payload -> 'metadata') = 'object' then p_payload -> 'metadata'
        else '{}'::jsonb
    end;
    normalized_value := case
        when trim(coalesce(p_payload ->> 'estimated_value', '')) ~ '^-?[0-9]+(\\.[0-9]+)?$'
            then (p_payload ->> 'estimated_value')::numeric(12, 2)
        else 0
    end;

    if normalized_name = '' then
        raise exception 'El lead necesita al menos un nombre.';
    end if;

    select cps.project_id
    into resolved_project_id
    from public.customer_pipeline_settings cps
    join public.projects p on p.id = cps.project_id
    where cps.public_key = trim(coalesce(p_public_key, ''))
      and p.deleted_at is null
    limit 1;

    if resolved_project_id is null then
        raise exception 'La llave publica del proyecto no es valida.';
    end if;

    perform public.seed_customer_pipeline_defaults(resolved_project_id);

    select cps.id
    into resolved_stage_id
    from public.customer_pipeline_stages cps
    where cps.project_id = resolved_project_id
      and cps.key = 'nuevo-lead'
    order by cps.sort_order asc
    limit 1;

    if resolved_stage_id is null then
        raise exception 'No fue posible resolver la columna inicial del tablero.';
    end if;

    select coalesce(min(sort_order), 0) - 1024
    into next_sort_order
    from public.customer_pipeline_leads
    where project_id = resolved_project_id
      and stage_id = resolved_stage_id
      and deleted_at is null;

    return query
    insert into public.customer_pipeline_leads (
        project_id,
        stage_id,
        full_name,
        email,
        phone,
        company_name,
        source_label,
        source_type,
        source_reference,
        currency_code,
        estimated_value,
        notes,
        lost_reason,
        tags,
        metadata,
        sort_order
    )
    values (
        resolved_project_id,
        resolved_stage_id,
        normalized_name,
        normalized_email,
        normalized_phone,
        normalized_company,
        normalized_source_label,
        normalized_source_type,
        normalized_source_reference,
        normalized_currency,
        normalized_value,
        normalized_notes,
        normalized_lost_reason,
        normalized_tags,
        normalized_metadata,
        next_sort_order
    )
    returning
        id,
        project_id,
        stage_id,
        created_at;
end;
$$;

alter table public.customer_pipeline_settings enable row level security;
alter table public.customer_pipeline_stages enable row level security;
alter table public.customer_pipeline_leads enable row level security;

drop policy if exists "Users can view customer pipeline settings" on public.customer_pipeline_settings;
create policy "Users can view customer pipeline settings"
on public.customer_pipeline_settings
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Managers can update customer pipeline settings" on public.customer_pipeline_settings;
create policy "Managers can update customer pipeline settings"
on public.customer_pipeline_settings
for update
to authenticated
using (public.can_manage_project(project_id))
with check (public.can_manage_project(project_id));

drop policy if exists "Users can view customer pipeline stages" on public.customer_pipeline_stages;
create policy "Users can view customer pipeline stages"
on public.customer_pipeline_stages
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Managers can create customer pipeline stages" on public.customer_pipeline_stages;
create policy "Managers can create customer pipeline stages"
on public.customer_pipeline_stages
for insert
to authenticated
with check (public.can_manage_project(project_id));

drop policy if exists "Managers can update customer pipeline stages" on public.customer_pipeline_stages;
create policy "Managers can update customer pipeline stages"
on public.customer_pipeline_stages
for update
to authenticated
using (public.can_manage_project(project_id))
with check (public.can_manage_project(project_id));

drop policy if exists "Managers can delete customer pipeline stages" on public.customer_pipeline_stages;
create policy "Managers can delete customer pipeline stages"
on public.customer_pipeline_stages
for delete
to authenticated
using (public.can_manage_project(project_id));

drop policy if exists "Users can view customer pipeline leads" on public.customer_pipeline_leads;
create policy "Users can view customer pipeline leads"
on public.customer_pipeline_leads
for select
to authenticated
using (deleted_at is null and public.can_access_project(project_id));

drop policy if exists "Users can create customer pipeline leads" on public.customer_pipeline_leads;
create policy "Users can create customer pipeline leads"
on public.customer_pipeline_leads
for insert
to authenticated
with check (public.can_access_project(project_id));

drop policy if exists "Users can update customer pipeline leads" on public.customer_pipeline_leads;
create policy "Users can update customer pipeline leads"
on public.customer_pipeline_leads
for update
to authenticated
using (deleted_at is null and public.can_access_project(project_id))
with check (public.can_access_project(project_id));

drop policy if exists "Users can delete customer pipeline leads" on public.customer_pipeline_leads;
create policy "Users can delete customer pipeline leads"
on public.customer_pipeline_leads
for delete
to authenticated
using (public.can_access_project(project_id));

grant select on public.customer_pipeline_settings to authenticated;
grant update on public.customer_pipeline_settings to authenticated;
grant select, insert, update, delete on public.customer_pipeline_stages to authenticated;
grant select, insert, update, delete on public.customer_pipeline_leads to authenticated;
grant execute on function public.seed_customer_pipeline_defaults(uuid) to authenticated;
grant execute on function public.submit_public_customer_lead(text, jsonb) to anon, authenticated;
