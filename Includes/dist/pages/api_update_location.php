<?php
// Includes/dist/pages/api_update_location.php
header('Content-Type: application/json');
require __DIR__ . '/../../../auth/dbconfig.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['ride_id'], $data['lat'], $data['lng'], $data['driver_id'])) {
    try {
        // Usa "ON DUPLICATE KEY UPDATE" para manter apenas a ULTIMA localização
        $sql = "INSERT INTO RideTracking (ride_id, driver_id, latitude, longitude, speed, heading) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                latitude = VALUES(latitude), 
                longitude = VALUES(longitude), 
                speed = VALUES(speed), 
                heading = VALUES(heading),
                last_update = NOW()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['ride_id'], 
            $data['driver_id'], 
            $data['lat'], 
            $data['lng'], 
            $data['speed'] ?? 0, 
            $data['heading'] ?? 0
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>