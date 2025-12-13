<?php
header('Content-Type: application/json');
// Desativa erros de HTML para não estragar o JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

require __DIR__ . '/../../../auth/dbconfig.php';

$rideId = $_GET['ride_id'] ?? null;

try {
    if ($rideId) {
        // MODO CLIENTE (Link específico)
        $stmt = $pdo->prepare("
            SELECT t.*, s.serviceTargetPoint 
            FROM RideTracking t 
            LEFT JOIN Services s ON t.ride_id = s.ID 
            WHERE t.ride_id = ?
        ");
        $stmt->execute([$rideId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se não encontrar, devolve array vazio em vez de erro
        if(!$data) $data = null;
        
        echo json_encode(['success' => true, 'data' => $data]);

    } else {
        // MODO ADMIN (Ver tudo)
        // Removi o WHERE de tempo e mudei para LEFT JOIN para garantir que aparece tudo
        $sql = "
            SELECT 
                t.*, 
                COALESCE(u.name, 'Condutor ' || t.driver_id) as driver_name, 
                COALESCE(s.NomeCliente, 'Desconhecido') as NomeCliente,
                COALESCE(s.serviceTargetPoint, 'N/A') as serviceTargetPoint
            FROM RideTracking t 
            LEFT JOIN Users u ON t.driver_id = u.id 
            LEFT JOIN Services s ON t.ride_id = s.ID
        ";
        
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>