<?php
require __DIR__ . '/../../../auth/dbconfig.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Receber os dados do formulário
    $serviceDate = $_POST['serviceDate'];
    $serviceStartTime = $_POST['serviceStartTime'];
    $paxADT = $_POST['paxADT'];
    $paxCHD = $_POST['paxCHD'];
    $serviceStartPoint = $_POST['serviceStartPoint'];
    $serviceTargetPoint = $_POST['serviceTargetPoint'];
    $driver = $_POST['driver']; // "later" ou um ID de condutor
    $serviceType = $_POST['serviceType']; // Tipo de serviço (1 = Private, 0 = Shared)
    $flightNumber = $_POST['FlightNumber']; // Número do voo
    $nomeCliente = $_POST['NomeCliente']; // Nome do cliente
    $clientNumber = $_POST['ClientNumber']; // Número do cliente
    
    // NOVO: Receber o preço (pode ser vazio, então usamos null)
    $totalPrice = !empty($_POST['totalPrice']) ? $_POST['totalPrice'] : null;

    try {
        // Preparar a query para inserir a viagem na tabela "Services"
        $stmt = $pdo->prepare("INSERT INTO Services (serviceDate, serviceStartTime, paxADT, paxCHD, serviceStartPoint, serviceTargetPoint, serviceType, FlightNumber, NomeCliente, ClientNumber, total_price) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$serviceDate, $serviceStartTime, $paxADT, $paxCHD, $serviceStartPoint, $serviceTargetPoint, $serviceType, $flightNumber, $nomeCliente, $clientNumber, $totalPrice]);

        // Obter o ID da viagem recém-criada
        $rideId = $pdo->lastInsertId();

        // Se um condutor foi selecionado, associamos à viagem
        if ($driver !== "later") {
            $stmtDriver = $pdo->prepare("INSERT INTO Services_Rides (RideID, UserID) VALUES (?, ?)");
            $stmtDriver->execute([$rideId, $driver]);
        }

        // Redirecionar para a página de gestão de viagens com sucesso
        header("Location: ManageRides.php?success=ride_created");
        exit();
    } catch (PDOException $e) {
        die("Erro ao criar viagem: " . $e->getMessage());
    }
}
?>