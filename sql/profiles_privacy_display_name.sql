-- Colunas de privacidade e nome de exibição (Supabase SQL Editor)
-- Idempotente: ADD COLUMN IF NOT EXISTS

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS display_name text;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS is_private boolean DEFAULT false;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS message_permission text DEFAULT 'all';

COMMENT ON COLUMN public.profiles.display_name IS 'Nome de exibição (opcional)';
COMMENT ON COLUMN public.profiles.is_private IS 'Perfil visível só para seguidores aprovados (futuro)';
COMMENT ON COLUMN public.profiles.message_permission IS 'all | following_only | none — quem pode enviar mensagem direta';

-- Novos valores de relacionamento (alinhados ao formulário de definições)
ALTER TABLE public.profiles DROP CONSTRAINT IF EXISTS profiles_relationship_type_check;
ALTER TABLE public.profiles ADD CONSTRAINT profiles_relationship_type_check CHECK (
    relationship_type IS NULL OR relationship_type IN (
        'solteiro', 'solteira', 'casal', 'casado', 'casada', 'single', 'couple',
        'namorando', 'prefiro_nao_dizer'
    )
);
