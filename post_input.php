<?php

declare(strict_types=1);

/**
 * Normalização da legenda do post (mesma regra que create_post).
 */
function club61_normalize_post_caption(mixed $caption, int $maxLen = 4000): string
{
    $s = isset($caption) ? trim((string) $caption) : '';
    if (strlen($s) > $maxLen) {
        $s = substr($s, 0, $maxLen);
    }

    return $s;
}
