-- =========================================================
-- Club61 — invites: colunas que faltavam + RLS compatível
-- Rode no Supabase SQL Editor (idempotente).
-- =========================================================

-- 1) Colunas faltantes em public.invites
ALTER TABLE public.invites
    ADD COLUMN IF NOT EXISTS expires_at timestamptz;

ALTER TABLE public.invites
    ADD COLUMN IF NOT EXISTS used_by uuid REFERENCES auth.users(id) ON DELETE SET NULL;

ALTER TABLE public.invites
    ADD COLUMN IF NOT EXISTS used_at timestamptz;

-- 2) Índices auxiliares
CREATE INDEX IF NOT EXISTS idx_invites_used_by     ON public.invites(used_by);
CREATE INDEX IF NOT EXISTS idx_invites_expires_at  ON public.invites(expires_at);
CREATE INDEX IF NOT EXISTS idx_invites_created_by  ON public.invites(created_by);

-- 3) Row Level Security
ALTER TABLE public.invites ENABLE ROW LEVEL SECURITY;

-- SELECT: dono vê os próprios convites
DROP POLICY IF EXISTS "invites_select_own" ON public.invites;
CREATE POLICY "invites_select_own"
    ON public.invites
    FOR SELECT
    TO authenticated
    USING (created_by = auth.uid());

-- INSERT: user autenticado pode criar convite com created_by = seu uid
DROP POLICY IF EXISTS "invites_insert_own" ON public.invites;
CREATE POLICY "invites_insert_own"
    ON public.invites
    FOR INSERT
    TO authenticated
    WITH CHECK (created_by = auth.uid());

-- UPDATE: dono pode atualizar o próprio convite (ex.: cancelar)
DROP POLICY IF EXISTS "invites_update_own" ON public.invites;
CREATE POLICY "invites_update_own"
    ON public.invites
    FOR UPDATE
    TO authenticated
    USING (created_by = auth.uid())
    WITH CHECK (created_by = auth.uid());

-- (Nota) service_role NÃO precisa de policies: bypassa RLS por padrão.
