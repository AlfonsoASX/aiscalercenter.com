create extension if not exists pgcrypto;

create table if not exists public.whatsapp_bots (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    public_key text not null unique default encode(gen_random_bytes(18), 'hex'),
    verify_token text not null unique default encode(gen_random_bytes(18), 'hex'),
    name text not null default 'Bot de WhatsApp',
    tone text not null default 'amigable' check (tone in ('formal', 'amigable', 'directo')),
    welcome_message text not null default '',
    handoff_message text not null default 'Te transfiero con un especialista en un momento.',
    off_hours_message text not null default 'Nuestros asesores estan dormidos, pero te atenderan manana a primera hora.',
    fallback_message text not null default 'No termine de entenderte. Me ayudas eligiendo una opcion?',
    unknown_attempt_limit integer not null default 3,
    timezone text not null default 'America/Mexico_City',
    business_phone_label text not null default '',
    provider_phone_number_id text not null default '',
    provider_waba_id text not null default '',
    flow_definition jsonb not null default '{}'::jsonb,
    schedule_definition jsonb not null default '{}'::jsonb,
    routing_definition jsonb not null default '{}'::jsonb,
    status text not null default 'draft' check (status in ('draft', 'active', 'paused')),
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    deleted_at timestamptz
);

create table if not exists public.whatsapp_bot_templates (
    id uuid primary key default gen_random_uuid(),
    bot_id uuid not null references public.whatsapp_bots (id) on delete cascade,
    project_id uuid not null references public.projects (id) on delete cascade,
    owner_user_id uuid not null references auth.users (id) on delete cascade,
    name text not null,
    slug text not null default '',
    category text not null default 'utility',
    header_text text not null default '',
    body_text text not null default '',
    footer_text text not null default '',
    variables jsonb not null default '[]'::jsonb,
    approval_status text not null default 'pendiente' check (approval_status in ('pendiente', 'aprobado', 'rechazado')),
    meta_template_id text not null default '',
    media_url text not null default '',
    media_storage_path text not null default '',
    media_kind text not null default 'none' check (media_kind in ('none', 'image', 'audio', 'document')),
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    deleted_at timestamptz
);

create table if not exists public.whatsapp_bot_conversations (
    id uuid primary key default gen_random_uuid(),
    bot_id uuid not null references public.whatsapp_bots (id) on delete cascade,
    project_id uuid not null references public.projects (id) on delete cascade,
    lead_id uuid references public.customer_pipeline_leads (id) on delete set null,
    customer_name text not null default '',
    customer_email text not null default '',
    customer_phone text not null,
    customer_company text not null default '',
    source_label text not null default '',
    source_reference text not null default '',
    conversation_state text not null default 'bot_activo' check (conversation_state in ('bot_activo', 'bot_pausado', 'sesion_cerrada')),
    inbox_status text not null default 'bot' check (inbox_status in ('bot', 'humano')),
    session_expires_at timestamptz,
    last_customer_message_at timestamptz,
    last_message_preview text not null default '',
    unread_count integer not null default 0,
    unknown_attempts integer not null default 0,
    assigned_user_id uuid references auth.users (id) on delete set null,
    bot_context jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    archived_at timestamptz
);

create table if not exists public.whatsapp_bot_messages (
    id uuid primary key default gen_random_uuid(),
    conversation_id uuid not null references public.whatsapp_bot_conversations (id) on delete cascade,
    bot_id uuid not null references public.whatsapp_bots (id) on delete cascade,
    project_id uuid not null references public.projects (id) on delete cascade,
    direction text not null default 'incoming' check (direction in ('incoming', 'outgoing')),
    author_type text not null default 'customer' check (author_type in ('customer', 'bot', 'human', 'system')),
    message_type text not null default 'text' check (message_type in ('text', 'button', 'list', 'template', 'image', 'audio', 'document', 'status')),
    body text not null default '',
    attachment_url text not null default '',
    attachment_storage_path text not null default '',
    attachment_mime text not null default '',
    payload jsonb not null default '{}'::jsonb,
    delivery_status text not null default 'received' check (delivery_status in ('queued', 'sent', 'delivered', 'read', 'failed', 'received')),
    external_message_id text not null default '',
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.whatsapp_bot_events (
    id uuid primary key default gen_random_uuid(),
    bot_id uuid references public.whatsapp_bots (id) on delete cascade,
    project_id uuid references public.projects (id) on delete cascade,
    conversation_id uuid references public.whatsapp_bot_conversations (id) on delete cascade,
    event_type text not null,
    status text not null default 'processed' check (status in ('received', 'processed', 'failed')),
    payload jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now())
);

