-- Pedidos de seguir (aprovação pelo perfil seguido). Executar no Supabase SQL Editor.

CREATE TABLE IF NOT EXISTS public.follows (
  id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
  follower_id uuid NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
  following_id uuid NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
  status text NOT NULL DEFAULT 'pendente',
  created_at timestamptz DEFAULT now(),
  CONSTRAINT follows_pair_unique UNIQUE (follower_id, following_id),
  CONSTRAINT follows_status_check CHECK (status IN ('pendente', 'aceito', 'recusado'))
);

CREATE INDEX IF NOT EXISTS idx_follows_following_status ON public.follows (following_id, status);
CREATE INDEX IF NOT EXISTS idx_follows_follower_status ON public.follows (follower_id, status);
