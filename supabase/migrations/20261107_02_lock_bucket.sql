-- Cutover step 4: lock bucket `rxmuk` to approved accounts.
-- RUN ONLY AFTER every user can sign in with the new client (step 3), or nobody can load data.
-- Rollback: run the "rollback" block at the bottom.

update storage.buckets set public = false where id = 'rxmuk';

-- Remove any older permissive policies on this bucket before running. List them with:
-- select policyname, cmd, roles, qual from pg_policies where schemaname = 'storage' and tablename = 'objects';

drop policy if exists rxmuk_obj_select on storage.objects;
create policy rxmuk_obj_select on storage.objects
  for select to authenticated
  using (bucket_id = 'rxmuk' and public.rxmuk_is_approved());

drop policy if exists rxmuk_obj_insert on storage.objects;
create policy rxmuk_obj_insert on storage.objects
  for insert to authenticated
  with check (bucket_id = 'rxmuk' and public.rxmuk_role() = 'province');

drop policy if exists rxmuk_obj_update on storage.objects;
create policy rxmuk_obj_update on storage.objects
  for update to authenticated
  using (bucket_id = 'rxmuk' and public.rxmuk_role() = 'province')
  with check (bucket_id = 'rxmuk' and public.rxmuk_role() = 'province');

drop policy if exists rxmuk_obj_delete on storage.objects;
create policy rxmuk_obj_delete on storage.objects
  for delete to authenticated
  using (bucket_id = 'rxmuk' and public.rxmuk_role() = 'province');

-- rollback:
-- drop policy if exists rxmuk_obj_select on storage.objects;
-- drop policy if exists rxmuk_obj_insert on storage.objects;
-- drop policy if exists rxmuk_obj_update on storage.objects;
-- drop policy if exists rxmuk_obj_delete on storage.objects;
-- then re-create the previous policies saved from the pg_policies listing above.
