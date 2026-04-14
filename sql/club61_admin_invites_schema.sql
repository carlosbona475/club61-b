-- Executar no SQL Editor do Supabase (admin + convites alinhados ao PHP)
-- Perfis: role padrão membro ou admin
ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS role TEXT DEFAULT 'membro';

-- Convites: modelo used_by / used_at / expires_at (compatível com código legado status)
ALTER TABLE public.invites
ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ DEFAULT (NOW() + INTERVAL '7 days');

ALTER TABLE public.invites
ADD COLUMN IF NOT EXISTS used_by UUID REFERENCES public.profiles(id);

ALTER TABLE public.invites
ADD COLUMN IF NOT EXISTS used_at TIMESTAMPTZ;

COMMENT ON COLUMN public.profiles.role IS 'admin | membro (legado: member)';
