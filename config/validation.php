<?php

declare(strict_types=1);

require_once __DIR__ . '/sanitize.php';

/**
 * @param array<string, mixed> $raw
 * @param array<string, string> $rules
 * @return array{ok: bool, errors: list<string>, data: array<string, string>}
 */
function club61_validate(array $raw, array $rules): array
{
    $errors = [];
    $data = [];

    foreach ($rules as $field => $ruleStr) {
        $parts = array_values(array_filter(array_map('trim', explode('|', $ruleStr))));
        $val = $raw[$field] ?? null;
        $str = is_string($val) ? trim($val) : (is_scalar($val) ? trim((string) $val) : '');
        $required = in_array('required', $parts, true);

        if ($required && $str === '') {
            $errors[] = 'Preencha todos os campos obrigatórios.';
            $data[$field] = '';

            continue;
        }

        if (!$required && $str === '') {
            $data[$field] = '';

            continue;
        }

        foreach ($parts as $rule) {
            if ($rule === 'required') {
                continue;
            }
            if ($rule === 'email') {
                if (!filter_var($str, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'E-mail inválido.';
                    break;
                }
            } elseif ($rule === 'int') {
                if (!preg_match('/^-?\d+$/', $str)) {
                    $errors[] = 'Valor inválido.';
                    break;
                }
            } elseif ($rule === 'alnum_invite') {
                if (!preg_match('/^[a-zA-Z0-9_-]{1,128}$/', $str)) {
                    $errors[] = 'Código de convite inválido.';
                    break;
                }
            } elseif (str_starts_with($rule, 'min:')) {
                $n = (int) substr($rule, 4);
                if (strlen($str) < $n) {
                    $errors[] = 'Senha muito curta (mínimo ' . $n . ' caracteres).';
                    break;
                }
            } elseif (str_starts_with($rule, 'max:')) {
                $n = (int) substr($rule, 4);
                if (strlen($str) > $n) {
                    $str = substr($str, 0, $n);
                }
            }
        }

        $data[$field] = $str;
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'data' => $data,
    ];
}
