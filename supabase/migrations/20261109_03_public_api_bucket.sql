-- Public read-only API: monthly summary JSON (no patient-level rows) in a public bucket.
-- Anyone can GET  <SB_URL>/storage/v1/object/public/rxmuk-public/<file>
-- Only approved accounts with role 'province' can publish. Safe to run any time.

insert into storage.buckets (id, name, public)
values ('rxmuk-public', 'rxmuk-public', true)
on conflict (id) do update set public = true;

drop policy if exists rxmuk_public_select on storage.objects;
create policy rxmuk_public_select on storage.objects
  for select to authenticated using (bucket_id = 'rxmuk-public' and public.rxmuk_role() = 'province');

drop policy if exists rxmuk_public_insert on storage.objects;
create policy rxmuk_public_insert on storage.objects
  for insert to authenticated with check (bucket_id = 'rxmuk-public' and public.rxmuk_role() = 'province');

drop policy if exists rxmuk_public_update on storage.objects;
create policy rxmuk_public_update on storage.objects
  for update to authenticated
  using (bucket_id = 'rxmuk-public' and public.rxmuk_role() = 'province')
  with check (bucket_id = 'rxmuk-public' and public.rxmuk_role() = 'province');

drop policy if exists rxmuk_public_delete on storage.objects;
create policy rxmuk_public_delete on storage.objects
  for delete to authenticated using (bucket_id = 'rxmuk-public' and public.rxmuk_role() = 'province');
