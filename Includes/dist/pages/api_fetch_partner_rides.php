<?php
session_start();
// 1. Caminho corrigido para a base de dados
require __DIR__ . '/../../../auth/dbconfig.php';

// 2. Garantir que devolve JSON mesmo se houver erro
header('Content-Type: application/json');

// Desativar output de erros HTML para não quebrar o JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['data' => []]);
    exit;
}

$status = $_GET['status'] ?? 'pendente';
$partner_id = $_SESSION['user_id'];

try {
    // 3. Query com os nomes EXATOS das colunas da tua BD
    // (serviceDate, serviceStartTime, etc.)
    $stmt = $pdo->prepare("SELECT * FROM Services WHERE partner_id = ? AND status_pedido = ? ORDER BY serviceDate DESC, serviceStartTime DESC");
    $stmt->execute([$partner_id, $status]);
    $rides = $stmt->fetchAll();

    $data = [];
    foreach ($rides as $ride) {
        // Classes visuais para o estado
        $badgeClass = match($ride['status_pedido']) {
            'pendente' => 'bg-pendente',
            'aprovado' => 'bg-aprovado',
            'rejeitado' => 'bg-rejeitado',
            default => 'bg-light'
        };

        $statusLabel = ucfirst($ride['status_pedido']);
        if($ride['status_pedido'] == 'pendente') $statusLabel = 'pendente';

        // Formatar Data e Hora
        $dataHora = date('d/m/Y H:i', strtotime($ride['serviceDate'] . ' ' . $ride['serviceStartTime']));

        $data[] = [
            'data_hora' => $dataHora,
            'cliente' => htmlspecialchars($ride['NomeCliente']), // Corrigido de 'name' para 'NomeCliente'
            'rota' => '<div class="d-flex flex-column text-start small">' . 
                      '<span class="text-truncate" style="max-width:150px"><i class="bi bi-geo-alt-fill text-success me-1"></i>' . htmlspecialchars($ride['serviceStartPoint']) . '</span>' .
                      '<span class="text-truncate" style="max-width:150px"><i class="bi bi-pin-map-fill text-danger me-1"></i>' . htmlspecialchars($ride['serviceTargetPoint']) . '</span></div>',
            'voo' => !empty($ride['FlightNumber']) ? '<span class="badge bg-light text-dark border">'.htmlspecialchars($ride['FlightNumber']).'</span>' : '-',
            'pax' => '<i class="bi bi-people-fill text-muted me-1"></i> ' . ($ride['paxADT'] + $ride['paxCHD']),
            'status' => "<span class='status-badge $badgeClass'>$statusLabel</span>"
        ];
    }
    echo json_encode(['data' => $data]);

} catch (PDOException $e) {
    // Em caso de erro SQL, devolver JSON válido com o erro (para debug no Network tab)
    echo json_encode(['data' => [], 'error' => $e->getMessage()]);
}
?>