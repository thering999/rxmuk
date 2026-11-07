-- Cutover step 4: lock bucket `rxmuk` to approved accounts.
-- RUN ONLY AFTER every user can sign in with the new client (step 3), or nobody can load data.
-- Rollback: run the "rollback" block at the bottom.

update storage.buckets set public = false where id = 'rxmuk';

-- Back up, then drop, every older storage.objects policy that mentions bucket rxmuk
-- (a leftover anon/public policy would keep the bucket readable).
create table if not exists public.rxmuk_policy_backup as
  select now() as saved_at, policyname, cmd, roles, qual, with_check
  from pg_policies where false;
insert into public.rxmuk_policy_backup
  select now(), policyname, cmd, roles, qual, with_check
  from pg_policies
  where schemaname = 'storage' and tablename = 'objects'
    and (coalesce(qual, '') || coalesce(with_check, '')) like '%rxmuk%'
    and policyname not like 'rxmuk_obj_%';
alter table public.rxmuk_policy_backup enable row level security;

do $$
declare p record;
begin
  for p in
    select policyname from pg_policies
    where schemaname = 'storage' and tablename = 'objects'
      and (coalesce(qual, '') || coalesce(with_check, '')) like '%rxmuk%'
      and policyname not like 'rxmuk_obj_%'
  loop
    execute format('drop policy %I on storage.objects', p.policyname);
  end loop;
end $$;

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
-- then re-create the previous policies from: select * from public.rxmuk_policy_backup;
