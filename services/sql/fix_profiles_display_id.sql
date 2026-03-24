-- Executar no SQL Editor do Supabase (corrige display_id vazio).
WITH ranked AS (
  SELECT id, ROW_NUMBER() OVER (ORDER BY created_at ASC) AS rn
  FROM public.profiles
  WHERE display_id IS NULL OR display_id = ''
)
UPDATE public.profiles p
SET display_id = 'CL' || LPAD(r.rn::text, 2, '0')
FROM ranked r
WHERE p.id = r.id;
