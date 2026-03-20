<?php
require_once __DIR__ . '/../../auth_guard.php';

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? null;
$profile_user_id = $_GET['user'] ?? $current_user_id;
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
</body>
</html>