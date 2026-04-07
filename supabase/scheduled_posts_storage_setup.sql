insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values (
    'scheduled-post-assets',
    'scheduled-post-assets',
    false,
    262144000,
    array[
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
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

drop policy if exists "Users can view own scheduled post assets" on storage.objects;
create policy "Users can view own scheduled post assets"
on storage.objects
for select
to authenticated
using (
    bucket_id = 'scheduled-post-assets'
    and (storage.foldername(name))[1] = auth.uid()::text
);

drop policy if exists "Users can upload own scheduled post assets" on storage.objects;
create policy "Users can upload own scheduled post assets"
on storage.objects
for insert
to authenticated
with check (
    bucket_id = 'scheduled-post-assets'
    and (storage.foldername(name))[1] = auth.uid()::text
);

drop policy if exists "Users can update own scheduled post assets" on storage.objects;
create policy "Users can update own scheduled post assets"
on storage.objects
for update
to authenticated
using (
    bucket_id = 'scheduled-post-assets'
    and (storage.foldername(name))[1] = auth.uid()::text
)
with check (
    bucket_id = 'scheduled-post-assets'
    and (storage.foldername(name))[1] = auth.uid()::text
);

drop policy if exists "Users can delete own scheduled post assets" on storage.objects;
create policy "Users can delete own scheduled post assets"
on storage.objects
for delete
to authenticated
using (
    bucket_id = 'scheduled-post-assets'
    and (storage.foldername(name))[1] = auth.uid()::text
);
