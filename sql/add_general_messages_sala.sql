-- Coluna para filtrar mensagens do chat geral por sala (cidade).
-- Execute no SQL Editor do Supabase se ainda não existir.

ALTER TABLE public.general_messages
ADD COLUMN IF NOT EXISTS sala text;

UPDATE public.general_messages
SET sala = 'geral'
WHERE sala IS NULL;

CREATE INDEX IF NOT EXISTS idx_general_messages_sala_created
ON public.general_messages (sala, created_at DESC);
