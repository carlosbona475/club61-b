-- =============================================================================
-- IMPORTANT (read this):
--   Do NOT use Google Translate / browser "Translate page" on this SQL.
--   PostgreSQL only accepts ENGLISH keywords: CREATE, TABLE, IF, NOT, EXISTS, etc.
--   If you see "TABELA" or "REATE" you broke the script — re-copy from the repo file.
--
-- Club61: full schema + minimal RLS (Supabase SQL Editor)
-- Run once; mostly idempotent (IF NOT EXISTS).
--
-- Depois: Authentication → Providers → Email
--   Para testar login sem abrir e-mail: desligar "Confirm email".
--
-- .env no servidor (JWT longo, Project Settings → API):
--   SUPABASE_URL=https://xxxx.supabase.co
--   SUPABASE_ANON_KEY=eyJ...
--   SUPABASE_SERVICE_KEY=eyJ...
-- Não uses sb_publishable / sb_secret no lugar das chaves anon/service JWT.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- PROFILES (cria tabela se o projeto ainda não tiver; liga a auth.users)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.profiles (
    id uuid NOT NULL PRIMARY KEY REFERENCES auth.users (id) ON DELETE CASCADE,
    username text,
    display_id text,
    avatar_url text,
    tipo text,
    cidade text,
    bairro text,
    bio text,
    age integer,
    relationship_type text,
    partner_age integer,
    role text DEFAULT 'member',
    status text DEFAULT 'active',
    latitude double precision,
    longitude double precision,
    last_seen timestamp with time zone,
    created_at timestamp with time zone DEFAULT now()
);

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS display_id text;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS avatar_url text;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS tipo text;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS cidade text;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS bairro text;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS bio text;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS age integer;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS relationship_type text;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS partner_age integer;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS role text;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS status text;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS latitude double precision;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS longitude double precision;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS last_seen timestamp with time zone;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS created_at timestamp with time zone;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS username text;

UPDATE public.profiles SET role = 'member' WHERE role IS NULL;
UPDATE public.profiles SET status = 'active' WHERE status IS NULL;

CREATE INDEX IF NOT EXISTS idx_profiles_role ON public.profiles (role);
CREATE INDEX IF NOT EXISTS idx_profiles_status ON public.profiles (status);
CREATE INDEX IF NOT EXISTS idx_profiles_last_seen ON public.profiles (last_seen DESC);

-- ---------------------------------------------------------------------------
-- POSTS / LIKES / COMMENTS
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.posts (
    id bigserial PRIMARY KEY,
    user_id uuid NOT NULL,
    image_url text NOT NULL,
    caption text,
    created_at timestamp with time zone DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_posts_user_created ON public.posts (user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS public.likes (
    id bigserial PRIMARY KEY,
    user_id uuid NOT NULL,
    post_id bigint NOT NULL,
    created_at timestamp with time zone DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_likes_user_post ON public.likes (user_id, post_id);
CREATE INDEX IF NOT EXISTS idx_likes_post ON public.likes (post_id);

CREATE TABLE IF NOT EXISTS public.post_likes (
    id bigserial PRIMARY KEY,
    user_id uuid NOT NULL,
    post_id bigint NOT NULL,
    created_at timestamp with time zone DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_post_likes_user_post ON public.post_likes (user_id, post_id);
CREATE INDEX IF NOT EXISTS idx_post_likes_post ON public.post_likes (post_id);

CREATE TABLE IF NOT EXISTS public.post_comments (
    id bigserial PRIMARY KEY,
    post_id bigint NOT NULL,
    user_id uuid NOT NULL,
    comment_text text NOT NULL CHECK (char_length(comment_text) <= 2000),
    created_at timestamp with time zone DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_post_comments_post_created ON public.post_comments (post_id, created_at DESC);

-- ---------------------------------------------------------------------------
-- FOLLOWERS
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.followers (
    id bigserial PRIMARY KEY,
    follower_id uuid NOT NULL,
    following_id uuid NOT NULL,
    created_at timestamp with time zone DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_followers_pair ON public.followers (follower_id, following_id);
CREATE INDEX IF NOT EXISTS idx_followers_following ON public.followers (following_id);

-- ---------------------------------------------------------------------------
-- STORIES
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.stories (
    id bigserial PRIMARY KEY,
    user_id uuid NOT NULL,
    image_url text NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    created_at timestamp with time zone DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_stories_user_created ON public.stories (user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_stories_expires ON public.stories (expires_at);

-- ---------------------------------------------------------------------------
-- MESSAGE REQUESTS / DM / GERAL
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.message_requests (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    from_user uuid NOT NULL,
    to_user uuid NOT NULL,
    status text NOT NULL DEFAULT 'pending',
    created_at timestamp with time zone DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_message_requests_to_status ON public.message_requests (to_user, status);
CREATE INDEX IF NOT EXISTS idx_message_requests_pair ON public.message_requests (from_user, to_user);

CREATE TABLE IF NOT EXISTS public.direct_messages (
    id bigserial PRIMARY KEY,
    sender_id uuid NOT NULL,
    receiver_id uuid NOT NULL,
    content text,
    media_url text,
    media_type text,
    read_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_dm_pair_created ON public.direct_messages (sender_id, receiver_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_dm_receiver_read ON public.direct_messages (receiver_id, read_at);

CREATE TABLE IF NOT EXISTS public.general_messages (
    id bigserial PRIMARY KEY,
    user_id uuid NOT NULL,
    content text,
    media_url text,
    media_type text,
    created_at timestamp with time zone DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_general_messages_created ON public.general_messages (created_at DESC);

-- ---------------------------------------------------------------------------
-- INVITES (cadastro com código)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.invites (
    id bigserial PRIMARY KEY,
    code text NOT NULL,
    status text NOT NULL DEFAULT 'available',
    created_by uuid,
    created_at timestamp with time zone DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_invites_code ON public.invites (code);
CREATE INDEX IF NOT EXISTS idx_invites_status ON public.invites (status);

-- ---------------------------------------------------------------------------
-- RLS — perfis (login cria linha com JWT do utilizador)
-- ---------------------------------------------------------------------------
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "club61_profiles_select_auth" ON public.profiles;

CREATE POLICY "club61_profiles_select_auth" ON public.profiles FOR SELECT
TO authenticated
USING (true);

DROP POLICY IF EXISTS "club61_profiles_insert_own" ON public.profiles;

CREATE POLICY "club61_profiles_insert_own" ON public.profiles FOR INSERT
TO authenticated
WITH CHECK ((SELECT auth.uid()) = id);

DROP POLICY IF EXISTS "club61_profiles_update_own" ON public.profiles;

CREATE POLICY "club61_profiles_update_own" ON public.profiles FOR UPDATE
TO authenticated
USING ((SELECT auth.uid()) = id)
WITH CHECK ((SELECT auth.uid()) = id);

-- ---------------------------------------------------------------------------
-- RLS — convites: leitura com chave anon (register.php só envia apikey)
-- ---------------------------------------------------------------------------
ALTER TABLE public.invites ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "club61_invites_select_anon" ON public.invites;

CREATE POLICY "club61_invites_select_anon" ON public.invites FOR SELECT TO anon USING (true);

DROP POLICY IF EXISTS "club61_invites_select_auth" ON public.invites;

CREATE POLICY "club61_invites_select_auth" ON public.invites FOR SELECT TO authenticated USING (true);

-- PATCH do convite usa service_role no PHP (ignora RLS). Não é necessário policy UPDATE para anon.
