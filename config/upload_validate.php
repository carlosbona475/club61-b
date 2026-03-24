<?php

declare(strict_types=1);

/**
 * Valida conteúdo de imagem num ficheiro temporário (usado após is_uploaded_file e em testes).
 *
 * @param array<string, string> $allowedMimeToExt
 * @return array{ok: true, mime: string, ext: string}|array{ok: false, error: string}
 */
function club61_validate_image_temp_path(string $tmp, int $declaredSize, int $maxBytes, array $allowedMimeToExt): array
{
    if ($tmp === '' || !is_readable($tmp)) {
        return ['ok' => false, 'error' => 'Não foi possível ler o ficheiro.'];
    }

    if ($declaredSize <= 0 || $declaredSize > $maxBytes) {
        return ['ok' => false, 'error' => 'Tamanho do ficheiro não permitido.'];
    }

    $mime = null;
    if (class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $m = $fi->file($tmp);
        if (is_string($m) && $m !== '') {
            $mime = $m;
        }
    }
    if ($mime === null && function_exists('mime_content_type')) {
        $m = @mime_content_type($tmp);
        if (is_string($m) && $m !== '' && $m !== 'application/octet-stream') {
            $mime = $m;
        }
    }
    if ($mime === null) {
        return ['ok' => false, 'error' => 'Não foi possível verificar o tipo do ficheiro.'];
    }

    if (!isset($allowedMimeToExt[$mime])) {
        return ['ok' => false, 'error' => 'Tipo de imagem não permitido.'];
    }

    $handle = @fopen($tmp, 'rb');
    if ($handle === false) {
        return ['ok' => false, 'error' => 'Não foi possível ler o ficheiro.'];
    }
    $head = fread($handle, 16);
    fclose($handle);
    if ($head === false || strlen($head) < 3) {
        return ['ok' => false, 'error' => 'Ficheiro vazio ou corrompido.'];
    }

    $bytes = unpack('C*', $head);
    $okMagic = false;
    if ($mime === 'image/jpeg' && isset($bytes[1], $bytes[2], $bytes[3])) {
        $okMagic = $bytes[1] === 0xFF && $bytes[2] === 0xD8 && $bytes[3] === 0xFF;
    } elseif ($mime === 'image/png' && strlen($head) >= 8) {
        $okMagic = str_starts_with($head, "\x89PNG\r\n\x1a\n");
    } elseif ($mime === 'image/webp' && strlen($head) >= 12) {
        $okMagic = str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP';
    }

    if (!$okMagic) {
        return ['ok' => false, 'error' => 'Conteúdo do ficheiro não corresponde a uma imagem permitida.'];
    }

    if ($mime === 'image/jpeg' || $mime === 'image/png') {
        $info = @getimagesize($tmp);
        if ($info === false) {
            return ['ok' => false, 'error' => 'Imagem inválida ou corrompida.'];
        }
    }

    return ['ok' => true, 'mime' => $mime, 'ext' => $allowedMimeToExt[$mime]];
}

/**
 * Validação de uploads de imagem (MIME real, magic bytes, tamanho, is_uploaded_file).
 *
 * @param array<string, mixed>|null $file entrada $_FILES['campo']
 * @param array<string, string> $allowedMimeToExt
 * @return array{ok: true, mime: string, ext: string}|array{ok: false, error: string}
 */
function club61_validate_image_upload(?array $file, int $maxBytes, array $allowedMimeToExt): array
{
    if ($file === null || !isset($file['tmp_name'], $file['error'], $file['size'])) {
        return ['ok' => false, 'error' => 'Nenhum ficheiro enviado.'];
    }

    $err = (int) $file['error'];
    if ($err !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE => 'Ficheiro excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE => 'Ficheiro excede o limite do formulário.',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto.',
            UPLOAD_ERR_NO_FILE => 'Nenhum ficheiro.',
        ];

        return ['ok' => false, 'error' => $map[$err] ?? 'Erro no upload.'];
    }

    $tmp = (string) $file['tmp_name'];
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Upload inválido.'];
    }

    return club61_validate_image_temp_path($tmp, (int) $file['size'], $maxBytes, $allowedMimeToExt);
}
