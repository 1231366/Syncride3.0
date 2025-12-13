<?php
// api_fetch_messages.php (No diretório raiz)
session_start();
header('Content-Type: application/json');
require 'auth/dbconfig.php';

$rideId = $_GET['ride_id'] ?? null;
$userType = $_GET['user_type'] ?? null; // 'client' or 'driver'

if (!$rideId || !in_array($userType, ['client', 'driver'])) {
    http_response_code(400); echo json_encode(['success' => false, 'error' => 'Missing ride_id or user_type']); exit();
}

try {
    // 1. Obter Mensagens (apenas o que é necessário para o frontend)
    // O campo timestamp é crucial para a ordem e para exibir o horário da mensagem.
    $stmt = $pdo->prepare("SELECT sender_type, message, timestamp FROM ChatMessages WHERE ride_id = ? ORDER BY timestamp ASC");
    $stmt->execute([$rideId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Marcar Mensagens como Lidas pelo Utilizador Solicitante
    $readColumn = ($userType === 'driver') ? 'is_read_by_driver' : 'is_read_by_client';
    $otherSender = ($userType === 'driver') ? 'client' : 'driver';

    // Marcar como lida as mensagens enviadas pelo "outro" que ainda não foram lidas
    $stmt = $pdo->prepare("UPDATE ChatMessages SET {$readColumn} = 1 WHERE ride_id = ? AND sender_type = ? AND {$readColumn} = 0");
    $stmt->execute([$rideId, $otherSender]);

    echo json_encode(['success' => true, 'messages' => $messages]);

} catch (PDOException $e) {
    error_log("Chat Fetch Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>