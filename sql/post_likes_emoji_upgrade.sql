-- Club61 — reações com emoji em post_likes (integer post_id, alinhado a sql/social_feed_upgrade.sql)
-- Rode no SQL Editor do Supabase após post_likes existir. Idempotente: pode reexecutar após ajustes.

ALTER TABLE public.post_likes
    ADD COLUMN IF NOT EXISTS emoji text NOT NULL DEFAULT '❤️';

-- Constraint antiga: UNIQUE (post_id, user_id) — substituir por (post_id, user_id, emoji)
ALTER TABLE public.post_likes DROP CONSTRAINT IF EXISTS post_likes_unique_user_post;
ALTER TABLE public.post_likes DROP CONSTRAINT IF EXISTS post_likes_unique_user_post_emoji;

ALTER TABLE public.post_likes
    ADD CONSTRAINT post_likes_unique_user_post_emoji UNIQUE (post_id, user_id, emoji);

ALTER TABLE public.post_likes ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Ver curtidas" ON public.post_likes;
DROP POLICY IF EXISTS "Inserir curtida" ON public.post_likes;
DROP POLICY IF EXISTS "Deletar curtida" ON public.post_likes;

CREATE POLICY "Ver curtidas" ON public.post_likes FOR SELECT USING (true);
CREATE POLICY "Inserir curtida" ON public.post_likes FOR INSERT WITH CHECK (true);
CREATE POLICY "Deletar curtida" ON public.post_likes FOR DELETE USING (true);
