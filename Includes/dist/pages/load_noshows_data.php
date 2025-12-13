<?php
session_start();
require __DIR__ . '/../../../auth/dbconfig.php';

// Apenas admins podem aceder
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    echo json_encode(['data' => []]);
    exit();
}

try {
    // ===================================
    // CORREÇÃO DA CONSULTA SQL (LEFT JOIN)
    // ===================================
    // Isto garante que apanhamos TODOS os 'No Shows',
    // mesmo que não tenham um condutor atribuído na tabela Services_Rides.
    $sql = "SELECT 
                s.ID, s.serviceDate, s.serviceStartTime, 
                s.serviceStartPoint, s.serviceTargetPoint, 
                s.noShowPhotoPath,
                u.name AS driverName
            FROM Services s
            LEFT JOIN Services_Rides sr ON s.ID = sr.RideID
            LEFT JOIN Users u ON sr.UserID = u.ID
            WHERE s.noShowStatus = 1
            ORDER BY s.serviceDate DESC, s.serviceStartTime DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $viagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($viagens as $viagem) {
        
        // Formatar Colunas
        $data_hora = htmlspecialchars($viagem['serviceDate'] . ' ' . substr($viagem['serviceStartTime'], 0, 5));
        
        // Lidar com condutor NULO
        $driver_name_text = $viagem['driverName'] ? htmlspecialchars($viagem['driverName']) : 'N.A.';
        $condutor_html = '<span class="badge text-bg-secondary">' . $driver_name_text . '</span>';
        
        $rota_html = htmlspecialchars($viagem['serviceStartPoint'] . ' → ' . $viagem['serviceTargetPoint']);

        // --- PREPARAÇÃO PARA O EMAIL (Mailto) ---
        // O link do email foi removido
        

        // Formatar Ações (Botões de Ícone)
        $acoes_html = '<div class="btn-group-sm d-flex justify-content-center">';
        
        // Botão Ver Foto
        $acoes_html .= '<a href="#" class="btn btn-info rounded-circle" 
                           data-bs-toggle="tooltip" title="Ver Foto"
                           onclick="event.preventDefault(); openPhotoModal(' . $viagem['ID'] . ', \'' . htmlspecialchars($viagem['noShowPhotoPath']) . '\');">
                           <i class="bi bi-camera-fill"></i>
                        </a>';

        // --- NOVA ALTERAÇÃO: Botão de Download ---
        // O 'download' attribute força o browser a transferir em vez de navegar
        $download_filename = 'NoShow-Viagem-' . $viagem['ID'] . '.jpg';
        $acoes_html .= '<a href="' . htmlspecialchars($viagem['noShowPhotoPath']) . '" class="btn btn-success rounded-circle" 
                           data-bs-toggle="tooltip" title="Transferir Foto"
                           download="' . $download_filename . '">
                           <i class="bi bi-download"></i>
                        </a>';
        // --- FIM DA ALTERAÇÃO ---
        
        $acoes_html .= '</div>';


        // Adiciona a linha formatada para o DataTables
        $data[] = [
            'id' => $viagem['ID'],
            'data_hora' => $data_hora,
            'condutor' => $condutor_html,
            'rota' => $rota_html,
            'acoes' => $acoes_html
        ];
    }

    // Retorna o JSON formatado
    echo json_encode(['data' => $data]);

} catch (PDOException $e) {
    // Se a consulta SQL falhar, o script entra aqui e devolve "data" vazia.
    // É ISTO QUE ESTÁ A ACONTECER!
    echo json_encode(['data' => [], 'error' => $e->getMessage()]);
}
?>