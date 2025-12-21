<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

require 'auth/dbconfig.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['ride_id'], $data['rating'])) {
    try {
        $stmt = $pdo->prepare("UPDATE Services SET driver_rating = ? WHERE ID = ?");
        $stmt->execute([$data['rating'], $data['ride_id']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
}
?>