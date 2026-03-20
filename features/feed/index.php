<?php
require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
$posts = [];

$ch = curl_init(SUPABASE_URL . '/rest/v1/posts?select=id,user_id,image_url,caption,created_at&order=created_at.desc');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_ANON_KEY,
    'Authorization: Bearer ' . $_SESSION['access_token'],
]);

$rawPosts = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($rawPosts !== false && $statusCode >= 200 && $statusCode < 300) {
    $decoded = json_decode($rawPosts, true);
    if (is_array($decoded)) {
        $posts = $decoded;
    }
}
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

    <hr>

    <h2>Posts</h2>
    <?php if (empty($posts)): ?>
        <p>Nenhum post encontrado.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <article style="margin-bottom: 24px;">
                <p><strong>Usuario:</strong> <?php echo htmlspecialchars((string) ($post['user_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (!empty($post['image_url'])): ?>
                    <img
                        src="<?php echo htmlspecialchars($post['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Imagem do post"
                        style="max-width: 320px; width: 100%; display: block;"
                    >
                <?php endif; ?>
                <?php if (!empty($post['caption'])): ?>
                    <p><?php echo htmlspecialchars($post['caption'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <form action="like.php" method="POST">
                    <input type="hidden" name="post_id" value="<?php echo (int) ($post['id'] ?? 0); ?>">
                    <button type="submit">Curtir</button>
                </form>
                <form action="unlike_post.php" method="POST">
                    <input type="hidden" name="post_id" value="<?php echo (int) ($post['id'] ?? 0); ?>">
                    <button type="submit">Descurtir</button>
                </form>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>