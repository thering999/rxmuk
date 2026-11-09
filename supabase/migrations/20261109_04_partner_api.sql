-- Partner API (record-level data for approved agencies only).
-- Keys are stored as SHA-256 hashes; the plain key is shown once at creation.
-- Only the Edge Function (service role) reads keys; super admins (role 'province') manage them via RPC.
-- Safe to run any time: nothing uses these until the rxmuk-partner-api function is deployed.

create extension if not exists pgcrypto with schema extensions;

create table if not exists public.rxmuk_api_keys (
  id             uuid primary key default gen_random_uuid(),
  name           text not null check (length(name) between 1 and 200),
  key_hash       text not null unique,
  key_prefix     text not null,
  allow_pid_hash boolean not null default false,
  expires_at     timestamptz,
  revoked_at     timestamptz,
  created_by     uuid not null default auth.uid() references auth.users(id),
  created_at     timestamptz not null default now(),
  last_used_at   timestamptz
);
alter table public.rxmuk_api_keys enable row level security;
-- no policies on purpose: clients cannot read hashes; access is through the functions below

create table if not exists public.rxmuk_api_log (
  id       bigint generated always as identity primary key,
  key_id   uuid references public.rxmuk_api_keys(id) on delete set null,
  query    text,
  rows     integer,
  status   integer not null,
  at       timestamptz not null default now()
);
create index if not exists rxmuk_api_log_key_at on public.rxmuk_api_log (key_id, at desc);
alter table public.rxmuk_api_log enable row level security;

drop policy if exists rxmuk_api_log_select on public.rxmuk_api_log;
create policy rxmuk_api_log_select on public.rxmuk_api_log
  for select to authenticated using (public.rxmuk_role() = 'province');

create or replace function public.rxmuk_create_api_key(p_name text, p_days integer, p_allow_pid_hash boolean default false)
returns text language plpgsql security definer set search_path = public, extensions as $$
declare
  k text;
begin
  if public.rxmuk_role() is distinct from 'province' then
    raise exception 'not allowed' using errcode = '42501';
  end if;
  if p_days is null or p_days < 1 or p_days > 730 then
    raise exception 'p_days must be 1-730';
  end if;
  k := 'rxk_' || encode(extensions.gen_random_bytes(24), 'hex');
  insert into public.rxmuk_api_keys (name, key_hash, key_prefix, allow_pid_hash, expires_at)
  values (p_name, encode(extensions.digest(k, 'sha256'), 'hex'), left(k, 10), coalesce(p_allow_pid_hash, false), now() + make_interval(days => p_days));
  return k;
end;
$$;

create or replace function public.rxmuk_list_api_keys()
returns table (id uuid, name text, key_prefix text, allow_pid_hash boolean, expires_at timestamptz,
               revoked_at timestamptz, created_at timestamptz, last_used_at timestamptz, calls_30d bigint)
language sql stable security definer set search_path = public as $$
  select k.id, k.name, k.key_prefix, k.allow_pid_hash, k.expires_at, k.revoked_at, k.created_at, k.last_used_at,
         (select count(*) from public.rxmuk_api_log l where l.key_id = k.id and l.at > now() - interval '30 days')
  from public.rxmuk_api_keys k
  where public.rxmuk_role() = 'province'
  order by k.created_at desc;
$$;

create or replace function public.rxmuk_revoke_api_key(p_id uuid)
returns void language plpgsql security definer set search_path = public as $$
begin
  if public.rxmuk_role() is distinct from 'province' then
    raise exception 'not allowed' using errcode = '42501';
  end if;
  update public.rxmuk_api_keys set revoked_at = now() where id = p_id and revoked_at is null;
end;
$$;

revoke all on function public.rxmuk_create_api_key(text, integer, boolean) from public, anon;
revoke all on function public.rxmuk_list_api_keys() from public, anon;
revoke all on function public.rxmuk_revoke_api_key(uuid) from public, anon;
grant execute on function public.rxmuk_create_api_key(text, integer, boolean) to authenticated;
grant execute on function public.rxmuk_list_api_keys() to authenticated;
grant execute on function public.rxmuk_revoke_api_key(uuid) to authenticated;
