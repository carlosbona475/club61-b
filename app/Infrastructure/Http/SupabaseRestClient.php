<?php

declare(strict_types=1);

namespace Club61\Infrastructure\Http;

/**
 * Cliente HTTP mínimo para PostgREST (comportamento alinhado ao restante do projeto).
 */
final class SupabaseRestClient
{
    /**
     * GET JSON; devolve array decodificado ou null se falhar.
     *
     * @param list<string> $extraHeaders
     * @return list{0: int, 1: ?array}|null [status, body] ou null se curl falhar antes do código
     */
    public function jsonGet(string $fullUrl, array $extraHeaders): ?array
    {
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $extraHeaders);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) {
            return null;
        }
        if ($code < 200 || $code >= 300) {
            return [$code, null];
        }
        $decoded = json_decode($raw, true);

        return [$code, is_array($decoded) ? $decoded : null];
    }
}
