-- Rode no SQL Editor do Supabase (schema public, tabela profiles)
-- Adiciona colunas usadas pelo perfil (tipo e cidade)

ALTER TABLE public.profiles
  ADD COLUMN IF NOT EXISTS tipo text,
  ADD COLUMN IF NOT EXISTS cidade text;

-- Opcional: comentários nas colunas
COMMENT ON COLUMN public.profiles.tipo IS 'Homem, Mulher ou Casal';
COMMENT ON COLUMN public.profiles.cidade IS 'Cidade informada pelo membro';
