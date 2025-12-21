<?php
session_start();
// 1. Caminho corrigido
require __DIR__ . '/../../../auth/dbconfig.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 3) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    // Validação básica
    if(empty($_POST['date']) || empty($_POST['time']) || empty($_POST['client_name'])) {
        throw new Exception("Preencha os campos obrigatórios");
    }

    // 2. Query ATUALIZADA com 'ClientNumber' e 'total_price'
    // Note que adicionei as colunas no INSERT e os placeholders (?, ?) no VALUES
    $stmt = $pdo->prepare("INSERT INTO Services 
        (serviceDate, serviceStartTime, serviceStartPoint, serviceTargetPoint, paxADT, paxCHD, NomeCliente, FlightNumber, partner_id, status_pedido, serviceType, ClientNumber, total_price) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', 1, ?, ?)");

    $result = $stmt->execute([
        $_POST['date'],              // serviceDate
        $_POST['time'],              // serviceStartTime
        $_POST['pickup'],            // serviceStartPoint
        $_POST['dropoff'],           // serviceTargetPoint
        $_POST['pax_adt'],           // paxADT
        $_POST['pax_chd'] ?? 0,      // paxCHD
        $_POST['client_name'],       // NomeCliente
        $_POST['flight'] ?? '',      // FlightNumber
        $_SESSION['user_id'],        // partner_id
        
        // NOVOS CAMPOS:
        $_POST['client_phone'] ?? '', // ClientNumber (Usei 'client_phone' como nome esperado do POST)
        $_POST['price'] ?? null       // total_price (Usei 'price' como nome esperado do POST)
    ]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro na inserção SQL']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>