create unique index if not exists whatsapp_bot_conversations_bot_phone_unique
on public.whatsapp_bot_conversations (bot_id, customer_phone)
where archived_at is null;

create index if not exists whatsapp_bots_project_idx
on public.whatsapp_bots (project_id, updated_at desc);

create index if not exists whatsapp_bot_templates_bot_idx
on public.whatsapp_bot_templates (bot_id, updated_at desc);

create index if not exists whatsapp_bot_conversations_bot_inbox_idx
on public.whatsapp_bot_conversations (bot_id, inbox_status, updated_at desc);

create index if not exists whatsapp_bot_messages_conversation_idx
on public.whatsapp_bot_messages (conversation_id, created_at asc);

create index if not exists whatsapp_bot_messages_external_idx
on public.whatsapp_bot_messages (external_message_id);

create or replace function public.set_whatsapp_bot_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_whatsapp_bots_updated_at on public.whatsapp_bots;
create trigger trg_whatsapp_bots_updated_at
before update on public.whatsapp_bots
for each row
execute function public.set_whatsapp_bot_updated_at();

drop trigger if exists trg_whatsapp_bot_templates_updated_at on public.whatsapp_bot_templates;
create trigger trg_whatsapp_bot_templates_updated_at
before update on public.whatsapp_bot_templates
for each row
execute function public.set_whatsapp_bot_updated_at();

drop trigger if exists trg_whatsapp_bot_conversations_updated_at on public.whatsapp_bot_conversations;
create trigger trg_whatsapp_bot_conversations_updated_at
before update on public.whatsapp_bot_conversations
for each row
execute function public.set_whatsapp_bot_updated_at();

drop trigger if exists trg_whatsapp_bot_messages_updated_at on public.whatsapp_bot_messages;
create trigger trg_whatsapp_bot_messages_updated_at
before update on public.whatsapp_bot_messages
for each row
execute function public.set_whatsapp_bot_updated_at();

create or replace function public.whatsapp_bot_row_as_json(p_bot_id uuid)
returns jsonb
language sql
stable
security definer
set search_path = public
as $$
    select to_jsonb(b)
    from public.whatsapp_bots b
    where b.id = p_bot_id
      and b.deleted_at is null
    limit 1;
$$;

create or replace function public.whatsapp_bot_conversation_as_json(p_conversation_id uuid)
returns jsonb
language sql
stable
security definer
set search_path = public
as $$
    select to_jsonb(c)
    from public.whatsapp_bot_conversations c
    where c.id = p_conversation_id
      and c.archived_at is null
    limit 1;
$$;

create or replace function public.whatsapp_bot_message_as_json(p_message_id uuid)
returns jsonb
language sql
stable
security definer
set search_path = public
as $$
    select to_jsonb(m)
    from public.whatsapp_bot_messages m
    where m.id = p_message_id
    limit 1;
$$;

create or replace function public.get_public_whatsapp_bot_context(p_public_key text)
returns jsonb
language sql
stable
security definer
set search_path = public
as $$
    select to_jsonb(b)
    from public.whatsapp_bots b
    join public.projects p on p.id = b.project_id
    where b.public_key = trim(coalesce(p_public_key, ''))
      and b.deleted_at is null
      and p.deleted_at is null
    limit 1;
$$;

