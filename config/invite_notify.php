<?php

declare(strict_types=1);

/**
 * Envio de códigos de convite por e-mail ou SMS (fora do Supabase: APIs externas).
 *
 * Variáveis de ambiente (.env):
 *
 * - CLUB61_PUBLIC_SITE_URL — URL pública do site (ex.: https://club61.site), usada no link de cadastro.
 *
 * E-mail (escolha uma):
 * - RESEND_API_KEY — https://resend.com (recomendado). Domínio de envio verificado no Resend.
 * - RESEND_FROM — ex.: "Club61 <convites@seudominio.com>"
 * - Ou, sem Resend: CLUB61_MAIL_FROM + servidor SMTP local com mail() (menos fiável em hospedagem partilhada).
 *
 * SMS (Twilio):
 * - TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER (E.164, ex.: +15551234567)
 */

require_once __DIR__ . '/dotenv.php';

/**
 * URL base pública (sem barra final).
 */
function club61_public_base_url(): string
{
    $u = (string) (getenv('CLUB61_PUBLIC_SITE_URL') ?: $_ENV['CLUB61_PUBLIC_SITE_URL'] ?? '');
    $u = rtrim(trim($u), '/');
    if ($u === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            return ($https ? 'https://' : 'http://') . $host;
        }

        return 'https://club61.site';
    }

    return $u;
}

function club61_invite_register_path(): string
{
    return '/features/auth/register.php';
}

/**
 * @param-out string $err
 */
function club61_send_invite_email(string $to, string $code, string &$err): bool
{
    $err = '';
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $err = 'E-mail inválido.';

        return false;
    }

    $base = club61_public_base_url();
    $registerUrl = $base . club61_invite_register_path();
    $subject = 'Convite Club61 — seu código de acesso';
    $html = '<p>Olá,</p>'
        . '<p>Você foi convidado para o <strong>Club61</strong>.</p>'
        . '<p>Seu código de convite:</p>'
        . '<p style="font-size:1.25rem;font-weight:700;letter-spacing:0.08em;color:#7B2EFF">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>Use este código na página de cadastro:</p>'
        . '<p><a href="' . htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
        . '<p style="color:#666;font-size:0.85rem">O código expira em 7 dias se não for utilizado.</p>';

    $resendKey = (string) (getenv('RESEND_API_KEY') ?: $_ENV['RESEND_API_KEY'] ?? '');
    if ($resendKey !== '') {
        $from = (string) (getenv('RESEND_FROM') ?: $_ENV['RESEND_FROM'] ?? '');
        if ($from === '') {
            $err = 'Defina RESEND_FROM no .env (ex.: Club61 &lt;convites@seudominio.com&gt;).';

            return false;
        }
        $payload = [
            'from' => $from,
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ];
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $resendKey,
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 25,
        ]);
        $raw = curl_exec($ch);
        $codeHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($codeHttp >= 200 && $codeHttp < 300) {
            return true;
        }
        $err = 'Resend HTTP ' . $codeHttp . ($raw !== false && $raw !== '' ? (': ' . substr((string) $raw, 0, 200)) : '');

        return false;
    }

    $fromMail = (string) (getenv('CLUB61_MAIL_FROM') ?: $_ENV['CLUB61_MAIL_FROM'] ?? '');
    if ($fromMail !== '' && function_exists('mail')) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $fromMail,
        ];
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, implode("\r\n", $headers));
        if ($ok) {
            return true;
        }
        $err = 'mail() retornou falha (verifique servidor SMTP / hosting).';

        return false;
    }

    $err = 'Configure RESEND_API_KEY + RESEND_FROM ou CLUB61_MAIL_FROM para enviar e-mail.';

    return false;
}

/**
 * Normaliza telefone BR para E.164 (+55...).
 */
function club61_normalize_br_phone(string $input): ?string
{
    $d = preg_replace('/\D+/', '', $input);
    if ($d === null || $d === '') {
        return null;
    }
    if (str_starts_with($d, '55') && strlen($d) >= 12) {
        return '+' . $d;
    }
    if (strlen($d) >= 10 && strlen($d) <= 11) {
        return '+55' . $d;
    }

    return null;
}

/**
 * @param-out string $err
 */
function club61_send_invite_sms(string $e164Phone, string $code, string &$err): bool
{
    $err = '';
    $sid = (string) (getenv('TWILIO_ACCOUNT_SID') ?: $_ENV['TWILIO_ACCOUNT_SID'] ?? '');
    $token = (string) (getenv('TWILIO_AUTH_TOKEN') ?: $_ENV['TWILIO_AUTH_TOKEN'] ?? '');
    $from = (string) (getenv('TWILIO_FROM_NUMBER') ?: $_ENV['TWILIO_FROM_NUMBER'] ?? '');
    if ($sid === '' || $token === '' || $from === '') {
        $err = 'Configure TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN e TWILIO_FROM_NUMBER no .env.';

        return false;
    }

    $base = club61_public_base_url();
    $registerUrl = $base . club61_invite_register_path();
    $body = 'Club61: seu codigo de convite e ' . $code . '. Cadastro: ' . $registerUrl;

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_POSTFIELDS => http_build_query([
            'From' => $from,
            'To' => $e164Phone,
            'Body' => $body,
        ]),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $codeHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($codeHttp >= 200 && $codeHttp < 300) {
        return true;
    }
    $err = 'Twilio HTTP ' . $codeHttp . ($raw !== false && $raw !== '' ? (': ' . substr((string) $raw, 0, 300)) : '');

    return false;
}
