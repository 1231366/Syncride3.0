<?php
// Auth/Auth.php

// Em produção, desativar display_errors para evitar que warnings quebrem o JSON/Header
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'dbconfig.php';

// session_start() JÁ FOI CHAMADO NO DBCONFIG.PHP
// Não precisas de chamar de novo aqui.

// Detetar chamada AJAX (App Mobile)
$is_mobile_app_call = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_mobile_app_call) {
    header('Content-Type: application/json');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['pass']);
    $remember = isset($_POST['remember']); // Verifica a checkbox

    if (empty($email) || empty($password)) {
        if ($is_mobile_app_call) echo json_encode(['success' => false, 'message' => 'Campos vazios.']);
        else header("Location: ../index.php?error=campos_vazios");
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            
            // 1. Login na Sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            // 2. Lógica "Guardar Sessão"
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);

                $upd = $pdo->prepare("UPDATE Users SET remember_token = ? WHERE id = ?");
                $upd->execute([$tokenHash, $user['id']]);

                setcookie('remember_me', $token, time() + (86400 * 30), "/", "", false, true);
            }

            $redirect_route = ($user['role'] == 1) ? '../Includes/dist/pages/admin.php' : '../Includes/dist/pages/driver.php';

            if ($is_mobile_app_call) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Login OK',
                    'user' => $user,
                    'redirect_route' => $redirect_route
                ]);
            } else {
                header("Location: " . $redirect_route);
            }
            exit();

        } else {
            if ($is_mobile_app_call) echo json_encode(['success' => false, 'message' => 'Dados incorretos.']);
            else header("Location: ../index.php?error=senha_incorreta");
            exit();
        }

    } catch (PDOException $e) {
        if ($is_mobile_app_call) echo json_encode(['success' => false, 'message' => 'Erro BD.']);
        else header("Location: ../index.php?error=erro_bd"); // Redireciona em vez de echo
        exit();
    }
} else {
    header("Location: ../index.php");
}
?>