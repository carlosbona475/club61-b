<?php

declare(strict_types=1);

/**
 * Mapa de rotas amigáveis → scripts PHP (Apache: ver rewrites em .htaccess na raiz).
 * Uso: require este ficheiro para listar URLs suportadas ou integrar um front controller.
 */
return [
    '/admin' => '/features/admin/index.php',
    '/admin/' => '/features/admin/index.php',
];
