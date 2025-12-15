<?php
// load_rides_data.php

// 1. SILENCIAR ERROS DE TEXTO
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require __DIR__ . '/../../../auth/dbconfig.php';

$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$data = [];

try {
    // 2. CONSTRUÇÃO DA QUERY COM LEFT JOIN
    // Adicionado s.total_price
    $sql = "
        SELECT 
            s.ID, 
            s.serviceDate, 
            s.serviceStartTime, 
            s.serviceStartPoint, 
            s.serviceTargetPoint, 
            s.paxADT, 
            s.paxCHD, 
            s.FlightNumber, 
            s.NomeCliente, 
            s.ClientNumber, 
            s.serviceType,
            s.total_price,
            u.name AS DriverName,
            u.id AS DriverID
        FROM Services s
        LEFT JOIN Services_Rides sr ON s.ID = sr.RideID
        LEFT JOIN Users u ON sr.UserID = u.id
    ";

    // 3. FILTROS
    $params = [];
    $where = [];

    switch ($status) {
        case 'today':
            $where[] = "s.serviceDate = CURDATE()";
            break;
        case 'yesterday':
            $where[] = "s.serviceDate = CURDATE() - INTERVAL 1 DAY";
            break;
        case 'tomorrow':
            $where[] = "s.serviceDate = CURDATE() + INTERVAL 1 DAY";
            break;
        case 'pending': // Viagens sem condutor
            $where[] = "u.id IS NULL"; 
            break;
        case 'assigned': // Viagens com condutor
            $where[] = "u.id IS NOT NULL";
            break;
        case 'all':
        default:
            break;
    }

    if (count($where) > 0) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY s.serviceDate ASC, s.serviceStartTime ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. FORMATAR DADOS PARA O DATATABLE
    foreach ($results as $row) {
        
        // Formatar Condutor
        if ($row['DriverName']) {
            $condutorHtml = '<span class="badge bg-success" style="font-size: 0.9rem;">' . htmlspecialchars($row['DriverName']) . '</span>';
        } else {
            $condutorHtml = '<button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#atribuirCondutorModal" onclick="setViagemId('.$row['ID'].')">
                                <i class="bi bi-person-plus-fill"></i> Atribuir
                             </button>';
        }

        // Formatar Tipo (Privado/Partilhado)
        $tipoBadge = ($row['serviceType'] == 1) 
            ? '<span class="badge bg-primary">Privado</span>' 
            : '<span class="badge bg-warning text-dark">Partilhado</span>';

        // Botões de Ação (HTML)
        // Adicionado s.total_price na função editTravel
        $acoes = '
            <div class="btn-group btn-group-sm">
                <button class="btn btn-primary rounded-circle" data-bs-toggle="modal" data-bs-target="#editModal" 
                    onclick="editTravel(
                        '.$row['ID'].', 
                        \''.$row['serviceDate'].' '.$row['serviceStartTime'].'\', 
                        \''.addslashes($row['DriverName'] ?? '').'\', 
                        \''.addslashes($row['serviceStartPoint']).'\', 
                        \''.addslashes($row['serviceTargetPoint']).'\', 
                        '.$row['paxADT'].', 
                        '.$row['paxCHD'].', 
                        \''.addslashes($row['FlightNumber'] ?? '').'\', 
                        \''.addslashes($row['NomeCliente'] ?? '').'\', 
                        \''.addslashes($row['ClientNumber'] ?? '').'\', 
                        '.$row['serviceType'].',
                        \''.($row['total_price'] ?? '').'\'
                    )" title="Editar">
                    <i class="bi bi-pencil-fill"></i>
                </button>
                <button class="btn btn-danger rounded-circle ms-1" data-bs-toggle="modal" data-bs-target="#deleteTripModal" 
                    onclick="setDeleteTrip('.$row['ID'].', \''.addslashes($row['serviceStartPoint']).' -> '.addslashes($row['serviceTargetPoint']).'\')" title="Apagar">
                    <i class="bi bi-trash-fill"></i>
                </button>
                <button class="btn btn-info btn-sm rounded-circle shadow-sm ms-1" onclick="viewTripLogs('.$row['ID'].')" title="Ver Logs" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;">
                    <i class="bi bi-clock-history text-white"></i>
                </button>
            </div>
        ';

        $data[] = [
            "id" => "#" . $row['ID'],
            "data_hora" => date('d/m H:i', strtotime($row['serviceDate'] . ' ' . $row['serviceStartTime'])),
            "condutor" => $condutorHtml,
            "recolha" => htmlspecialchars($row['serviceStartPoint']),
            "entrega" => htmlspecialchars($row['serviceTargetPoint']),
            "tipo" => $tipoBadge,
            "acoes" => $acoes
        ];
    }

    echo json_encode(["data" => $data]);

} catch (Exception $e) {
    echo json_encode(["data" => [], "error" => $e->getMessage()]);
}
?>