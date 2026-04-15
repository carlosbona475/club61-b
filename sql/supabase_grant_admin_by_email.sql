-- Conceder role admin ao utilizador por e-mail (Supabase SQL Editor).
-- Ajuste o e-mail se necessário.

UPDATE public.profiles AS p
SET role = 'admin'
FROM auth.users AS u
WHERE u.id = p.id
  AND lower(u.email) = lower('carlosbonadia042@gmail.com');

-- Verificação (opcional):
-- SELECT p.id, u.email, p.role FROM public.profiles p JOIN auth.users u ON u.id = p.id WHERE lower(u.email) = lower('carlosbonadia042@gmail.com');
