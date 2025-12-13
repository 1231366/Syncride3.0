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
    $stmt = $pdo->prepare("SELECT sender_type, message, timestamp FROM ChatMessages WHERE ride_id = ? ORDER BY timestamp ASC");
    $stmt->execute([$rideId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Marcar Mensagens como Lidas pelo Utilizador Solicitante
    $readColumn = ($userType === 'driver') ? 'is_read_by_driver' : 'is_read_by_client';
    $otherSender = ($userType === 'driver') ? 'client' : 'driver';

    // Marcar como lida as mensagens enviadas pelo "outro" que ainda não foram lidas
    // Nota: Usamos bindParam ou bindValue aqui para evitar problemas de segurança com nomes de colunas.
    $sql = "UPDATE ChatMessages SET {$readColumn} = 1 WHERE ride_id = ? AND sender_type = ? AND {$readColumn} = 0";
    $stmt = $pdo->prepare($sql);
    
    // Usamos bindValue aqui para garantir que a coluna é tratada corretamente.
    // O nome da coluna ($readColumn) foi sanitizado manualmente, mas a PDO ajuda a proteger os outros parâmetros.
    $stmt->bindValue(1, $rideId, PDO::PARAM_INT);
    $stmt->bindValue(2, $otherSender, PDO::PARAM_STR);
    $stmt->execute();


    echo json_encode(['success' => true, 'messages' => $messages]);

} catch (PDOException $e) {
    error_log("Chat Fetch Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>