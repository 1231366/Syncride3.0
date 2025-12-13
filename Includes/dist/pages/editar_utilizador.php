<?php
require __DIR__ . '/../../../auth/dbconfig.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Receber os dados do formulário
    $userId = $_POST['id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Se a password não foi alterada, mantemos a antiga
    if (empty($password)) {
        // Buscar a password antiga
        $stmt = $pdo->prepare("SELECT password FROM Users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $password = $user['password'];
    } else {
        // Se foi alterada, fazemos o hash da nova password
        $password = password_hash($password, PASSWORD_DEFAULT);
    }

    try {
        // Preparar a query para atualizar os dados do utilizador
        $stmt = $pdo->prepare("UPDATE Users SET name = ?, email = ?, phone = ?, role = ?, password = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $role, $password, $userId]);

        // Redirecionar para a página de gestão de utilizadores com sucesso
        header("Location: manageUsers.php?success=user_updated");
        exit();
    } catch (PDOException $e) {
        die("Erro ao atualizar utilizador: " . $e->getMessage());
    }
}
?>
