-- Identificador público sequencial CL01, CL02… na tabela profiles.
-- Executar no SQL Editor do Supabase (schema public).

ALTER TABLE public.profiles
ADD COLUMN IF NOT EXISTS display_id TEXT UNIQUE;

CREATE OR REPLACE FUNCTION public.generate_display_id()
RETURNS TRIGGER AS $$
DECLARE
  next_num INTEGER;
BEGIN
  SELECT COALESCE(MAX(CAST(SUBSTRING(display_id FROM 3) AS INTEGER)), 0) + 1
  INTO next_num
  FROM public.profiles
  WHERE display_id IS NOT NULL;

  NEW.display_id := 'CL' || LPAD(next_num::TEXT, 2, '0');
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS set_display_id ON public.profiles;

CREATE TRIGGER set_display_id
  BEFORE INSERT ON public.profiles
  FOR EACH ROW
  WHEN (NEW.display_id IS NULL)
  EXECUTE FUNCTION public.generate_display_id();

-- Perfis existentes sem display_id: um número de cada vez, evitando colisão com CL já usados.
DO $$
DECLARE
  rec RECORD;
  next_num INTEGER;
BEGIN
  FOR rec IN
    SELECT id FROM public.profiles WHERE display_id IS NULL ORDER BY created_at ASC
  LOOP
    SELECT COALESCE(MAX(CAST(SUBSTRING(display_id FROM 3) AS INTEGER)), 0) + 1
    INTO next_num
    FROM public.profiles
    WHERE display_id IS NOT NULL;

    UPDATE public.profiles
    SET display_id = 'CL' || LPAD(next_num::TEXT, 2, '0')
    WHERE id = rec.id;
  END LOOP;
END $$;
