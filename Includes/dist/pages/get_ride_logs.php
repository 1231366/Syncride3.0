<?php
// Ficheiro: get_ride_logs.php
// Caminho: Includes/dist/pages/get_ride_logs.php

header('Content-Type: application/json');

// Ajusta o caminho se necessário.
// Se este ficheiro está em 'Includes/dist/pages/', e o 'dbconfig.php' está em 'auth/', então:
// ../../../auth/dbconfig.php é o caminho correto.
require __DIR__ . '/../../../auth/dbconfig.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT ts_start_pickup, ts_arrived_pickup, ts_start_trip, ts_completed FROM Services WHERE ID = ?");
        $stmt->execute([$id]);
        $logs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($logs) {
            echo json_encode(['success' => true, 'data' => $logs]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Viagem não encontrada']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro SQL: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
}
?>