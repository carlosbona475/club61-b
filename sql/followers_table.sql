-- Seguidores (UUID). Rode no SQL Editor do Supabase.
-- Se já existir uma tabela `followers` com outro esquema (ex.: INT), faça backup e remova antes.

CREATE TABLE IF NOT EXISTS public.followers (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    follower_id uuid NOT NULL REFERENCES auth.users (id) ON DELETE CASCADE,
    following_id uuid NOT NULL REFERENCES auth.users (id) ON DELETE CASCADE,
    created_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT followers_no_self CHECK (follower_id <> following_id),
    CONSTRAINT followers_unique_pair UNIQUE (follower_id, following_id)
);

CREATE INDEX IF NOT EXISTS idx_followers_following_id ON public.followers (following_id);
CREATE INDEX IF NOT EXISTS idx_followers_follower_id ON public.followers (follower_id);
