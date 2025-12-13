<?php
// Auth/dbconfig.php

// Enable error display for debugging (remove / disable in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Configurações da base de dados
$host = 'localhost';
$dbname = 'SyncRide3';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    // Não mostrar erros na tela em produção, usar log
    error_log("Erro na conexão: " . $e->getMessage());
    die("Erro de conexão. Tente mais tarde.");
}

// --- SISTEMA DE AUTO-LOGIN (REMEMBER ME) ---
// Inicia sessão APENAS se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se não estiver logado, mas tiver o cookie, tenta logar
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $cookieToken = $_COOKIE['remember_me'];
    $tokenHash = hash('sha256', $cookieToken);

    try {
        // Selecionar os novos campos
        $stmt = $pdo->prepare("SELECT id, email, role, name, profile_photo_path, assigned_vehicle_id FROM Users WHERE remember_token = ?");
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['profile_photo_path'] = $user['profile_photo_path']; // NOVO
            $_SESSION['assigned_vehicle_id'] = $user['assigned_vehicle_id']; // NOVO
            
            // Renovar o cookie de forma segura
            setcookie('remember_me', $cookieToken, [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'secure' => false, // Mudar para true em HTTPS
                'httponly' => true, 
                'samesite' => 'Lax'
            ]);
        }
    } catch (Exception $e) {
        error_log("Auto-login error: " . $e->getMessage());
    }
}
?>