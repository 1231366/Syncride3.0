<?php
// Auth/Auth.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'dbconfig.php';

// Detetar chamada AJAX
$is_mobile_app_call = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_mobile_app_call) {
    header('Content-Type: application/json');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['pass']);
    $remember = isset($_POST['remember']);

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
            
            // Login na Sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            // Se existirem estas colunas na tua tabela Users:
            $_SESSION['profile_photo_path'] = $user['profile_photo_path'] ?? null;

            // Remember Me
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $upd = $pdo->prepare("UPDATE Users SET remember_token = ? WHERE id = ?");
                $upd->execute([$tokenHash, $user['id']]);
                setcookie('remember_me', $token, time() + (86400 * 30), "/", "", false, true);
            }

            // REDIRECIONAMENTO CORRIGIDO
            $redirect_route = '../index.php'; // Fallback
            if ($user['role'] == 1) {
                $redirect_route = '../Includes/dist/pages/admin.php';
            } elseif ($user['role'] == 2) {
                $redirect_route = '../Includes/dist/pages/driver.php';
            } elseif ($user['role'] == 3) {
                $redirect_route = '../Includes/dist/pages/partner.php'; // Nova rota
            } else {
                header("Location: ../index.php?error=role_invalida");
                exit();
            }

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
        else header("Location: ../index.php?error=erro_bd");
        exit();
    }
} else {
    header("Location: ../index.php");
}
?>