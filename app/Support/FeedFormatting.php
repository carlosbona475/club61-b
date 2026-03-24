<?php

declare(strict_types=1);

namespace Club61\Support;

use DateTimeImmutable;
use Exception;

final class FeedFormatting
{
    public static function buildClLabel(string $disp, string $fallback = ''): string
    {
        if ($disp === '') {
            return $fallback;
        }
        $num = null;
        if (preg_match('/^CL\s*0*(\d+)$/i', $disp, $m)) {
            $num = (int) $m[1];
        } else {
            $digits = preg_replace('/\D/', '', $disp);
            if ($digits !== '') {
                $num = (int) $digits;
            }
        }

        return ($num !== null && $num > 0)
            ? 'CL' . str_pad((string) min(999, $num), 2, '0', STR_PAD_LEFT)
            : $fallback;
    }

    public static function relativeTime(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }
        try {
            $t = new DateTimeImmutable($iso);
            $now = new DateTimeImmutable('now');
            $diff = $now->getTimestamp() - $t->getTimestamp();
            if ($diff < 45) {
                return 'agora';
            }
            if ($diff < 3600) {
                return max(1, (int) floor($diff / 60)) . 'min';
            }
            if ($diff < 86400) {
                return max(1, (int) floor($diff / 3600)) . 'h';
            }

            return max(1, (int) floor($diff / 86400)) . 'd';
        } catch (Exception $e) {
            return '';
        }
    }
}
