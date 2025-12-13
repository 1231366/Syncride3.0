<?php
require __DIR__ . '/../../../auth/dbconfig.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password_raw = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? '';

    if (empty($name) || empty($email) || empty($password_raw) || empty($phone) || empty($role)) {
        die("Erro: Todos os campos são obrigatórios.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Erro: Endereço de email inválido.");
    }

    if (!preg_match('/^\+?\d{9,15}$/', $phone)) {
        die("Erro: Número de telefone inválido.");
    }

    $password = password_hash($password_raw, PASSWORD_BCRYPT);
    $role = intval($role);

    try {
        $stmt = $pdo->prepare("INSERT INTO Users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $phone, $role]);

        header("Location: manageUsers.php?success=user_created");
        exit();
    } catch (PDOException $e) {
        echo "Erro ao adicionar utilizador: " . $e->getMessage();
        exit();
    }
}
?>
