<?php
require __DIR__ . '/../../../auth/dbconfig.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obter todos os campos do formulário
    $viagemId = $_POST['edit_trip_id'] ?? null;
    $dataHora = $_POST['edit_departure_datetime'] ?? null;
    $localRecolha = $_POST['edit_origin'] ?? null;
    $localEntrega = $_POST['edit_destination'] ?? null;
    
    // Campos adicionais
    $paxADT = $_POST['edit_paxADT'] ?? 0;
    $paxCHD = $_POST['edit_paxCHD'] ?? 0;
    $flightNumber = $_POST['edit_flightNumber'] ?? null;
    $nomeCliente = $_POST['edit_clientName'] ?? null;
    $clientNumber = $_POST['edit_clientNumber'] ?? null;

    // NOVO: Receber o preço da edição
    $totalPrice = !empty($_POST['edit_totalPrice']) ? $_POST['edit_totalPrice'] : null;

    if ($viagemId && $dataHora) {
        try {
            // Separar data e hora corretamente
            list($data, $hora) = explode('T', $dataHora); 
            $hora .= ":00"; // Adiciona segundos

            // SQL ATUALIZADO
            $sql = "UPDATE Services 
                    SET serviceDate = :data, 
                        serviceStartTime = :hora, 
                        serviceStartPoint = :startPoint, 
                        serviceTargetPoint = :targetPoint,
                        paxADT = :paxADT,
                        paxCHD = :paxCHD,
                        FlightNumber = :flightNumber,
                        NomeCliente = :nomeCliente,
                        ClientNumber = :clientNumber,
                        total_price = :totalPrice
                    WHERE ID = :rideID";

            $stmt = $pdo->prepare($sql);
            
            // Bind dos parâmetros
            $stmt->bindParam(':data', $data, PDO::PARAM_STR);
            $stmt->bindParam(':hora', $hora, PDO::PARAM_STR);
            $stmt->bindParam(':startPoint', $localRecolha, PDO::PARAM_STR);
            $stmt->bindParam(':targetPoint', $localEntrega, PDO::PARAM_STR);
            $stmt->bindParam(':rideID', $viagemId, PDO::PARAM_INT);

            $stmt->bindParam(':paxADT', $paxADT, PDO::PARAM_INT);
            $stmt->bindParam(':paxCHD', $paxCHD, PDO::PARAM_INT);
            $stmt->bindParam(':flightNumber', $flightNumber, PDO::PARAM_STR);
            $stmt->bindParam(':nomeCliente', $nomeCliente, PDO::PARAM_STR);
            $stmt->bindParam(':clientNumber', $clientNumber, PDO::PARAM_STR);
            
            // NOVO BIND
            $stmt->bindParam(':totalPrice', $totalPrice, PDO::PARAM_STR);
            
            $stmt->execute();

            // Redireciona de volta com sucesso
            header('Location: ManageRides.php?success=rideUpdated');
            exit();

        } catch (PDOException $e) {
            header('Location: ManageRides.php?success=false&message=' . urlencode('Erro ao atualizar viagem: ' . $e->getMessage()));
            exit();
        }
    } else {
        header('Location: ManageRides.php?success=false&message=' . urlencode('Dados inválidos! Faltou o ID ou a Data/Hora.'));
        exit();
    }
} else {
    header('Location: ManageRides.php?success=false&message=' . urlencode('Método não permitido!'));
    exit();
}
?>