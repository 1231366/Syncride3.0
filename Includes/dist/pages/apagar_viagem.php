<?php
require __DIR__ . '/../../../auth/dbconfig.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $viagemId = $_GET['id'] ?? null;

    if ($viagemId) {
        try {
            // Primeiro, verificar se a viagem tem um condutor associado
            $stmt = $pdo->prepare("SELECT UserID FROM Services_Rides WHERE RideID = :rideID");
            $stmt->bindParam(':rideID', $viagemId, PDO::PARAM_INT);
            $stmt->execute();
            $condutor = $stmt->fetch(PDO::FETCH_ASSOC);

            // Se a viagem tiver um condutor, remover a associação
            if ($condutor) {
                $stmt = $pdo->prepare("DELETE FROM Services_Rides WHERE RideID = :rideID");
                $stmt->bindParam(':rideID', $viagemId, PDO::PARAM_INT);
                $stmt->execute();
            }

            // Agora, apagar a viagem da tabela Services
            $stmt = $pdo->prepare("DELETE FROM Services WHERE ID = :rideID");
            $stmt->bindParam(':rideID', $viagemId, PDO::PARAM_INT);
            $stmt->execute();

            // Redirecionar para a página de gestão de viagens
            header("Location: ManageRides.php?success=ViagemaApagada");
            exit();
        } catch (PDOException $e) {
            // Redirecionar com erro
            header("Location: ManageRides.php?error=" . urlencode("Erro ao apagar viagem: " . $e->getMessage()));
            exit();
        }
    } else {
        header("Location: ManageRides.php?error=" . urlencode("Dados inválidos!"));
        exit();
    }
} else {
    header("Location: ManageRides.php?error=" . urlencode("Método não permitido!"));
    exit();
}
?>
