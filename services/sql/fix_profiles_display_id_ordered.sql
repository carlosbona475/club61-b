-- Atribui display_id CL01, CL02… por ordem de criação (apenas onde está vazio).
-- Executar no SQL Editor do Supabase após backup.

WITH ranked AS (
  SELECT
    id,
    row_number() OVER (ORDER BY created_at ASC NULLS LAST, id ASC) AS rn
  FROM public.profiles
  WHERE display_id IS NULL OR btrim(display_id::text) = ''
)
UPDATE public.profiles AS p
SET display_id = 'CL' || lpad(r.rn::text, greatest(2, char_length(r.rn::text)), '0')
FROM ranked AS r
WHERE p.id = r.id;
