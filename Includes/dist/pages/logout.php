<?php
require_once __DIR__ . '/../../../auth/dbconfig.php';
session_start();

// 1. Limpar Token da BD
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // Ignorar erro no logout
    }
}

// 2. Destruir Sessão
session_unset();
session_destroy();

// 3. Destruir Cookie
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Redirecionar
header("Location: ../../../index.php");
exit();
?>