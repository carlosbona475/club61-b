<?php

declare(strict_types=1);

/**
 * Salas por cidade (chat / UI).
 *
 * @return list<array{slug:string,nome:string,emoji:string,cor:string}>
 */
function club61_city_rooms(): array
{
    return [
        ['slug' => 'presidente-prudente', 'nome' => 'Presidente Prudente', 'emoji' => '🏙️', 'cor' => '#1565C0'],
        ['slug' => 'maringa', 'nome' => 'Maringá', 'emoji' => '🌳', 'cor' => '#2E7D32'],
        ['slug' => 'londrina', 'nome' => 'Londrina', 'emoji' => '☀️', 'cor' => '#E65100'],
    ];
}

/**
 * Fragmento para filtro ilike em profiles.cidade (contagem "online").
 */
function club61_city_room_cidade_ilike(string $slug): string
{
    $map = [
        'presidente-prudente' => 'Presidente',
        'maringa' => 'Maring',
        'londrina' => 'Londrina',
    ];

    return $map[$slug] ?? '';
}

/**
 * @return array<string, array{slug:string,nome:string,emoji:string,cor:string}>
 */
function club61_city_rooms_by_slug(): array
{
    $out = [];
    foreach (club61_city_rooms() as $r) {
        $out[$r['slug']] = $r;
    }

    return $out;
}

/**
 * @return array{slug:string,nome:string,emoji:string,cor:string}|null
 */
function club61_city_room_by_slug(string $slug): ?array
{
    $slug = trim($slug);

    return club61_city_rooms_by_slug()[$slug] ?? null;
}
