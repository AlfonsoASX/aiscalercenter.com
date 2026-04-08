create extension if not exists pgcrypto;

-- Ejecuta supabase/projects_schema.sql antes de este archivo.

create table if not exists public.analytics_traffic_sources (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    source_key text not null,
    source_label text not null default '',
    channel text not null default 'social',
    metric_date date not null default current_date,
    clicks integer not null default 0,
    sessions integer not null default 0,
    bounces integer not null default 0,
    conversions integer not null default 0,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    constraint analytics_traffic_sources_project_key_date_unique unique (project_id, source_key, metric_date)
);

create table if not exists public.analytics_cpl_snapshots (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    source_key text not null default '',
    source_label text not null default '',
    snapshot_date date not null default current_date,
    spend_mxn numeric(12, 2) not null default 0,
    lead_count integer not null default 0,
    whatsapp_click_count integer not null default 0,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now()),
    constraint analytics_cpl_snapshots_project_key_date_unique unique (project_id, source_key, snapshot_date)
);

create table if not exists public.analytics_heatmap_pages (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    landing_page_id uuid references public.landing_pages (id) on delete cascade,
    page_path text not null default '',
    page_title text not null default '',
    snapshot_date date not null default current_date,
    total_views integer not null default 0,
    avg_scroll_depth numeric(5, 2) not null default 0,
    top_click_zones jsonb not null default '[]'::jsonb,
    session_recordings jsonb not null default '[]'::jsonb,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.analytics_utm_registry (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    scheduled_post_id uuid references public.scheduled_posts (id) on delete set null,
    scheduled_target_id uuid references public.scheduled_post_targets (id) on delete set null,
    provider_key text not null default '',
    source_label text not null default '',
    campaign_name text not null default '',
    content_name text not null default '',
    destination_url text not null default '',
    tracked_url text not null default '',
    utm_source text not null default '',
    utm_medium text not null default '',
    utm_campaign text not null default '',
    utm_content text not null default '',
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.analytics_campaign_alerts (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    severity text not null default 'info' check (severity in ('info', 'warning', 'critical')),
    status text not null default 'open' check (status in ('open', 'dismissed', 'resolved')),
    source_key text not null default '',
    alert_type text not null default '',
    title text not null default '',
    message text not null default '',
    related_url text not null default '',
    metadata jsonb not null default '{}'::jsonb,
    detected_at timestamptz not null default timezone('utc', now()),
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create index if not exists analytics_traffic_sources_project_date_idx
on public.analytics_traffic_sources (project_id, metric_date desc);

create index if not exists analytics_cpl_snapshots_project_date_idx
on public.analytics_cpl_snapshots (project_id, snapshot_date desc);

create index if not exists analytics_heatmap_pages_project_date_idx
on public.analytics_heatmap_pages (project_id, snapshot_date desc);

create index if not exists analytics_utm_registry_project_created_idx
on public.analytics_utm_registry (project_id, created_at desc);

create index if not exists analytics_campaign_alerts_project_detected_idx
on public.analytics_campaign_alerts (project_id, detected_at desc);

create or replace function public.set_analytics_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_analytics_traffic_sources_updated_at on public.analytics_traffic_sources;
create trigger trg_analytics_traffic_sources_updated_at
before update on public.analytics_traffic_sources
for each row
execute function public.set_analytics_updated_at();

drop trigger if exists trg_analytics_cpl_snapshots_updated_at on public.analytics_cpl_snapshots;
create trigger trg_analytics_cpl_snapshots_updated_at
before update on public.analytics_cpl_snapshots
for each row
execute function public.set_analytics_updated_at();

drop trigger if exists trg_analytics_heatmap_pages_updated_at on public.analytics_heatmap_pages;
create trigger trg_analytics_heatmap_pages_updated_at
before update on public.analytics_heatmap_pages
for each row
execute function public.set_analytics_updated_at();

drop trigger if exists trg_analytics_utm_registry_updated_at on public.analytics_utm_registry;
create trigger trg_analytics_utm_registry_updated_at
before update on public.analytics_utm_registry
for each row
execute function public.set_analytics_updated_at();

drop trigger if exists trg_analytics_campaign_alerts_updated_at on public.analytics_campaign_alerts;
create trigger trg_analytics_campaign_alerts_updated_at
before update on public.analytics_campaign_alerts
for each row
execute function public.set_analytics_updated_at();

alter table public.analytics_traffic_sources enable row level security;
alter table public.analytics_cpl_snapshots enable row level security;
alter table public.analytics_heatmap_pages enable row level security;
alter table public.analytics_utm_registry enable row level security;
alter table public.analytics_campaign_alerts enable row level security;

drop policy if exists "Project members can view analytics traffic sources" on public.analytics_traffic_sources;
create policy "Project members can view analytics traffic sources"
on public.analytics_traffic_sources
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Project managers can manage analytics traffic sources" on public.analytics_traffic_sources;
create policy "Project managers can manage analytics traffic sources"
on public.analytics_traffic_sources
for all
to authenticated
using (public.can_manage_project(project_id))
with check (public.can_manage_project(project_id));

drop policy if exists "Project members can view analytics cpl snapshots" on public.analytics_cpl_snapshots;
create policy "Project members can view analytics cpl snapshots"
on public.analytics_cpl_snapshots
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Project managers can manage analytics cpl snapshots" on public.analytics_cpl_snapshots;
create policy "Project managers can manage analytics cpl snapshots"
on public.analytics_cpl_snapshots
for all
to authenticated
using (public.can_manage_project(project_id))
with check (public.can_manage_project(project_id));

drop policy if exists "Project members can view analytics heatmap pages" on public.analytics_heatmap_pages;
create policy "Project members can view analytics heatmap pages"
on public.analytics_heatmap_pages
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Project managers can manage analytics heatmap pages" on public.analytics_heatmap_pages;
create policy "Project managers can manage analytics heatmap pages"
on public.analytics_heatmap_pages
for all
to authenticated
using (public.can_manage_project(project_id))
with check (public.can_manage_project(project_id));

drop policy if exists "Project members can view analytics utm registry" on public.analytics_utm_registry;
create policy "Project members can view analytics utm registry"
on public.analytics_utm_registry
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Project managers can manage analytics utm registry" on public.analytics_utm_registry;
create policy "Project managers can manage analytics utm registry"
on public.analytics_utm_registry
for all
to authenticated
using (public.can_manage_project(project_id))
with check (public.can_manage_project(project_id));

drop policy if exists "Project members can view analytics campaign alerts" on public.analytics_campaign_alerts;
create policy "Project members can view analytics campaign alerts"
on public.analytics_campaign_alerts
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Project managers can manage analytics campaign alerts" on public.analytics_campaign_alerts;
create policy "Project managers can manage analytics campaign alerts"
on public.analytics_campaign_alerts
for all
to authenticated
using (public.can_manage_project(project_id))
with check (public.can_manage_project(project_id));

grant select, insert, update, delete on public.analytics_traffic_sources to authenticated;
grant select, insert, update, delete on public.analytics_cpl_snapshots to authenticated;
grant select, insert, update, delete on public.analytics_heatmap_pages to authenticated;
grant select, insert, update, delete on public.analytics_utm_registry to authenticated;
grant select, insert, update, delete on public.analytics_campaign_alerts to authenticated;
