<?php
session_start();
require __DIR__ . '/../../../auth/dbconfig.php';

header('Content-Type: application/json');

// Apenas Admin (Role 1)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Proibido']);
    exit;
}

$id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? null; // 'approve' ou 'reject'

if (!$id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    if ($action === 'approve') {
        // Aprovar muda o estado para 'aprovado' -> Passa a ser visível na escala normal
        $stmt = $pdo->prepare("UPDATE Services SET status_pedido = 'aprovado' WHERE id = ?");
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE Services SET status_pedido = 'rejeitado' WHERE id = ?");
    } else {
        throw new Exception("Ação desconhecida");
    }
    
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>