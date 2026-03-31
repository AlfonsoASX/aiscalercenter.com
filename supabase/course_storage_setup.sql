insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values (
    'course-assets',
    'course-assets',
    false,
    262144000,
    array[
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'audio/mpeg',
        'audio/mp4',
        'audio/wav',
        'audio/x-wav',
        'audio/webm',
        'audio/ogg',
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
        'image/avif'
    ]
)
on conflict (id) do update
set public = excluded.public,
    file_size_limit = excluded.file_size_limit,
    allowed_mime_types = excluded.allowed_mime_types;

drop policy if exists "Public can view course assets" on storage.objects;
drop policy if exists "Admins can view course assets" on storage.objects;
drop policy if exists "Authenticated users can view course assets" on storage.objects;
create policy "Authenticated users can view course assets"
on storage.objects
for select
to authenticated
using (bucket_id = 'course-assets');

drop policy if exists "Admins can upload course assets" on storage.objects;
create policy "Admins can upload course assets"
on storage.objects
for insert
to authenticated
with check (
    bucket_id = 'course-assets'
    and (
        coalesce(auth.jwt() ->> 'email', '') = 'a@asx.mx'
        or coalesce(auth.jwt() -> 'app_metadata' ->> 'role', '') = 'admin'
    )
);

drop policy if exists "Admins can update course assets" on storage.objects;
create policy "Admins can update course assets"
on storage.objects
for update
to authenticated
using (
    bucket_id = 'course-assets'
    and (
        coalesce(auth.jwt() ->> 'email', '') = 'a@asx.mx'
        or coalesce(auth.jwt() -> 'app_metadata' ->> 'role', '') = 'admin'
    )
)
with check (
    bucket_id = 'course-assets'
    and (
        coalesce(auth.jwt() ->> 'email', '') = 'a@asx.mx'
        or coalesce(auth.jwt() -> 'app_metadata' ->> 'role', '') = 'admin'
    )
);

drop policy if exists "Admins can delete course assets" on storage.objects;
create policy "Admins can delete course assets"
on storage.objects
for delete
to authenticated
using (
    bucket_id = 'course-assets'
    and (
        coalesce(auth.jwt() ->> 'email', '') = 'a@asx.mx'
        or coalesce(auth.jwt() -> 'app_metadata' ->> 'role', '') = 'admin'
    )
);
