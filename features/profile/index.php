<?php
require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? null;
$profile_user_id = $_GET['user'] ?? $current_user_id;
$invites = [];

$url = SUPABASE_URL . '/rest/v1/invites?created_by=eq.' . urlencode((string) $current_user_id);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_ANON_KEY,
    'Authorization: Bearer ' . $_SESSION['access_token'],
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false && $statusCode >= 200 && $statusCode < 300) {
    $decoded = json_decode($response, true);
    if (is_array($decoded)) {
        $invites = $decoded;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil</title>
</head>
<body>
    <h1>Perfil</h1>

    <?php if ($status === 'ok'): ?>
        <p style="color: #2f9e44;"><?php echo htmlspecialchars($message ?: 'Operacao realizada com sucesso.', ENT_QUOTES, 'UTF-8'); ?></p>
    <?php elseif ($status === 'error'): ?>
        <p style="color: #e03131;"><?php echo htmlspecialchars($message ?: 'Erro ao processar operacao.', ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <p><strong>Usuario do perfil:</strong> <?php echo htmlspecialchars((string) $profile_user_id, ENT_QUOTES, 'UTF-8'); ?></p>

    <?php if ((string) $profile_user_id !== (string) $current_user_id): ?>
        <form action="follow.php" method="POST">
            <input type="hidden" name="followed_id" value="<?= htmlspecialchars((string) $profile_user_id, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Seguir</button>
        </form>
    <?php endif; ?>

    <form action="generate_invite.php" method="POST">
        <button type="submit">Gerar convite</button>
    </form>

    <h2>Meus convites</h2>
    <?php if (empty($invites)): ?>
        <p>Nenhum convite encontrado.</p>
    <?php else: ?>
        <?php foreach ($invites as $invite): ?>
            <div>
                Código: <b><?php echo htmlspecialchars((string) ($invite['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></b>
                Status: <?php echo htmlspecialchars((string) ($invite['status'] ?? 'indefinido'), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>