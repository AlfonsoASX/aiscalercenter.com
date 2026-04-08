-- Bucket central para todos los archivos de usuarios.
-- Estructura recomendada de rutas:
-- {auth.uid()}/{scope}/...
-- Ejemplos:
-- {user_id}/blog/{slug}/cover/{file}
-- {user_id}/courses/{slug}/cover/{file}
-- {user_id}/execute/{file}
-- {user_id}/landing-pages/{yyyy}/{mm}/{file}

insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values (
    'user-files',
    'user-files',
    true,
    262144000,
    array[
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
        'image/avif',
        'video/mp4',
        'video/quicktime',
        'video/webm',
        'video/x-m4v',
        'audio/mpeg',
        'audio/mp4',
        'audio/wav',
        'audio/x-wav',
        'audio/ogg',
        'application/pdf'
    ]
)
on conflict (id) do update
set public = excluded.public,
    file_size_limit = excluded.file_size_limit,
    allowed_mime_types = excluded.allowed_mime_types;

drop policy if exists "Public can view user files" on storage.objects;
create policy "Public can view user files"
on storage.objects
for select
to public
using (bucket_id = 'user-files');

drop policy if exists "Users can upload own user files" on storage.objects;
create policy "Users can upload own user files"
on storage.objects
for insert
to authenticated
with check (
    bucket_id = 'user-files'
    and (storage.foldername(name))[1] = auth.uid()::text
);

drop policy if exists "Users can update own user files" on storage.objects;
create policy "Users can update own user files"
on storage.objects
for update
to authenticated
using (
    bucket_id = 'user-files'
    and (storage.foldername(name))[1] = auth.uid()::text
)
with check (
    bucket_id = 'user-files'
    and (storage.foldername(name))[1] = auth.uid()::text
);

drop policy if exists "Users can delete own user files" on storage.objects;
create policy "Users can delete own user files"
on storage.objects
for delete
to authenticated
using (
    bucket_id = 'user-files'
    and (storage.foldername(name))[1] = auth.uid()::text
);
