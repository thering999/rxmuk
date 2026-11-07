-- Cutover step 1: accounts + E1/E3/E5 tables. Safe to run any time: nothing reads these yet.
-- Run in Supabase SQL editor as the project owner.

create table if not exists public.rxmuk_users (
  user_id      uuid primary key references auth.users(id) on delete cascade,
  role         text not null check (role in ('province', 'district', 'unit')),
  amp_code     text,
  hospcode     text,
  display_name text,
  created_at   timestamptz not null default now()
);
alter table public.rxmuk_users enable row level security;

-- security definer so policies can check approval without granting select on rxmuk_users
create or replace function public.rxmuk_is_approved()
returns boolean language sql stable security definer set search_path = public as $$
  select exists (select 1 from public.rxmuk_users where user_id = auth.uid());
$$;

create or replace function public.rxmuk_role()
returns text language sql stable security definer set search_path = public as $$
  select role from public.rxmuk_users where user_id = auth.uid();
$$;

revoke all on function public.rxmuk_is_approved() from public, anon;
revoke all on function public.rxmuk_role() from public, anon;
grant execute on function public.rxmuk_is_approved() to authenticated;
grant execute on function public.rxmuk_role() to authenticated;

drop policy if exists rxmuk_users_self on public.rxmuk_users;
create policy rxmuk_users_self on public.rxmuk_users
  for select to authenticated using (user_id = auth.uid());

-- E3 shared import log
create table if not exists public.rxmuk_import_log (
  id         bigint generated always as identity primary key,
  user_id    uuid not null default auth.uid() references auth.users(id),
  filename   text not null check (length(filename) <= 255),
  row_count  integer not null check (row_count >= 0),
  months     text[] not null default '{}',
  created_at timestamptz not null default now()
);
alter table public.rxmuk_import_log enable row level security;

drop policy if exists rxmuk_import_log_select on public.rxmuk_import_log;
create policy rxmuk_import_log_select on public.rxmuk_import_log
  for select to authenticated using (public.rxmuk_is_approved());

drop policy if exists rxmuk_import_log_insert on public.rxmuk_import_log;
create policy rxmuk_import_log_insert on public.rxmuk_import_log
  for insert to authenticated with check (public.rxmuk_is_approved() and user_id = auth.uid());

-- E1 shared annotations per unit+month
create table if not exists public.rxmuk_annotations (
  id         bigint generated always as identity primary key,
  hospcode   text not null check (length(hospcode) <= 10),
  month      text not null check (month ~ '^\d{4}-\d{2}$'),
  note       text not null check (length(note) between 1 and 2000),
  user_id    uuid not null default auth.uid() references auth.users(id),
  author     text,
  created_at timestamptz not null default now()
);
create index if not exists rxmuk_annotations_hosp_month on public.rxmuk_annotations (hospcode, month);
alter table public.rxmuk_annotations enable row level security;

drop policy if exists rxmuk_annotations_select on public.rxmuk_annotations;
create policy rxmuk_annotations_select on public.rxmuk_annotations
  for select to authenticated using (public.rxmuk_is_approved());

drop policy if exists rxmuk_annotations_insert on public.rxmuk_annotations;
create policy rxmuk_annotations_insert on public.rxmuk_annotations
  for insert to authenticated with check (public.rxmuk_is_approved() and user_id = auth.uid());

drop policy if exists rxmuk_annotations_delete_own on public.rxmuk_annotations;
create policy rxmuk_annotations_delete_own on public.rxmuk_annotations
  for delete to authenticated using (user_id = auth.uid());

-- E5 share snapshots: aggregated data only, sign-in required to read, expire after 7 days
create table if not exists public.rxmuk_snapshots (
  id         uuid primary key default gen_random_uuid(),
  title      text check (length(title) <= 200),
  data       jsonb not null check (pg_column_size(data) <= 1048576),
  created_by uuid not null default auth.uid() references auth.users(id),
  created_at timestamptz not null default now(),
  expires_at timestamptz not null default now() + interval '7 days'
);
alter table public.rxmuk_snapshots enable row level security;

drop policy if exists rxmuk_snapshots_select on public.rxmuk_snapshots;
create policy rxmuk_snapshots_select on public.rxmuk_snapshots
  for select to authenticated using (public.rxmuk_is_approved() and expires_at > now());

drop policy if exists rxmuk_snapshots_insert on public.rxmuk_snapshots;
create policy rxmuk_snapshots_insert on public.rxmuk_snapshots
  for insert to authenticated with check (public.rxmuk_is_approved() and created_by = auth.uid());

drop policy if exists rxmuk_snapshots_delete_own on public.rxmuk_snapshots;
create policy rxmuk_snapshots_delete_own on public.rxmuk_snapshots
  for delete to authenticated using (created_by = auth.uid());

-- After creating each account in Authentication > Users:
-- insert into public.rxmuk_users (user_id, role, display_name)
--   select id, 'province', 'ชื่อที่แสดง' from auth.users where email = 'someone@example.com';
