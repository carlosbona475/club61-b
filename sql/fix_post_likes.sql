-- Club61 — alinhar post_likes a post_id bigint (PHP) e user_id em profiles.
-- Rode no SQL Editor do Supabase quando houver conflito uuid vs integer.
-- ATENÇÃO: DROP TABLE apaga todas as curtidas existentes.

DROP TABLE IF EXISTS post_likes CASCADE;

CREATE TABLE public.post_likes (
    id bigserial PRIMARY KEY,
    user_id uuid NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    post_id bigint NOT NULL,
    created_at timestamp with time zone DEFAULT now()
);

CREATE UNIQUE INDEX ux_post_likes_user_post ON public.post_likes(user_id, post_id);
CREATE INDEX idx_post_likes_post ON public.post_likes(post_id);

ALTER TABLE public.post_likes ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Ver curtidas" ON post_likes;
DROP POLICY IF EXISTS "Inserir curtida" ON post_likes;
DROP POLICY IF EXISTS "Deletar curtida" ON post_likes;

CREATE POLICY "Ver curtidas" ON post_likes FOR SELECT USING (true);
CREATE POLICY "Inserir curtida" ON post_likes FOR INSERT WITH CHECK (true);
CREATE POLICY "Deletar curtida" ON post_likes FOR DELETE USING (true);
