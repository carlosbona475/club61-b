-- Rode no SQL Editor do Supabase.
-- Requer extensão para UUID (geralmente já ativa): gen_random_uuid() vem de pgcrypto.

CREATE TABLE IF NOT EXISTS public.stories (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES auth.users (id),
    image_url TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    expires_at TIMESTAMPTZ DEFAULT (NOW() + INTERVAL '24 hours')
);

CREATE INDEX IF NOT EXISTS idx_stories_user_expires ON public.stories (user_id, expires_at DESC);

-- Políticas RLS (ajuste conforme necessidade). Leituras via service_role no PHP ignoram RLS.
ALTER TABLE public.stories ENABLE ROW LEVEL SECURITY;

CREATE POLICY "stories_select_active"
    ON public.stories FOR SELECT
    TO authenticated
    USING (expires_at > NOW());

CREATE POLICY "stories_insert_own"
    ON public.stories FOR INSERT
    TO authenticated
    WITH CHECK (auth.uid() = user_id);
