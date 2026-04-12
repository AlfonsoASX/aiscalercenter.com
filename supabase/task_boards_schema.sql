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

create table if not exists public.task_boards (
    id uuid primary key default gen_random_uuid(),
    project_id uuid not null references public.projects (id) on delete cascade,
    title text not null,
    description text not null default '',
    sort_order integer not null default 0,
    settings jsonb not null default '{}'::jsonb,
    created_by uuid references auth.users (id) on delete set null,
    created_by_email text not null default '',
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.task_board_columns (
    id uuid primary key default gen_random_uuid(),
    board_id uuid not null references public.task_boards (id) on delete cascade,
    title text not null,
    accent_color text not null default '#1A73E8',
    responsible_member_id uuid references public.project_members (id) on delete set null,
    wip_limit integer check (wip_limit is null or wip_limit >= 0),
    sort_order integer not null default 0,
    is_archived boolean not null default false,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.task_board_labels (
    id uuid primary key default gen_random_uuid(),
    board_id uuid not null references public.task_boards (id) on delete cascade,
    title text not null,
    color text not null default '#2F7CEF',
    sort_order integer not null default 0,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.task_board_cards (
    id uuid primary key default gen_random_uuid(),
    board_id uuid not null references public.task_boards (id) on delete cascade,
    column_id uuid not null references public.task_board_columns (id) on delete restrict,
    title text not null,
    description_markdown text not null default '',
    priority text not null default 'medium' check (priority in ('low', 'medium', 'high', 'urgent')),
    start_date date,
    due_date date,
    assigned_member_ids jsonb not null default '[]'::jsonb,
    label_ids jsonb not null default '[]'::jsonb,
    checklist jsonb not null default '[]'::jsonb,
    metadata jsonb not null default '{}'::jsonb,
    is_archived boolean not null default false,
    sort_order numeric(20, 10) not null default 0,
    created_by uuid references auth.users (id) on delete set null,
    created_by_email text not null default '',
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.task_board_column_follows (
    id uuid primary key default gen_random_uuid(),
    board_id uuid not null references public.task_boards (id) on delete cascade,
    column_id uuid not null references public.task_board_columns (id) on delete cascade,
    user_id uuid not null references auth.users (id) on delete cascade,
    user_email text not null default '',
    created_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.task_board_column_rules (
    id uuid primary key default gen_random_uuid(),
    board_id uuid not null references public.task_boards (id) on delete cascade,
    column_id uuid not null references public.task_board_columns (id) on delete cascade,
    title text not null default '',
    trigger_type text not null check (trigger_type in ('card_added', 'daily', 'weekly_monday')),
    action_type text not null check (action_type in ('sort_list', 'assign_responsible', 'create_notification')),
    config jsonb not null default '{}'::jsonb,
    is_active boolean not null default true,
    last_run_at timestamptz,
    created_by uuid references auth.users (id) on delete set null,
    created_by_email text not null default '',
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.workspace_notifications (
    id uuid primary key default gen_random_uuid(),
    user_id uuid not null references auth.users (id) on delete cascade,
    project_id uuid references public.projects (id) on delete cascade,
    source_tool_slug text not null default '',
    source_type text not null default '',
    title text not null,
    body text not null default '',
    destination jsonb not null default '{}'::jsonb,
    payload jsonb not null default '{}'::jsonb,
    is_read boolean not null default false,
    read_at timestamptz,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

do $$
begin
    if not exists (
        select 1
        from information_schema.columns
        where table_schema = 'public'
          and table_name = 'task_board_columns'
          and column_name = 'responsible_member_id'
    ) then
        alter table public.task_board_columns
            add column responsible_member_id uuid references public.project_members (id) on delete set null;
    end if;

    if not exists (
        select 1
        from information_schema.columns
        where table_schema = 'public'
          and table_name = 'task_board_cards'
          and column_name = 'is_archived'
    ) then
        alter table public.task_board_cards
            add column is_archived boolean not null default false;
    end if;
end
$$;

create table if not exists public.task_board_comments (
    id uuid primary key default gen_random_uuid(),
    board_id uuid not null references public.task_boards (id) on delete cascade,
    card_id uuid not null references public.task_board_cards (id) on delete cascade,
    body_markdown text not null,
    author_user_id uuid references auth.users (id) on delete set null,
    author_email text not null default '',
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.task_board_activity (
    id uuid primary key default gen_random_uuid(),
    board_id uuid not null references public.task_boards (id) on delete cascade,
    card_id uuid references public.task_board_cards (id) on delete cascade,
    event_type text not null,
    description text not null,
    actor_user_id uuid references auth.users (id) on delete set null,
    actor_email text not null default '',
    payload jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default timezone('utc', now())
);

do $$
begin
    if exists (
        select 1
        from information_schema.columns
        where table_schema = 'public'
          and table_name = 'task_board_cards'
          and column_name = 'swimlane_id'
    ) then
        with ranked as (
            select
                id,
                (((row_number() over (
                    partition by board_id, column_id
                    order by sort_order asc, created_at asc, id asc
                )) - 1) * 1024)::numeric(20, 10) as new_sort_order
            from public.task_board_cards
        )
        update public.task_board_cards cards
        set sort_order = ranked.new_sort_order
        from ranked
        where ranked.id = cards.id;

        alter table public.task_board_cards
            drop constraint if exists task_board_cards_swimlane_id_fkey;

        alter table public.task_board_cards
            drop column if exists swimlane_id;
    end if;

    if exists (
        select 1
        from information_schema.tables
        where table_schema = 'public'
          and table_name = 'task_board_swimlanes'
    ) then
        drop table public.task_board_swimlanes;
    end if;
end
$$;

create index if not exists task_boards_project_sort_idx
on public.task_boards (project_id, sort_order, updated_at desc);

create index if not exists task_board_columns_board_sort_idx
on public.task_board_columns (board_id, sort_order);

create index if not exists task_board_labels_board_sort_idx
on public.task_board_labels (board_id, sort_order);

drop index if exists task_board_cards_board_column_lane_sort_idx;

create index if not exists task_board_cards_board_column_sort_idx
on public.task_board_cards (board_id, column_id, sort_order);

create unique index if not exists task_board_column_follows_column_user_unique
on public.task_board_column_follows (column_id, user_id);

create index if not exists task_board_column_follows_board_user_idx
on public.task_board_column_follows (board_id, user_id);

create index if not exists task_board_column_rules_board_column_idx
on public.task_board_column_rules (board_id, column_id, is_active);

create index if not exists workspace_notifications_user_created_idx
on public.workspace_notifications (user_id, is_read, created_at desc);

create index if not exists task_board_comments_board_card_created_idx
on public.task_board_comments (board_id, card_id, created_at);

create index if not exists task_board_activity_board_created_idx
on public.task_board_activity (board_id, created_at desc);

create or replace function public.set_task_board_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_task_boards_updated_at on public.task_boards;
create trigger trg_task_boards_updated_at
before update on public.task_boards
for each row
execute function public.set_task_board_updated_at();

drop trigger if exists trg_task_board_columns_updated_at on public.task_board_columns;
create trigger trg_task_board_columns_updated_at
before update on public.task_board_columns
for each row
execute function public.set_task_board_updated_at();

drop trigger if exists trg_task_board_labels_updated_at on public.task_board_labels;
create trigger trg_task_board_labels_updated_at
before update on public.task_board_labels
for each row
execute function public.set_task_board_updated_at();

drop trigger if exists trg_task_board_cards_updated_at on public.task_board_cards;
create trigger trg_task_board_cards_updated_at
before update on public.task_board_cards
for each row
execute function public.set_task_board_updated_at();

drop trigger if exists trg_task_board_comments_updated_at on public.task_board_comments;
create trigger trg_task_board_comments_updated_at
before update on public.task_board_comments
for each row
execute function public.set_task_board_updated_at();

drop trigger if exists trg_task_board_column_rules_updated_at on public.task_board_column_rules;
create trigger trg_task_board_column_rules_updated_at
before update on public.task_board_column_rules
for each row
execute function public.set_task_board_updated_at();

drop trigger if exists trg_workspace_notifications_updated_at on public.workspace_notifications;
create trigger trg_workspace_notifications_updated_at
before update on public.workspace_notifications
for each row
execute function public.set_task_board_updated_at();

create or replace function public.can_access_task_board(p_board_id uuid)
returns boolean
language sql
stable
security definer
set search_path = public
as $$
    select exists (
        select 1
        from public.task_boards tb
        where tb.id = p_board_id
          and public.can_access_project(tb.project_id)
    );
$$;

create or replace function public.can_access_task_card(p_card_id uuid)
returns boolean
language sql
stable
security definer
set search_path = public
as $$
    select exists (
        select 1
        from public.task_board_cards tc
        join public.task_boards tb on tb.id = tc.board_id
        where tc.id = p_card_id
          and public.can_access_project(tb.project_id)
    );
$$;

alter table public.task_boards enable row level security;
alter table public.task_board_columns enable row level security;
alter table public.task_board_labels enable row level security;
alter table public.task_board_cards enable row level security;
alter table public.task_board_comments enable row level security;
alter table public.task_board_activity enable row level security;
alter table public.task_board_column_follows enable row level security;
alter table public.task_board_column_rules enable row level security;
alter table public.workspace_notifications enable row level security;

drop policy if exists "Users can view accessible task boards" on public.task_boards;
create policy "Users can view accessible task boards"
on public.task_boards
for select
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Users can create task boards in accessible projects" on public.task_boards;
create policy "Users can create task boards in accessible projects"
on public.task_boards
for insert
to authenticated
with check (public.can_access_project(project_id));

drop policy if exists "Users can update accessible task boards" on public.task_boards;
create policy "Users can update accessible task boards"
on public.task_boards
for update
to authenticated
using (public.can_access_project(project_id))
with check (public.can_access_project(project_id));

drop policy if exists "Users can delete accessible task boards" on public.task_boards;
create policy "Users can delete accessible task boards"
on public.task_boards
for delete
to authenticated
using (public.can_access_project(project_id));

drop policy if exists "Users can view accessible task board columns" on public.task_board_columns;
create policy "Users can view accessible task board columns"
on public.task_board_columns
for select
to authenticated
using (public.can_access_task_board(board_id));

drop policy if exists "Users can manage accessible task board columns" on public.task_board_columns;
create policy "Users can manage accessible task board columns"
on public.task_board_columns
for all
to authenticated
using (public.can_access_task_board(board_id))
with check (public.can_access_task_board(board_id));

drop policy if exists "Users can view accessible task board labels" on public.task_board_labels;
create policy "Users can view accessible task board labels"
on public.task_board_labels
for select
to authenticated
using (public.can_access_task_board(board_id));

drop policy if exists "Users can manage accessible task board labels" on public.task_board_labels;
create policy "Users can manage accessible task board labels"
on public.task_board_labels
for all
to authenticated
using (public.can_access_task_board(board_id))
with check (public.can_access_task_board(board_id));

drop policy if exists "Users can view accessible task cards" on public.task_board_cards;
create policy "Users can view accessible task cards"
on public.task_board_cards
for select
to authenticated
using (public.can_access_task_board(board_id));

drop policy if exists "Users can manage accessible task cards" on public.task_board_cards;
create policy "Users can manage accessible task cards"
on public.task_board_cards
for all
to authenticated
using (public.can_access_task_board(board_id))
with check (public.can_access_task_board(board_id));

drop policy if exists "Users can view accessible task comments" on public.task_board_comments;
create policy "Users can view accessible task comments"
on public.task_board_comments
for select
to authenticated
using (public.can_access_task_board(board_id));

drop policy if exists "Users can manage accessible task comments" on public.task_board_comments;
create policy "Users can manage accessible task comments"
on public.task_board_comments
for all
to authenticated
using (public.can_access_task_board(board_id))
with check (public.can_access_task_board(board_id));

drop policy if exists "Users can view accessible task activity" on public.task_board_activity;
create policy "Users can view accessible task activity"
on public.task_board_activity
for select
to authenticated
using (public.can_access_task_board(board_id));

drop policy if exists "Users can create accessible task activity" on public.task_board_activity;
create policy "Users can create accessible task activity"
on public.task_board_activity
for insert
to authenticated
with check (public.can_access_task_board(board_id));

drop policy if exists "Users can view own task board follows" on public.task_board_column_follows;
create policy "Users can view own task board follows"
on public.task_board_column_follows
for select
to authenticated
using (auth.uid() = user_id and public.can_access_task_board(board_id));

drop policy if exists "Users can create own task board follows" on public.task_board_column_follows;
create policy "Users can create own task board follows"
on public.task_board_column_follows
for insert
to authenticated
with check (auth.uid() = user_id and public.can_access_task_board(board_id));

drop policy if exists "Users can delete own task board follows" on public.task_board_column_follows;
create policy "Users can delete own task board follows"
on public.task_board_column_follows
for delete
to authenticated
using (auth.uid() = user_id and public.can_access_task_board(board_id));

drop policy if exists "Users can view accessible task board rules" on public.task_board_column_rules;
create policy "Users can view accessible task board rules"
on public.task_board_column_rules
for select
to authenticated
using (public.can_access_task_board(board_id));

drop policy if exists "Users can manage accessible task board rules" on public.task_board_column_rules;
create policy "Users can manage accessible task board rules"
on public.task_board_column_rules
for all
to authenticated
using (public.can_access_task_board(board_id))
with check (public.can_access_task_board(board_id));

drop policy if exists "Users can view own workspace notifications" on public.workspace_notifications;
create policy "Users can view own workspace notifications"
on public.workspace_notifications
for select
to authenticated
using (auth.uid() = user_id);

drop policy if exists "Users can update own workspace notifications" on public.workspace_notifications;
create policy "Users can update own workspace notifications"
on public.workspace_notifications
for update
to authenticated
using (auth.uid() = user_id)
with check (auth.uid() = user_id);

drop policy if exists "Users can create project workspace notifications" on public.workspace_notifications;
create policy "Users can create project workspace notifications"
on public.workspace_notifications
for insert
to authenticated
with check ((project_id is null) or public.can_access_project(project_id));

drop policy if exists "Users can delete own workspace notifications" on public.workspace_notifications;
create policy "Users can delete own workspace notifications"
on public.workspace_notifications
for delete
to authenticated
using (auth.uid() = user_id);

grant select, insert, update, delete on public.task_boards to authenticated;
grant select, insert, update, delete on public.task_board_columns to authenticated;
grant select, insert, update, delete on public.task_board_labels to authenticated;
grant select, insert, update, delete on public.task_board_cards to authenticated;
grant select, insert, update, delete on public.task_board_comments to authenticated;
grant select, insert on public.task_board_activity to authenticated;
grant select, insert, delete on public.task_board_column_follows to authenticated;
grant select, insert, update, delete on public.task_board_column_rules to authenticated;
grant select, insert, update, delete on public.workspace_notifications to authenticated;

do $$
begin
    if not exists (
        select 1
        from pg_publication_tables
        where pubname = 'supabase_realtime'
          and schemaname = 'public'
          and tablename = 'task_boards'
    ) then
        execute 'alter publication supabase_realtime add table public.task_boards';
    end if;
end
$$;

do $$
begin
    if not exists (
        select 1
        from pg_publication_tables
        where pubname = 'supabase_realtime'
          and schemaname = 'public'
          and tablename = 'task_board_column_follows'
    ) then
        execute 'alter publication supabase_realtime add table public.task_board_column_follows';
    end if;
end
$$;

do $$
begin
    if not exists (
        select 1
        from pg_publication_tables
        where pubname = 'supabase_realtime'
          and schemaname = 'public'
          and tablename = 'task_board_column_rules'
    ) then
        execute 'alter publication supabase_realtime add table public.task_board_column_rules';
    end if;
end
$$;

do $$
begin
    if not exists (
        select 1
        from pg_publication_tables
        where pubname = 'supabase_realtime'
          and schemaname = 'public'
          and tablename = 'workspace_notifications'
    ) then
        execute 'alter publication supabase_realtime add table public.workspace_notifications';
    end if;
end
$$;

do $$
begin
    if not exists (
        select 1
        from pg_publication_tables
        where pubname = 'supabase_realtime'
          and schemaname = 'public'
          and tablename = 'task_board_columns'
    ) then
        execute 'alter publication supabase_realtime add table public.task_board_columns';
    end if;
end
$$;

do $$
begin
    if not exists (
        select 1
        from pg_publication_tables
        where pubname = 'supabase_realtime'
          and schemaname = 'public'
          and tablename = 'task_board_labels'
    ) then
        execute 'alter publication supabase_realtime add table public.task_board_labels';
    end if;
end
$$;

do $$
begin
    if not exists (
        select 1
        from pg_publication_tables
        where pubname = 'supabase_realtime'
          and schemaname = 'public'
          and tablename = 'task_board_cards'
    ) then
        execute 'alter publication supabase_realtime add table public.task_board_cards';
    end if;
end
$$;

do $$
begin
    if not exists (
        select 1
        from pg_publication_tables
        where pubname = 'supabase_realtime'
          and schemaname = 'public'
          and tablename = 'task_board_comments'
    ) then
        execute 'alter publication supabase_realtime add table public.task_board_comments';
    end if;
end
$$;

do $$
begin
    if not exists (
        select 1
        from pg_publication_tables
        where pubname = 'supabase_realtime'
          and schemaname = 'public'
          and tablename = 'task_board_activity'
    ) then
        execute 'alter publication supabase_realtime add table public.task_board_activity';
    end if;
end
$$;
