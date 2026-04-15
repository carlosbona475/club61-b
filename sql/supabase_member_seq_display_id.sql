-- Sequência e trigger opcionais: novo perfil sem display_id recebe CL01, CL02…
-- (o PHP também atribui via assignDisplayIdIfEmptyForUser — use um dos fluxos de preferência.)

CREATE SEQUENCE IF NOT EXISTS public.club61_member_seq AS integer START WITH 1;

CREATE OR REPLACE FUNCTION public.club61_auto_display_id()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
  n bigint;
BEGIN
  IF NEW.display_id IS NULL OR btrim(NEW.display_id::text) = '' THEN
    n := nextval('public.club61_member_seq');
    NEW.display_id := 'CL' || lpad(n::text, greatest(2, char_length(n::text)), '0');
  END IF;
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS club61_profiles_display_bi ON public.profiles;

CREATE TRIGGER club61_profiles_display_bi
  BEFORE INSERT ON public.profiles
  FOR EACH ROW
  EXECUTE FUNCTION public.club61_auto_display_id();

-- Sincronizar a sequência com o maior número CL já usado (opcional, após migração de dados):
-- SELECT setval('public.club61_member_seq', COALESCE((SELECT MAX(
--   CASE WHEN display_id ~ '^CL[0-9]+$' THEN regexp_replace(display_id, '^CL', '')::int ELSE 0 END
-- ) FROM public.profiles), 0) + 1);
