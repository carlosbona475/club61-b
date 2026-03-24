<?php

declare(strict_types=1);

namespace Club61\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Validação de imagens (equivalente ao fluxo de upload, sem HTTP).
 */
final class UploadValidationTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    private function writeTempPng(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'c61png');
        self::assertNotFalse($path);
        $this->tempFiles[] = $path;
        $bin = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmWQQAAAABJRU5ErkJggg==',
            true
        );
        self::assertNotFalse($bin);
        file_put_contents($path, $bin);

        return $path;
    }

    public function test_upload_null_returns_error(): void
    {
        $r = club61_validate_image_upload(null, 1024, ['image/png' => 'png']);
        self::assertFalse($r['ok']);
        self::assertArrayHasKey('error', $r);
    }

    public function test_upload_no_file_error(): void
    {
        $r = club61_validate_image_upload(
            ['tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
            1024,
            ['image/png' => 'png']
        );
        self::assertFalse($r['ok']);
    }

    public function test_temp_path_accepts_minimal_png(): void
    {
        $path = $this->writeTempPng();
        $size = (int) filesize($path);
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $r = club61_validate_image_temp_path($path, $size, 5 * 1024 * 1024, $map);
        self::assertTrue($r['ok']);
        self::assertSame('image/png', $r['mime']);
        self::assertSame('png', $r['ext']);
    }

    public function test_temp_path_rejects_oversize(): void
    {
        $path = $this->writeTempPng();
        $r = club61_validate_image_temp_path($path, 999999999, 100, ['image/png' => 'png']);
        self::assertFalse($r['ok']);
    }

    public function test_temp_path_rejects_fake_png_content(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'c61bad');
        self::assertNotFalse($path);
        $this->tempFiles[] = $path;
        file_put_contents($path, 'not really an image');
        $size = (int) filesize($path);
        $r = club61_validate_image_temp_path($path, $size, 1024, ['image/png' => 'png']);
        self::assertFalse($r['ok']);
    }
}
