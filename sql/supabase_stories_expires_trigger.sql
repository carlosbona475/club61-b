-- Garante expires_at = created_at + 24h em cada story (substitui valor enviado pelo cliente).

CREATE OR REPLACE FUNCTION public.club61_stories_expires_from_created()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  NEW.expires_at := COALESCE(NEW.created_at, timezone('utc', now())) + interval '24 hours';
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS club61_stories_expires_bi ON public.stories;

CREATE TRIGGER club61_stories_expires_bi
  BEFORE INSERT OR UPDATE OF created_at ON public.stories
  FOR EACH ROW
  EXECUTE FUNCTION public.club61_stories_expires_from_created();
