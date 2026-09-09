create extension if not exists pgcrypto;

-- Ejecuta supabase/projects_schema.sql antes de este archivo.

create table if not exists public.account_app_workspaces (
    id uuid primary key default gen_random_uuid(),
    user_id uuid not null references auth.users (id) on delete cascade,
    app_key text not null,
    project_id uuid not null references public.projects (id) on delete cascade,
    last_used_at timestamptz not null default timezone('utc', now()),
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    constraint account_app_workspaces_user_app_unique unique (user_id, app_key)
);

create table if not exists public.billing_customers (
    id uuid primary key default gen_random_uuid(),
    user_id uuid references auth.users (id) on delete set null,
    email text not null default '',
    provider text not null default 'stripe',
    provider_customer_id text not null,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    constraint billing_customers_provider_customer_unique unique (provider, provider_customer_id)
);

create table if not exists public.billing_subscriptions (
    id uuid primary key default gen_random_uuid(),
    user_id uuid references auth.users (id) on delete set null,
    provider text not null default 'stripe',
    provider_customer_id text not null default '',
    provider_subscription_id text not null,
    plan_key text not null default 'ecosistema_asx',
    status text not null default 'inactive'
        check (status in ('active', 'trialing', 'past_due', 'unpaid', 'canceled', 'incomplete_expired', 'inactive', 'incomplete')),
    current_period_end timestamptz,
    cancel_at_period_end boolean not null default false,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    constraint billing_subscriptions_provider_subscription_unique unique (provider, provider_subscription_id)
);

create table if not exists public.billing_events (
    id uuid primary key default gen_random_uuid(),
    provider text not null default 'stripe',
    provider_event_id text not null,
    event_type text not null default '',
    payload jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    constraint billing_events_provider_event_unique unique (provider, provider_event_id)
);

alter table public.account_app_workspaces
    add column if not exists user_id uuid,
    add column if not exists app_key text,
    add column if not exists project_id uuid,
    add column if not exists last_used_at timestamptz not null default timezone('utc', now()),
    add column if not exists created_at timestamptz not null default timezone('utc', now()),
    add column if not exists updated_at timestamptz not null default timezone('utc', now());

alter table public.billing_customers
    add column if not exists user_id uuid,
    add column if not exists email text not null default '',
    add column if not exists provider text not null default 'stripe',
    add column if not exists provider_customer_id text,
    add column if not exists metadata jsonb not null default '{}'::jsonb,
    add column if not exists created_at timestamptz not null default timezone('utc', now()),
    add column if not exists updated_at timestamptz not null default timezone('utc', now());

alter table public.billing_subscriptions
    add column if not exists user_id uuid,
    add column if not exists provider text not null default 'stripe',
    add column if not exists provider_customer_id text not null default '',
    add column if not exists provider_subscription_id text,
    add column if not exists plan_key text not null default 'ecosistema_asx',
    add column if not exists status text not null default 'inactive',
    add column if not exists current_period_end timestamptz,
    add column if not exists cancel_at_period_end boolean not null default false,
    add column if not exists metadata jsonb not null default '{}'::jsonb,
    add column if not exists created_at timestamptz not null default timezone('utc', now()),
    add column if not exists updated_at timestamptz not null default timezone('utc', now());

alter table public.billing_events
    add column if not exists provider text not null default 'stripe',
    add column if not exists provider_event_id text,
    add column if not exists event_type text not null default '',
    add column if not exists payload jsonb not null default '{}'::jsonb,
    add column if not exists created_at timestamptz not null default timezone('utc', now());

create unique index if not exists account_app_workspaces_user_app_unique
on public.account_app_workspaces (user_id, app_key);

create index if not exists account_app_workspaces_user_last_used_idx
on public.account_app_workspaces (user_id, last_used_at desc);

create index if not exists account_app_workspaces_project_idx
on public.account_app_workspaces (project_id);

create unique index if not exists billing_customers_provider_customer_unique
on public.billing_customers (provider, provider_customer_id);

create unique index if not exists billing_customers_user_provider_unique
on public.billing_customers (user_id, provider)
where user_id is not null;

create unique index if not exists billing_subscriptions_provider_subscription_unique
on public.billing_subscriptions (provider, provider_subscription_id);

create index if not exists billing_subscriptions_user_updated_idx
on public.billing_subscriptions (user_id, updated_at desc);

create index if not exists billing_subscriptions_customer_idx
on public.billing_subscriptions (provider_customer_id, updated_at desc);

create unique index if not exists billing_events_provider_event_unique
on public.billing_events (provider, provider_event_id);

create or replace function public.set_account_billing_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_account_app_workspaces_updated_at on public.account_app_workspaces;
create trigger trg_account_app_workspaces_updated_at
before update on public.account_app_workspaces
for each row
execute function public.set_account_billing_updated_at();

drop trigger if exists trg_billing_customers_updated_at on public.billing_customers;
create trigger trg_billing_customers_updated_at
before update on public.billing_customers
for each row
execute function public.set_account_billing_updated_at();

drop trigger if exists trg_billing_subscriptions_updated_at on public.billing_subscriptions;
create trigger trg_billing_subscriptions_updated_at
before update on public.billing_subscriptions
for each row
execute function public.set_account_billing_updated_at();

alter table public.account_app_workspaces enable row level security;
alter table public.billing_customers enable row level security;
alter table public.billing_subscriptions enable row level security;
alter table public.billing_events enable row level security;

drop policy if exists "Users can read own app workspaces" on public.account_app_workspaces;
create policy "Users can read own app workspaces"
on public.account_app_workspaces
for select
to authenticated
using (
    user_id = auth.uid()
    and public.can_access_project(project_id)
);

drop policy if exists "Users can insert own app workspaces" on public.account_app_workspaces;
create policy "Users can insert own app workspaces"
on public.account_app_workspaces
for insert
to authenticated
with check (
    user_id = auth.uid()
    and public.can_access_project(project_id)
);

drop policy if exists "Users can update own app workspaces" on public.account_app_workspaces;
create policy "Users can update own app workspaces"
on public.account_app_workspaces
for update
to authenticated
using (
    user_id = auth.uid()
    and public.can_access_project(project_id)
)
with check (
    user_id = auth.uid()
    and public.can_access_project(project_id)
);

drop policy if exists "Users can read own billing customers" on public.billing_customers;
create policy "Users can read own billing customers"
on public.billing_customers
for select
to authenticated
using (user_id = auth.uid());

drop policy if exists "Users can read own billing subscriptions" on public.billing_subscriptions;
create policy "Users can read own billing subscriptions"
on public.billing_subscriptions
for select
to authenticated
using (user_id = auth.uid());

grant select, insert, update on public.account_app_workspaces to authenticated;
grant select on public.billing_customers to authenticated;
grant select on public.billing_subscriptions to authenticated;
grant insert, update on public.account_app_workspaces to service_role;
grant select, insert, update on public.billing_customers to service_role;
grant select, insert, update on public.billing_subscriptions to service_role;
grant insert on public.billing_events to service_role;
