<?php
require __DIR__ . '/../../../auth/dbconfig.php';

if (isset($_GET['id'])) {
    $userId = $_GET['id'];

    try {
        // Preparar a consulta para excluir o usuário
        $stmt = $pdo->prepare("DELETE FROM Users WHERE id = ?");
        $stmt->execute([$userId]);

        // Redirecionar para a página de usuários com a mensagem de sucesso
        header("Location: manageUsers.php?success=user_deleted");
        exit();
    } catch (PDOException $e) {
        die("Erro ao apagar utilizador: " . $e->getMessage());
    }
} else {
    // Se o ID não estiver presente, redireciona para a página de usuários com erro
    header("Location: manageUsers.php?error=no_user_id");
    exit();
}
