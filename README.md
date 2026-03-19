Implementar autenticação real no projeto PHP.

Usar Supabase REST Auth API.

Criar:

- arquivo config/supabase.php com URL e anon key
- função loginUser(email, password)
- função registerUser(email, password)

Após login:

- salvar access_token na sessão PHP
- salvar user_id

Criar middleware auth_guard.php:

- verificar sessão
- redirecionar para login se não autenticado

Proteger:

- features/feed/index.php
- features/profile/index.php

Criar logout.php destruindo sessão.