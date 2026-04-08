create extension if not exists pgcrypto;

-- Nota: la ruta privada de cada herramienta se guarda en PHP
-- dentro de storage/tool_launch_configs.php para no exponer carpetas
-- a usuarios autenticados a traves del REST de Supabase.

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

create table if not exists public.tool_categories (
    key text primary key,
    label text not null,
    description text not null default '',
    sort_order integer not null default 0,
    is_active boolean not null default true,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.tools (
    id uuid primary key default gen_random_uuid(),
    category_key text not null references public.tool_categories (key) on delete restrict,
    slug text not null unique,
    title text not null,
    description text not null default '',
    image_url text not null default '',
    tutorial_youtube_url text not null default '',
    sort_order integer not null default 0,
    is_active boolean not null default true,
    admin_only boolean not null default false,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

alter table public.tools drop column if exists launch_mode;
alter table public.tools drop column if exists panel_module_key;
alter table public.tools drop column if exists app_folder;
alter table public.tools drop column if exists entry_file;
alter table public.tools add column if not exists image_url text not null default '';

create index if not exists tools_category_order_idx on public.tools (category_key, sort_order, title);
create index if not exists tools_slug_idx on public.tools (slug);

create or replace function public.set_tool_categories_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_tool_categories_updated_at on public.tool_categories;
create trigger trg_tool_categories_updated_at
before update on public.tool_categories
for each row
execute function public.set_tool_categories_updated_at();

create or replace function public.set_tools_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists trg_tools_updated_at on public.tools;
create trigger trg_tools_updated_at
before update on public.tools
for each row
execute function public.set_tools_updated_at();

insert into public.tool_categories (key, label, description, sort_order, is_active)
values
    ('investigar', 'Investigar', 'Herramientas para validar mercado, recopilar senales y analizar oportunidades.', 10, true),
    ('disenar', 'Diseñar', 'Herramientas para estructurar propuestas, ofertas, piezas y activos visuales.', 20, true),
    ('ejecutar', 'Ejecutar', 'Herramientas para programar, lanzar y operar activos digitales o procesos.', 30, true),
    ('analizar', 'Analizar', 'Herramientas para medir resultados, interpretar datos y detectar oportunidades.', 40, true)
on conflict (key) do update
set label = excluded.label,
    description = excluded.description,
    sort_order = excluded.sort_order,
    is_active = excluded.is_active;

insert into public.tools (
    slug,
    category_key,
    title,
    description,
    image_url,
    tutorial_youtube_url,
    sort_order,
    is_active,
    admin_only
)
values
    (
        'investigar-google',
        'investigar',
        'Google',
        'Consulta terminos relacionados y senales de busqueda desde Google.',
        '',
        '',
        10,
        true,
        false
    ),
    (
        'investigar-youtube',
        'investigar',
        'YouTube',
        'Consulta senales y terminos relacionados desde YouTube.',
        '',
        '',
        20,
        true,
        false
    ),
    (
        'investigar-mercado-libre',
        'investigar',
        'Mercado Libre',
        'Consulta senales de demanda y terminos relacionados desde Mercado Libre.',
        '',
        '',
        30,
        true,
        false
    ),
    (
        'investigar-amazon',
        'investigar',
        'Amazon',
        'Consulta senales y terminos relacionados desde Amazon.',
        '',
        '',
        40,
        true,
        false
    ),
    (
        'generador-formularios',
        'disenar',
        'Generador de formularios',
        'Crea formularios publicos, compartelos sin login y guarda sus respuestas como JSON.',
        '',
        '',
        10,
        true,
        false
    ),
    (
        'creador-landing-pages',
        'disenar',
        'Creador de landing pages',
        'Construye landing pages visuales con bloques editables, vista en vivo y publicacion sin login.',
        '',
        '',
        20,
        true,
        false
    ),
    (
        'planificar-publicaciones',
        'ejecutar',
        'Planificar publicaciones',
        'Programa contenido por red social, valida campos mínimos y resguarda archivos en Supabase Storage.',
        '',
        '',
        10,
        true,
        false
    ),
    (
        'seguimiento-clientes',
        'ejecutar',
        'Seguimiento de Clientes',
        'Gestiona prospectos con un tablero Kanban, panel lateral y entrada automatica de leads por webhook.',
        '',
        '',
        20,
        true,
        false
    )
on conflict (slug) do update
set category_key = excluded.category_key,
    title = excluded.title,
    description = excluded.description,
    image_url = coalesce(nullif(excluded.image_url, ''), public.tools.image_url),
    tutorial_youtube_url = excluded.tutorial_youtube_url,
    sort_order = excluded.sort_order,
    is_active = excluded.is_active,
    admin_only = excluded.admin_only;

delete from public.tools
where slug = 'validar-mercado';

alter table public.tool_categories enable row level security;
alter table public.tools enable row level security;

drop policy if exists "Authenticated users can view active tool categories" on public.tool_categories;
create policy "Authenticated users can view active tool categories"
on public.tool_categories
for select
to authenticated
using (is_active or public.is_admin_user());

drop policy if exists "Admins can manage tool categories" on public.tool_categories;
create policy "Admins can manage tool categories"
on public.tool_categories
for all
to authenticated
using (public.is_admin_user())
with check (public.is_admin_user());

drop policy if exists "Authenticated users can view tools" on public.tools;
create policy "Authenticated users can view tools"
on public.tools
for select
to authenticated
using (
    (is_active and not admin_only)
    or public.is_admin_user()
);

drop policy if exists "Admins can manage tools" on public.tools;
create policy "Admins can manage tools"
on public.tools
for all
to authenticated
using (public.is_admin_user())
with check (public.is_admin_user());

grant select on public.tool_categories to authenticated;
grant select on public.tools to authenticated;
grant insert, update, delete on public.tool_categories to authenticated;
grant insert, update, delete on public.tools to authenticated;
