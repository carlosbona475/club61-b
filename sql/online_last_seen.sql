-- Presença online por heartbeat em carregamento de página.

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS last_seen timestamp with time zone;

COMMENT ON COLUMN public.profiles.last_seen IS 'Última atividade do membro (UTC)';
