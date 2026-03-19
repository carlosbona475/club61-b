<?php

require_once __DIR__ . '/index.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errorMessage = 'Informe email e senha.';
    } else {
        $result = registerUser($email, $password);

        if ($result['success']) {
            header('Location: /features/auth/login.php');
            exit;
        } else {
            $errorMessage = $result['error'] ?? 'Nao foi possivel criar a conta.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página de Registro</title>
    <style>
        body {
            background: #0A0A0A;
            color: white;
            font-family: Arial, sans-serif;
        }
        .button {
            background-color: #7B2EFF;
            transition: box-shadow 0.3s;
        }
        .button:hover {
            box-shadow: 0 0 20px #7B2EFF;
        }
    </style>
</head>
<body>
    <h1>Registro</h1>
    <?php if ($errorMessage !== ''): ?>
        <p style="color: #ff6b6b;"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <form action="" method="POST">
        <input type="text" name="invite_code" placeholder="Código de Convite" required />
        <input type="text" name="email" placeholder="Email" required />
        <input type="password" name="password" placeholder="Senha" required />
        <button class="button" type="submit">Registrar</button>
    </form>
</body>
</html>