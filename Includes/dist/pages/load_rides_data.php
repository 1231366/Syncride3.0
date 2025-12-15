<?php
session_start();
require __DIR__ . '/../../../auth/dbconfig.php';

// Apenas admins podem aceder a estes dados
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    echo json_encode(['data' => []]); 
    exit();
}

// Filtro vindo do AJAX
$status = $_GET['status'] ?? 'pending'; 

$sql = "";
$params = [];

// --- QUERY BUILDER ---

if ($status === 'requests') {
    // NOVA ABA: Pedidos Pendentes (Parceiros)
    $sql = "SELECT 
                s.ID, s.serviceDate, s.serviceStartTime, s.paxADT, s.paxCHD, 
                s.serviceStartPoint, s.serviceTargetPoint, s.FlightNumber, 
                s.NomeCliente, s.ClientNumber, s.serviceType, s.total_price,
                s.status_pedido,
                NULL AS driverName,
                p.name AS partner_name
            FROM Services s
            LEFT JOIN Users p ON s.partner_id = p.ID
            WHERE s.status_pedido = 'pendente'
            ORDER BY s.serviceDate ASC, s.serviceStartTime ASC";

} elseif ($status === 'today') {
    // Viagens de HOJE (apenas aprovadas)
    $sql = "SELECT 
                s.ID, s.serviceDate, s.serviceStartTime, s.paxADT, s.paxCHD, 
                s.serviceStartPoint, s.serviceTargetPoint, s.FlightNumber, 
                s.NomeCliente, s.ClientNumber, s.serviceType, s.total_price,
                s.status_pedido,
                u.name AS driverName,
                p.name AS partner_name
            FROM Services s
            LEFT JOIN Services_Rides sr ON s.ID = sr.RideID
            LEFT JOIN Users u ON sr.UserID = u.ID
            LEFT JOIN Users p ON s.partner_id = p.ID
            WHERE s.serviceDate = CURDATE()
            AND (s.status_pedido = 'aprovado' OR s.status_pedido IS NULL)
            ORDER BY s.serviceStartTime ASC";

} elseif ($status === 'pending') {
    // Viagens POR ATRIBUIR (sem condutor, apenas aprovadas)
    $sql = "SELECT 
                s.ID, s.serviceDate, s.serviceStartTime, s.paxADT, s.paxCHD, 
                s.serviceStartPoint, s.serviceTargetPoint, s.FlightNumber, 
                s.NomeCliente, s.ClientNumber, s.serviceType, s.total_price,
                s.status_pedido,
                NULL AS driverName,
                p.name AS partner_name
            FROM Services s
            LEFT JOIN Services_Rides sr ON s.ID = sr.RideID
            LEFT JOIN Users p ON s.partner_id = p.ID
            WHERE sr.UserID IS NULL
            AND (s.status_pedido = 'aprovado' OR s.status_pedido IS NULL)
            ORDER BY s.serviceDate, s.serviceStartTime";

} elseif ($status === 'assigned') {
    // Viagens ATRIBUÍDAS (apenas aprovadas)
    $sql = "SELECT 
                s.ID, s.serviceDate, s.serviceStartTime, s.paxADT, s.paxCHD, 
                s.serviceStartPoint, s.serviceTargetPoint, s.FlightNumber, 
                s.NomeCliente, s.ClientNumber, s.serviceType, s.total_price,
                s.status_pedido,
                u.name AS driverName,
                p.name AS partner_name
            FROM Services s
            INNER JOIN Services_Rides sr ON s.ID = sr.RideID
            INNER JOIN Users u ON sr.UserID = u.ID
            LEFT JOIN Users p ON s.partner_id = p.ID
            WHERE (s.status_pedido = 'aprovado' OR s.status_pedido IS NULL)
            ORDER BY s.serviceDate, s.serviceStartTime";

} else {
    // TODAS as viagens (apenas aprovadas)
    $sql = "SELECT 
                s.ID, s.serviceDate, s.serviceStartTime, s.paxADT, s.paxCHD, 
                s.serviceStartPoint, s.serviceTargetPoint, s.FlightNumber, 
                s.NomeCliente, s.ClientNumber, s.serviceType, s.total_price,
                s.status_pedido,
                u.name AS driverName,
                p.name AS partner_name
            FROM Services s
            LEFT JOIN Services_Rides sr ON s.ID = sr.RideID
            LEFT JOIN Users u ON sr.UserID = u.ID
            LEFT JOIN Users p ON s.partner_id = p.ID
            WHERE (s.status_pedido = 'aprovado' OR s.status_pedido IS NULL)
            ORDER BY s.serviceDate, s.serviceStartTime";
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $viagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($viagens as $viagem) {
        
        // Data & Hora
        $data_hora = htmlspecialchars($viagem['serviceDate'] . ' ' . substr($viagem['serviceStartTime'], 0, 5));

        // --- LÓGICA DE CONDUTOR / PARCEIRO ---
        // Se for um pedido pendente, mostramos quem pediu em vez do condutor
        if (isset($viagem['status_pedido']) && $viagem['status_pedido'] === 'pendente') {
            $nomeParceiro = $viagem['partner_name'] ? htmlspecialchars($viagem['partner_name']) : 'Agência';
            $condutor_html = '<span class="badge text-bg-warning border border-warning"><i class="bi bi-shop me-1"></i>' . $nomeParceiro . '</span>';
        } else {
            // Lógica normal de condutor
            if ($viagem['driverName']) {
                $condutor_html = '<span class="badge text-bg-success">' . htmlspecialchars($viagem['driverName']) . '</span>';
            } else {
                $condutor_html = '<span class="badge bg-secondary">N.A</span>';
            }
        }

        // Tipo
        $tipo_badge = $viagem['serviceType'] == 1 ? 'bg-dark' : 'bg-warning';
        $tipo_texto = $viagem['serviceType'] == 1 ? 'Private' : 'Shared';
        $tipo_html = '<span class="badge ' . $tipo_badge . '" 
                           style="cursor:pointer;" 
                           onclick="changeTripType(' . $viagem['ID'] . ', ' . $viagem['serviceType'] . ')">'
                      . $tipo_texto . 
                      '</span>';

        // Preço
        $preco = $viagem['total_price'] ?? '0.00';

        // --- AÇÕES ---
        $acoes_html = '<div class="btn-group-sm d-flex justify-content-center">';

        if (isset($viagem['status_pedido']) && $viagem['status_pedido'] === 'pendente') {
            // == BOTÕES DE APROVAÇÃO ==
            $acoes_html .= '<button class="btn btn-success rounded-circle shadow-sm me-2" onclick="handleRequest(' . $viagem['ID'] . ', \'approve\')" title="Aprovar"><i class="bi bi-check-lg"></i></button>';
            $acoes_html .= '<button class="btn btn-danger rounded-circle shadow-sm" onclick="handleRequest(' . $viagem['ID'] . ', \'reject\')" title="Rejeitar"><i class="bi bi-x-lg"></i></button>';
        
        } else {
            // == BOTÕES NORMAIS (Editar/Atribuir/Apagar) ==
            
            // Variáveis para os onclicks (evitar "spaghetti code" no HTML)
            $onclick_details = sprintf(
                "editTravel(%d, '%sT%s', '%s', '%s', '%s', %d, %d, '%s', '%s', '%s', %d, '%s')", 
                $viagem['ID'],
                $viagem['serviceDate'],
                substr($viagem['serviceStartTime'], 0, 5),
                htmlspecialchars(addslashes($viagem['driverName'] ?? 'N.A'), ENT_QUOTES),
                htmlspecialchars(addslashes($viagem['serviceStartPoint']), ENT_QUOTES),
                htmlspecialchars(addslashes($viagem['serviceTargetPoint']), ENT_QUOTES),
                $viagem['paxADT'],
                $viagem['paxCHD'],
                htmlspecialchars(addslashes($viagem['FlightNumber'] ?? ''), ENT_QUOTES),
                htmlspecialchars(addslashes($viagem['NomeCliente'] ?? ''), ENT_QUOTES),
                htmlspecialchars(addslashes($viagem['ClientNumber'] ?? ''), ENT_QUOTES),
                $viagem['serviceType'],
                $preco
            );
            
            $onclick_delete_name = htmlspecialchars(addslashes($viagem['serviceStartPoint'] . ' - ' . $viagem['serviceTargetPoint']), ENT_QUOTES);
            $btn_atribuir_cor = ($viagem['driverName'] ?? false) ? 'btn-info' : 'btn-primary';

            // Botão Atribuir
            $acoes_html .= '<a href="#" class="btn ' . $btn_atribuir_cor . ' rounded-circle" 
                               data-bs-target="#atribuirCondutorModal" 
                               onclick="event.preventDefault(); setViagemId(' . $viagem['ID'] . '); var myModal = new bootstrap.Modal(document.getElementById(\'atribuirCondutorModal\')); myModal.show();">
                               <i class="bi ' . (($viagem['driverName'] ?? false) ? 'bi-person-check-fill' : 'bi-person-plus-fill') . '"></i>
                            </a>';

            // Botão Editar
            $acoes_html .= '<a href="#" class="btn btn-warning rounded-circle text-dark ms-1" 
                               data-bs-target="#editModal" 
                               onclick="event.preventDefault(); ' . $onclick_details . '; var myModal = new bootstrap.Modal(document.getElementById(\'editModal\')); myModal.show();">
                               <i class="bi bi-pencil-fill"></i>
                            </a>';
            
            // Botão Apagar
            $acoes_html .= '<a href="#" class="btn btn-danger rounded-circle ms-1" 
                               data-bs-target="#deleteTripModal" 
                               onclick="event.preventDefault(); setDeleteTrip(' . $viagem['ID'] . ', \'' . $onclick_delete_name . '\'); var myModal = new bootstrap.Modal(document.getElementById(\'deleteTripModal\')); myModal.show();">
                               <i class="bi bi-trash3-fill"></i>
                            </a>';

            // Botão Logs
            $acoes_html .= '<button class="btn btn-info btn-sm rounded-circle shadow-sm ms-1" 
                               onclick="viewTripLogs(' . $viagem['ID'] . ')" 
                               title="Ver Logs" 
                               style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;">
                               <i class="bi bi-clock-history text-white"></i>
                            </button>';
        }
        
        $acoes_html .= '</div>';

        $data[] = [
            'id' => "#" . $viagem['ID'], // ID com cardinal para visualização
            'raw_id' => $viagem['ID'], // ID puro para lógica JS (handleRequest)
            'data_hora' => $data_hora,
            'condutor' => $condutor_html,
            'recolha' => htmlspecialchars($viagem['serviceStartPoint']),
            'entrega' => htmlspecialchars($viagem['serviceTargetPoint']),
            'tipo' => $tipo_html,
            'status_pedido' => $viagem['status_pedido'] ?? null, // Passar estado para o JS
            'partner_name' => htmlspecialchars($viagem['partner_name'] ?? ''), // Passar nome para o JS
            'acoes' => $acoes_html
        ];
    }

    echo json_encode(['data' => $data]);

} catch (PDOException $e) {
    echo json_encode(['data' => [], 'error' => $e->getMessage()]);
}
?>