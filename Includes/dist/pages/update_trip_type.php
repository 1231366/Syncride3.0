<?php
// Conexão ao banco de dados
require __DIR__ . '/../../../auth/dbconfig.php';

// Verificar se o ID da viagem e o tipo foram enviados via POST
if (isset($_POST['tripId']) && isset($_POST['tripType'])) {
    $tripId = $_POST['tripId'];
    $tripType = $_POST['tripType'];

    // Query para atualizar o tipo de viagem na tabela Services
    $query = "UPDATE Services SET serviceType = ? WHERE ID = ?";
    $stmt = $pdo->prepare($query);

    // Executar o UPDATE no banco de dados
    if ($stmt->execute([$tripType, $tripId])) {
        // Redireciona para a página ManageRides.php em caso de sucesso
        header("Location: ManageRides.php?success=TypeChanged");
        exit(); // Garantir que o script seja interrompido após o redirecionamento
    } else {
        // Se falhar, redireciona para ManageRides.php com erro
        header("Location: ManageRides.php?success=false");
        exit(); // Garantir que o script seja interrompido após o redirecionamento
    }
} else {
    // Se os dados não forem válidos, redireciona com erro
    header("Location: ManageRides.php?success=false");
    exit(); // Garantir que o script seja interrompido após o redirecionamento
}
?>
