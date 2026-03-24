<?php

declare(strict_types=1);

namespace Club61\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Normalização da legenda na criação de post.
 */
final class PostCaptionTest extends TestCase
{
    public function test_trims_whitespace(): void
    {
        self::assertSame('hello', club61_normalize_post_caption("  hello  \n"));
    }

    public function test_empty_becomes_empty_string(): void
    {
        self::assertSame('', club61_normalize_post_caption(null));
        self::assertSame('', club61_normalize_post_caption('   '));
    }

    public function test_truncates_to_max_length(): void
    {
        $long = str_repeat('a', 5000);
        $out = club61_normalize_post_caption($long, 4000);
        self::assertSame(4000, strlen($out));
    }
}