create or replace function public.get_public_whatsapp_conversation_state(
    p_public_key text,
    p_customer_phone text
)
returns jsonb
language sql
stable
security definer
set search_path = public
as $$
    with resolved_bot as (
        select b.id
        from public.whatsapp_bots b
        where b.public_key = trim(coalesce(p_public_key, ''))
          and b.deleted_at is null
        limit 1
    )
    select to_jsonb(c)
    from public.whatsapp_bot_conversations c
    join resolved_bot rb on rb.id = c.bot_id
    where c.customer_phone = regexp_replace(trim(coalesce(p_customer_phone, '')), '[^0-9]+', '', 'g')
      and c.archived_at is null
    limit 1;
$$;

create or replace function public.upsert_public_whatsapp_conversation(
    p_public_key text,
    p_payload jsonb default '{}'::jsonb
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    resolved_bot public.whatsapp_bots%rowtype;
    existing_conversation public.whatsapp_bot_conversations%rowtype;
    resolved_conversation_id uuid;
    normalized_phone text;
    next_expires_at timestamptz;
begin
    select *
    into resolved_bot
    from public.whatsapp_bots
    where public_key = trim(coalesce(p_public_key, ''))
      and deleted_at is null
    limit 1;

    if resolved_bot.id is null then
        raise exception 'No encontramos el bot publico solicitado.';
    end if;

    normalized_phone := regexp_replace(trim(coalesce(p_payload ->> 'customer_phone', '')), '[^0-9]+', '', 'g');

    if normalized_phone = '' then
        raise exception 'La conversacion necesita un numero de telefono.';
    end if;

    select *
    into existing_conversation
    from public.whatsapp_bot_conversations
    where bot_id = resolved_bot.id
      and customer_phone = normalized_phone
      and archived_at is null
    limit 1;

    next_expires_at := case
        when trim(coalesce(p_payload ->> 'session_expires_at', '')) <> '' then (p_payload ->> 'session_expires_at')::timestamptz
        else timezone('utc', now()) + interval '24 hours'
    end;

    if existing_conversation.id is null then
        insert into public.whatsapp_bot_conversations (
            bot_id,
            project_id,
            lead_id,
            customer_name,
            customer_email,
            customer_phone,
            customer_company,
            source_label,
            source_reference,
            conversation_state,
            inbox_status,
            session_expires_at,
            last_customer_message_at,
            last_message_preview,
            unread_count,
            unknown_attempts,
            assigned_user_id,
            bot_context
        )
        values (
            resolved_bot.id,
            resolved_bot.project_id,
            nullif(trim(coalesce(p_payload ->> 'lead_id', '')), '')::uuid,
            trim(coalesce(p_payload ->> 'customer_name', '')),
            trim(coalesce(p_payload ->> 'customer_email', '')),
            normalized_phone,
            trim(coalesce(p_payload ->> 'customer_company', '')),
            trim(coalesce(p_payload ->> 'source_label', 'WhatsApp')),
            trim(coalesce(p_payload ->> 'source_reference', '')),
            coalesce(nullif(trim(coalesce(p_payload ->> 'conversation_state', '')), ''), 'bot_activo'),
            coalesce(nullif(trim(coalesce(p_payload ->> 'inbox_status', '')), ''), 'bot'),
            next_expires_at,
            case
                when trim(coalesce(p_payload ->> 'last_customer_message_at', '')) <> '' then (p_payload ->> 'last_customer_message_at')::timestamptz
                else timezone('utc', now())
            end,
            trim(coalesce(p_payload ->> 'last_message_preview', '')),
            greatest(0, coalesce((p_payload ->> 'unread_count')::int, 0)),
            greatest(0, coalesce((p_payload ->> 'unknown_attempts')::int, 0)),
            nullif(trim(coalesce(p_payload ->> 'assigned_user_id', '')), '')::uuid,
            case
                when jsonb_typeof(p_payload -> 'bot_context') = 'object' then p_payload -> 'bot_context'
                else '{}'::jsonb
            end
        )
        returning id into resolved_conversation_id;
    else
        update public.whatsapp_bot_conversations
        set lead_id = coalesce(nullif(trim(coalesce(p_payload ->> 'lead_id', '')), '')::uuid, existing_conversation.lead_id),
            customer_name = coalesce(nullif(trim(coalesce(p_payload ->> 'customer_name', '')), ''), existing_conversation.customer_name),
            customer_email = coalesce(trim(coalesce(p_payload ->> 'customer_email', '')), existing_conversation.customer_email),
            customer_company = coalesce(trim(coalesce(p_payload ->> 'customer_company', '')), existing_conversation.customer_company),
            source_label = coalesce(nullif(trim(coalesce(p_payload ->> 'source_label', '')), ''), existing_conversation.source_label),
            source_reference = coalesce(trim(coalesce(p_payload ->> 'source_reference', '')), existing_conversation.source_reference),
            conversation_state = coalesce(nullif(trim(coalesce(p_payload ->> 'conversation_state', '')), ''), existing_conversation.conversation_state),
            inbox_status = coalesce(nullif(trim(coalesce(p_payload ->> 'inbox_status', '')), ''), existing_conversation.inbox_status),
            session_expires_at = next_expires_at,
            last_customer_message_at = case
                when trim(coalesce(p_payload ->> 'last_customer_message_at', '')) <> '' then (p_payload ->> 'last_customer_message_at')::timestamptz
                when coalesce((p_payload ->> 'touch_last_customer_message')::boolean, false) then timezone('utc', now())
                else existing_conversation.last_customer_message_at
            end,
            last_message_preview = coalesce(nullif(trim(coalesce(p_payload ->> 'last_message_preview', '')), ''), existing_conversation.last_message_preview),
            unread_count = case
                when p_payload ? 'increment_unread' then greatest(0, existing_conversation.unread_count + coalesce((p_payload ->> 'increment_unread')::int, 0))
                when p_payload ? 'unread_count' then greatest(0, coalesce((p_payload ->> 'unread_count')::int, 0))
                else existing_conversation.unread_count
            end,
            unknown_attempts = case
                when p_payload ? 'unknown_attempts' then greatest(0, coalesce((p_payload ->> 'unknown_attempts')::int, 0))
                else existing_conversation.unknown_attempts
            end,
            assigned_user_id = coalesce(nullif(trim(coalesce(p_payload ->> 'assigned_user_id', '')), '')::uuid, existing_conversation.assigned_user_id),
            bot_context = case
                when jsonb_typeof(p_payload -> 'bot_context') = 'object' then p_payload -> 'bot_context'
                else existing_conversation.bot_context
            end
        where id = existing_conversation.id
        returning id into resolved_conversation_id;
    end if;

    return public.whatsapp_bot_conversation_as_json(resolved_conversation_id);
end;
$$;

create or replace function public.patch_public_whatsapp_conversation(
    p_public_key text,
    p_payload jsonb default '{}'::jsonb
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    resolved_bot_id uuid;
    resolved_conversation_id uuid;
begin
    select id
    into resolved_bot_id
    from public.whatsapp_bots
    where public_key = trim(coalesce(p_public_key, ''))
      and deleted_at is null
    limit 1;

    if resolved_bot_id is null then
        raise exception 'No encontramos el bot publico solicitado.';
    end if;

    resolved_conversation_id := nullif(trim(coalesce(p_payload ->> 'conversation_id', '')), '')::uuid;

    if resolved_conversation_id is null then
        raise exception 'No encontramos la conversacion a actualizar.';
    end if;

    update public.whatsapp_bot_conversations
    set customer_name = coalesce(nullif(trim(coalesce(p_payload ->> 'customer_name', '')), ''), customer_name),
        customer_email = coalesce(trim(coalesce(p_payload ->> 'customer_email', '')), customer_email),
        customer_company = coalesce(trim(coalesce(p_payload ->> 'customer_company', '')), customer_company),
        source_label = coalesce(nullif(trim(coalesce(p_payload ->> 'source_label', '')), ''), source_label),
        source_reference = coalesce(trim(coalesce(p_payload ->> 'source_reference', '')), source_reference),
        conversation_state = coalesce(nullif(trim(coalesce(p_payload ->> 'conversation_state', '')), ''), conversation_state),
        inbox_status = coalesce(nullif(trim(coalesce(p_payload ->> 'inbox_status', '')), ''), inbox_status),
        session_expires_at = case
            when trim(coalesce(p_payload ->> 'session_expires_at', '')) <> '' then (p_payload ->> 'session_expires_at')::timestamptz
            when p_payload ? 'session_expires_at' then null
            else session_expires_at
        end,
        last_customer_message_at = case
            when trim(coalesce(p_payload ->> 'last_customer_message_at', '')) <> '' then (p_payload ->> 'last_customer_message_at')::timestamptz
            else last_customer_message_at
        end,
        last_message_preview = coalesce(nullif(trim(coalesce(p_payload ->> 'last_message_preview', '')), ''), last_message_preview),
        unread_count = case
            when p_payload ? 'unread_count' then greatest(0, coalesce((p_payload ->> 'unread_count')::int, 0))
            else unread_count
        end,
        unknown_attempts = case
            when p_payload ? 'unknown_attempts' then greatest(0, coalesce((p_payload ->> 'unknown_attempts')::int, 0))
            else unknown_attempts
        end,
        assigned_user_id = case
            when p_payload ? 'assigned_user_id' then nullif(trim(coalesce(p_payload ->> 'assigned_user_id', '')), '')::uuid
            else assigned_user_id
        end,
        bot_context = case
            when jsonb_typeof(p_payload -> 'bot_context') = 'object' then p_payload -> 'bot_context'
            else bot_context
        end
    where id = resolved_conversation_id
      and bot_id = resolved_bot_id;

    return public.whatsapp_bot_conversation_as_json(resolved_conversation_id);
end;
$$;

create or replace function public.append_public_whatsapp_message(
    p_public_key text,
    p_payload jsonb default '{}'::jsonb
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    resolved_bot public.whatsapp_bots%rowtype;
    resolved_conversation_id uuid;
    created_message_id uuid;
begin
    select *
    into resolved_bot
    from public.whatsapp_bots
    where public_key = trim(coalesce(p_public_key, ''))
      and deleted_at is null
    limit 1;

    if resolved_bot.id is null then
        raise exception 'No encontramos el bot publico solicitado.';
    end if;

    resolved_conversation_id := nullif(trim(coalesce(p_payload ->> 'conversation_id', '')), '')::uuid;

    if resolved_conversation_id is null then
        raise exception 'No encontramos la conversacion del mensaje.';
    end if;

    insert into public.whatsapp_bot_messages (
        conversation_id,
        bot_id,
        project_id,
        direction,
        author_type,
        message_type,
        body,
        attachment_url,
        attachment_storage_path,
        attachment_mime,
        payload,
        delivery_status,
        external_message_id
    )
    values (
        resolved_conversation_id,
        resolved_bot.id,
        resolved_bot.project_id,
        coalesce(nullif(trim(coalesce(p_payload ->> 'direction', '')), ''), 'incoming'),
        coalesce(nullif(trim(coalesce(p_payload ->> 'author_type', '')), ''), 'customer'),
        coalesce(nullif(trim(coalesce(p_payload ->> 'message_type', '')), ''), 'text'),
        trim(coalesce(p_payload ->> 'body', '')),
        trim(coalesce(p_payload ->> 'attachment_url', '')),
        trim(coalesce(p_payload ->> 'attachment_storage_path', '')),
        trim(coalesce(p_payload ->> 'attachment_mime', '')),
        case
            when jsonb_typeof(p_payload -> 'payload') in ('object', 'array') then p_payload -> 'payload'
            else '{}'::jsonb
        end,
        coalesce(nullif(trim(coalesce(p_payload ->> 'delivery_status', '')), ''), 'received'),
        trim(coalesce(p_payload ->> 'external_message_id', ''))
    )
    returning id into created_message_id;

    update public.whatsapp_bot_conversations
    set last_message_preview = coalesce(nullif(trim(coalesce(p_payload ->> 'body', '')), ''), last_message_preview),
        updated_at = timezone('utc', now())
    where id = resolved_conversation_id;

    return public.whatsapp_bot_message_as_json(created_message_id);
end;
$$;

create or replace function public.update_public_whatsapp_message_status(
    p_public_key text,
    p_external_message_id text,
    p_delivery_status text,
    p_payload jsonb default '{}'::jsonb
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    resolved_bot_id uuid;
    resolved_message_id uuid;
begin
    select id
    into resolved_bot_id
    from public.whatsapp_bots
    where public_key = trim(coalesce(p_public_key, ''))
      and deleted_at is null
    limit 1;

    if resolved_bot_id is null then
        raise exception 'No encontramos el bot publico solicitado.';
    end if;

    update public.whatsapp_bot_messages
    set delivery_status = coalesce(nullif(trim(coalesce(p_delivery_status, '')), ''), delivery_status),
        payload = case
            when jsonb_typeof(p_payload) in ('object', 'array') then payload || p_payload
            else payload
        end
    where bot_id = resolved_bot_id
      and external_message_id = trim(coalesce(p_external_message_id, ''))
    returning id into resolved_message_id;

    return public.whatsapp_bot_message_as_json(resolved_message_id);
end;
$$;

create or replace function public.create_project_customer_pipeline_lead(
    p_project_id uuid,
    p_payload jsonb default '{}'::jsonb
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    resolved_stage_id uuid;
    next_sort_order numeric(20, 10);
    created_row public.customer_pipeline_leads%rowtype;
    normalized_name text;
begin
    normalized_name := trim(coalesce(p_payload ->> 'full_name', p_payload ->> 'name', ''));

    if normalized_name = '' then
        raise exception 'El lead necesita al menos un nombre.';
    end if;

    perform public.seed_customer_pipeline_defaults(p_project_id);

    select id
    into resolved_stage_id
    from public.customer_pipeline_stages
    where project_id = p_project_id
      and key = 'nuevo-lead'
    order by sort_order asc
    limit 1;

    if resolved_stage_id is null then
        raise exception 'No fue posible resolver la columna inicial del pipeline.';
    end if;

    select coalesce(min(sort_order), 0) - 1024
    into next_sort_order
    from public.customer_pipeline_leads
    where project_id = p_project_id
      and stage_id = resolved_stage_id
      and deleted_at is null;

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
        p_project_id,
        resolved_stage_id,
        normalized_name,
        trim(coalesce(p_payload ->> 'email', '')),
        regexp_replace(trim(coalesce(p_payload ->> 'phone', p_payload ->> 'whatsapp', '')), '[^0-9]+', '', 'g'),
        trim(coalesce(p_payload ->> 'company_name', p_payload ->> 'company', '')),
        trim(coalesce(p_payload ->> 'source_label', 'WhatsApp Bot')),
        trim(coalesce(p_payload ->> 'source_type', 'whatsapp_bot')),
        trim(coalesce(p_payload ->> 'source_reference', '')),
        upper(trim(coalesce(p_payload ->> 'currency_code', 'MXN'))),
        case
            when trim(coalesce(p_payload ->> 'estimated_value', '')) ~ '^-?[0-9]+(\.[0-9]+)?$' then (p_payload ->> 'estimated_value')::numeric(12, 2)
            else 0
        end,
        trim(coalesce(p_payload ->> 'notes', '')),
        trim(coalesce(p_payload ->> 'lost_reason', '')),
        case
            when jsonb_typeof(p_payload -> 'tags') = 'array' then p_payload -> 'tags'
            else '[]'::jsonb
        end,
        case
            when jsonb_typeof(p_payload -> 'metadata') = 'object' then p_payload -> 'metadata'
            else '{}'::jsonb
        end,
        next_sort_order
    )
    returning * into created_row;

    return to_jsonb(created_row);
end;
$$;

alter table public.whatsapp_bots enable row level security;
alter table public.whatsapp_bot_templates enable row level security;
alter table public.whatsapp_bot_conversations enable row level security;
alter table public.whatsapp_bot_messages enable row level security;
alter table public.whatsapp_bot_events enable row level security;

drop policy if exists "Users can view WhatsApp bots" on public.whatsapp_bots;
create policy "Users can view WhatsApp bots"
on public.whatsapp_bots
for select
to authenticated
using (deleted_at is null and public.can_access_project(project_id));

drop policy if exists "Managers can manage WhatsApp bots" on public.whatsapp_bots;
create policy "Managers can manage WhatsApp bots"
on public.whatsapp_bots
for all
to authenticated
using (deleted_at is null and public.can_manage_project(project_id))
with check (public.can_manage_project(project_id));

drop policy if exists "Users can view WhatsApp templates" on public.whatsapp_bot_templates;
create policy "Users can view WhatsApp templates"
on public.whatsapp_bot_templates
for select
to authenticated
using (deleted_at is null and public.can_access_project(project_id));

drop policy if exists "Managers can manage WhatsApp templates" on public.whatsapp_bot_templates;
create policy "Managers can manage WhatsApp templates"
on public.whatsapp_bot_templates
for all
to authenticated
using (deleted_at is null and public.can_manage_project(project_id))
with check (public.can_manage_project(project_id));

drop policy if exists "Users can view WhatsApp conversations" on public.whatsapp_bot_conversations;
create policy "Users can view WhatsApp conversations"
on public.whatsapp_bot_conversations
for select
to authenticated
using (archived_at is null and public.can_access_project(project_id));

drop policy if exists "Users can insert WhatsApp conversations" on public.whatsapp_bot_conversations;
create policy "Users can insert WhatsApp conversations"
on public.whatsapp_bot_conversations
for insert
to authenticated
with check (public.can_access_project(project_id));

drop policy if exists "Users can update WhatsApp conversations" on public.whatsapp_bot_conversations;
create policy "Users can update WhatsApp conversations"
on public.whatsapp_bot_conversations
for update
to authenticated
using (archived_at is null and public.can_access_project(project_id))
with check (public.can_access_project(project_id));

drop policy if exists "Managers can delete WhatsApp conversations" on public.whatsapp_bot_conversations;
create policy "Managers can delete WhatsApp conversations"
on public.whatsapp_bot_conversations
for delete
to authenticated
using (public.can_manage_project(project_id));

drop policy if exists "Users can view WhatsApp messages" on public.whatsapp_bot_messages;
create policy "Users can view WhatsApp messages"
on public.whatsapp_bot_messages
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Users can insert WhatsApp messages" on public.whatsapp_bot_messages;
create policy "Users can insert WhatsApp messages"
on public.whatsapp_bot_messages
for insert
to authenticated
with check (public.can_access_project(project_id));

drop policy if exists "Users can update WhatsApp messages" on public.whatsapp_bot_messages;
create policy "Users can update WhatsApp messages"
on public.whatsapp_bot_messages
for update
to authenticated
using (public.can_access_project(project_id))
with check (public.can_access_project(project_id));

drop policy if exists "Users can view WhatsApp events" on public.whatsapp_bot_events;
create policy "Users can view WhatsApp events"
on public.whatsapp_bot_events
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Users can insert WhatsApp events" on public.whatsapp_bot_events;
create policy "Users can insert WhatsApp events"
on public.whatsapp_bot_events
for insert
to authenticated
with check (public.can_access_project(project_id));

grant select, insert, update, delete on public.whatsapp_bots to authenticated;
grant select, insert, update, delete on public.whatsapp_bot_templates to authenticated;
grant select, insert, update, delete on public.whatsapp_bot_conversations to authenticated;
grant select, insert, update on public.whatsapp_bot_messages to authenticated;
grant select, insert on public.whatsapp_bot_events to authenticated;
grant execute on function public.get_public_whatsapp_bot_context(text) to anon, authenticated;
grant execute on function public.get_public_whatsapp_conversation_state(text, text) to anon, authenticated;
grant execute on function public.upsert_public_whatsapp_conversation(text, jsonb) to anon, authenticated;
grant execute on function public.patch_public_whatsapp_conversation(text, jsonb) to anon, authenticated;
grant execute on function public.append_public_whatsapp_message(text, jsonb) to anon, authenticated;
grant execute on function public.update_public_whatsapp_message_status(text, text, text, jsonb) to anon, authenticated;
grant execute on function public.create_project_customer_pipeline_lead(uuid, jsonb) to anon, authenticated;
