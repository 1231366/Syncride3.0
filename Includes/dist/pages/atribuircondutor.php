<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../../../auth/dbconfig.php';

// Verifica se os dados foram recebidos via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Receber os dados enviados pelo frontend
    $viagemId = isset($_POST['viagemId']) ? $_POST['viagemId'] : null;
    $condutorId = isset($_POST['condutorId']) ? $_POST['condutorId'] : null;

    // Verifica se os dados são válidos
    if ($viagemId && $condutorId) {
        try {
            // 1. Deletar qualquer associação existente entre a viagem e qualquer condutor
            $stmtDelete = $pdo->prepare("DELETE FROM Services_Rides WHERE RideID = ?");
            $stmtDelete->execute([$viagemId]);

            // 2. Inserir a nova associação
            $stmtInsert = $pdo->prepare("INSERT INTO Services_Rides (RideID, UserID) VALUES (?, ?)");
            $stmtInsert->execute([$viagemId, $condutorId]);

            // Redireciona de volta para a página de gerenciamento de viagens com sucesso
            header('Location: ManageRides.php?success=viagemAtribuida');
            exit; // Sempre chame exit após um redirecionamento

        } catch (PDOException $e) {
            // Em caso de erro, redireciona de volta para a página de gerenciamento de viagens
            header('Location: ManageRides.php?error=erroAoAtribuirCondutor');
            exit; // Sempre chame exit após um redirecionamento
        }
    } else {
        // Se faltar algum dado, redireciona de volta para a página de gerenciamento de viagens
        header('Location: ManageRides.php?error=dadosInvalidos');
        exit; // Sempre chame exit após um redirecionamento
    }
} else {
    // Se a requisição não for POST, redireciona de volta para a página de gerenciamento de viagens
    header('Location: ManageRides.php');
    exit; // Sempre chame exit após um redirecionamento
}
?>
