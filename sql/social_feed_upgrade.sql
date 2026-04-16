-- Club61 — feed, likes, comments, perfil estendido, pedidos de mensagem
-- Rode no SQL Editor do Supabase (schema public).

-- ---------------------------------------------------------------------------
-- Curtidas (substitui uso legado da tabela "likes" se migrar dados manualmente)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.post_likes (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    post_id     integer NOT NULL REFERENCES public.posts(id) ON DELETE CASCADE,
    user_id     uuid NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    emoji       text NOT NULL DEFAULT '❤️',
    created_at  timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT post_likes_unique_user_post_emoji UNIQUE (post_id, user_id, emoji)
);

CREATE INDEX IF NOT EXISTS idx_post_likes_post_id ON public.post_likes(post_id);
CREATE INDEX IF NOT EXISTS idx_post_likes_user_id ON public.post_likes(user_id);

ALTER TABLE public.post_likes ENABLE ROW LEVEL SECURITY;

-- Políticas exemplo (ajuste ao seu modelo): leitura autenticada; escrita via service role no PHP
-- CREATE POLICY "read likes" ON public.post_likes FOR SELECT TO authenticated USING (true);

-- ---------------------------------------------------------------------------
-- Comentários
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.post_comments (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    post_id       integer NOT NULL REFERENCES public.posts(id) ON DELETE CASCADE,
    user_id       uuid NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    comment_text  text NOT NULL CHECK (char_length(comment_text) <= 2000),
    created_at    timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_post_comments_post_id ON public.post_comments(post_id);
CREATE INDEX IF NOT EXISTS idx_post_comments_created ON public.post_comments(post_id, created_at DESC);

-- ---------------------------------------------------------------------------
-- Pedidos de mensagem
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.message_requests (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    from_user  uuid NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    to_user    uuid NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    status     text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'rejected')),
    created_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT message_requests_unique_pair UNIQUE (from_user, to_user)
);

CREATE INDEX IF NOT EXISTS idx_message_requests_to ON public.message_requests(to_user, status);

-- ---------------------------------------------------------------------------
-- Campos extra em profiles (se ainda não existirem)
-- ---------------------------------------------------------------------------
ALTER TABLE public.profiles
    ADD COLUMN IF NOT EXISTS bio text,
    ADD COLUMN IF NOT EXISTS age integer CHECK (age IS NULL OR (age >= 18 AND age <= 120)),
    ADD COLUMN IF NOT EXISTS relationship_type text CHECK (relationship_type IS NULL OR relationship_type IN ('solteiro', 'solteira', 'casal', 'casado', 'casada', 'single', 'couple')),
    ADD COLUMN IF NOT EXISTS partner_age integer CHECK (partner_age IS NULL OR (partner_age >= 18 AND partner_age <= 120));

COMMENT ON COLUMN public.profiles.bio IS 'Bio curta do membro';
COMMENT ON COLUMN public.profiles.age IS 'Idade';
COMMENT ON COLUMN public.profiles.relationship_type IS 'solteiro | solteira | casal | casado | casada (minúsculas; single/couple legado)';
COMMENT ON COLUMN public.profiles.partner_age IS 'Idade do parceiro(a) se casal';

-- Se a coluna já existia com CHECK antigo, substitua a constraint:
ALTER TABLE public.profiles DROP CONSTRAINT IF EXISTS profiles_relationship_type_check;
ALTER TABLE public.profiles ADD CONSTRAINT profiles_relationship_type_check CHECK (
    relationship_type IS NULL OR relationship_type IN (
        'solteiro', 'solteira', 'casal', 'casado', 'casada', 'single', 'couple'
    )
);

-- Migração opcional de likes antigos (descomente se existir tabela public.likes compatível)
-- INSERT INTO public.post_likes (post_id, user_id, created_at)
-- SELECT l.post_id, l.user_id::uuid, COALESCE(l.created_at, now())
-- FROM public.likes l
-- ON CONFLICT DO NOTHING;
