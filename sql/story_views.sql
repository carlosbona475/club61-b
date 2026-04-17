-- Club61 — visualizações de stories e visitas ao perfil (rodar no Supabase).

-- Visualizações de stories
CREATE TABLE IF NOT EXISTS public.story_views (
    id bigserial PRIMARY KEY,
    story_id uuid NOT NULL REFERENCES stories(id) ON DELETE CASCADE,
    viewer_id uuid NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    viewed_at timestamptz DEFAULT now(),
    UNIQUE(story_id, viewer_id)
);

CREATE INDEX IF NOT EXISTS idx_story_views_story ON public.story_views(story_id);
ALTER TABLE public.story_views ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Ver views" ON public.story_views;
DROP POLICY IF EXISTS "Inserir view" ON public.story_views;
CREATE POLICY "Ver views" ON public.story_views FOR SELECT USING (true);
CREATE POLICY "Inserir view" ON public.story_views FOR INSERT WITH CHECK (true);

-- Visitas ao perfil
CREATE TABLE IF NOT EXISTS public.profile_views (
    id bigserial PRIMARY KEY,
    profile_id uuid NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    viewer_id uuid NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    viewed_at timestamptz DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_profile_views_profile ON public.profile_views(profile_id, viewed_at DESC);
ALTER TABLE public.profile_views ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Ver visitas" ON public.profile_views;
DROP POLICY IF EXISTS "Inserir visita" ON public.profile_views;
CREATE POLICY "Ver visitas" ON public.profile_views FOR SELECT USING (true);
CREATE POLICY "Inserir visita" ON public.profile_views FOR INSERT WITH CHECK (true);
