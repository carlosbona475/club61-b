-- Geolocalização em profiles (Supabase SQL Editor)

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS latitude double precision;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS longitude double precision;

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS bairro text;

COMMENT ON COLUMN public.profiles.latitude IS 'Latitude WGS84 (graus decimais)';
COMMENT ON COLUMN public.profiles.longitude IS 'Longitude WGS84 (graus decimais)';
COMMENT ON COLUMN public.profiles.bairro IS 'Bairro / zona (opcional)';
