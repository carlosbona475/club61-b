# Club61-b

Projeto web em PHP com foco em:

- autenticacao (`login` e `registro`, com referencia a Supabase),
- feed de posts,
- perfil de usuario,
- estrutura inicial de banco de dados para posts e seguidores.

O repositorio ainda esta em fase inicial, com algumas telas prontas e partes de backend marcadas para implementacao.

## Estrutura do projeto

```text
club61-b/
├─ features/
│  ├─ auth/
│  │  ├─ index.php
│  │  ├─ login.php
│  │  └─ register.php
│  ├─ feed/
│  │  └─ index.php
│  └─ profile/
│     └─ index.php
├─ database/
│  ├─ posts.sql
│  └─ followers.sql
└─ navbar.php
```

## O que ja existe

- paginas de `login` e `registro` com formulario e estilo base;
- arquivo base para autenticacao em `features/auth/index.php`;
- pagina inicial do feed (`features/feed/index.php`) com TODO para listagem/infinite scroll;
- pagina inicial de perfil (`features/profile/index.php`) com TODO para avatar, bio e posts;
- scripts SQL para tabelas:
  - `posts` (id, user_id, image_url, caption, likes_count, created_at)
  - `followers` (follower_id, followed_id)

## Requisitos

- PHP 8.1+ (ou compativel com seu ambiente local)
- Servidor local (XAMPP, Laragon, WAMP ou `php -S`)
- Banco SQL (scripts atuais usam tipos compativeis com PostgreSQL)

## Como rodar localmente

1. Clone/baixe o projeto para sua maquina.
2. Configure seu servidor local apontando para a pasta do projeto.
3. Crie o banco e execute os scripts de `database/`:
   - `database/posts.sql`
   - `database/followers.sql`
4. Inicie o servidor PHP e abra no navegador.

Exemplo com servidor embutido do PHP:

```bash
php -S localhost:8000
```

Depois acesse:

- `http://localhost:8000/features/auth/login.php`
- `http://localhost:8000/features/auth/register.php`

## Proximos passos recomendados

- implementar autenticação real com Supabase em `features/auth/index.php`;
- conectar formularios de login/registro ao backend;
- implementar CRUD de posts no feed;
- adicionar protecao de rotas para usuarios autenticados;
- ajustar links/rotas para consistencia (`/auth/*` vs `features/auth/*`);
- incluir validacoes de entrada e tratamento de erros.

## Licenca

Defina a licenca do projeto (ex.: MIT) conforme sua necessidade.
