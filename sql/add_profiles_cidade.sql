-- Cidade no perfil (executar no SQL Editor do Supabase)

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS cidade TEXT;
