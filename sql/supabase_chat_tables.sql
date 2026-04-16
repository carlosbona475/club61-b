-- Chat geral (Club61): mensagens, reações e presença por sala.
-- Executar no SQL Editor do Supabase (public).

CREATE TABLE IF NOT EXISTS public.chat_messages (
  id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
  sala_id text NOT NULL,
  user_id uuid REFERENCES public.profiles(id),
  conteudo text,
  tipo text DEFAULT 'texto',
  media_url text,
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.chat_reactions (
  id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
  message_id uuid REFERENCES public.chat_messages(id) ON DELETE CASCADE,
  user_id uuid REFERENCES public.profiles(id),
  emoji text NOT NULL,
  created_at timestamptz DEFAULT now(),
  UNIQUE(message_id, user_id, emoji)
);

CREATE TABLE IF NOT EXISTS public.chat_presence (
  user_id uuid REFERENCES public.profiles(id) PRIMARY KEY,
  sala_id text NOT NULL,
  last_seen timestamptz DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_chat_messages_sala_created ON public.chat_messages (sala_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_chat_reactions_message ON public.chat_reactions (message_id);
CREATE INDEX IF NOT EXISTS idx_chat_presence_sala_seen ON public.chat_presence (sala_id, last_seen DESC);

-- O backend PHP usa SUPABASE_SERVICE_KEY (service_role), que ignora RLS.
-- Se um cliente anon/authenticated aceder a chat_messages via PostgREST, defina políticas à medida.
-- Garanta que existe linha em public.profiles para cada user_id — senão INSERT em chat_messages falha (FK).
