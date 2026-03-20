<?php
require_once __DIR__ . '/../../auth_guard.php';

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed</title>
</head>
<body>
    <h1>Feed</h1>

    <?php if ($status === 'ok'): ?>
        <p style="color: #2f9e44;"><?php echo htmlspecialchars($message ?: 'Post criado com sucesso.', ENT_QUOTES, 'UTF-8'); ?></p>
    <?php elseif ($status === 'error'): ?>
        <p style="color: #e03131;"><?php echo htmlspecialchars($message ?: 'Erro ao criar post.', ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form action="create_post.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="image" required>
        <input type="text" name="caption" placeholder="Legenda...">
        <button type="submit">Postar</button>
    </form>
</body>
</html>