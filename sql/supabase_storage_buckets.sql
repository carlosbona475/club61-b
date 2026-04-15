-- Buckets públicos para avatars, posts e stories (Supabase SQL Editor).
-- Depois confirme em Storage → Policies (leitura pública + upload via service_role).

INSERT INTO storage.buckets (id, name, public)
VALUES
  ('avatars', 'avatars', true),
  ('posts', 'posts', true),
  ('stories', 'stories', true)
ON CONFLICT (id) DO UPDATE SET public = EXCLUDED.public;
