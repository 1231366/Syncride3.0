<?php
// api_update_status.php
header('Content-Type: application/json');
require __DIR__ . '/../../../auth/dbconfig.php'; // Caminho correto para a tua config

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['ride_id']) || !isset($_POST['status'])) {
            throw new Exception("Dados incompletos.");
        }

        $ride_id = $_POST['ride_id'];
        $new_status = (int)$_POST['status'];
        $current_time = date('Y-m-d H:i:s');
        
        $sql_part = "";
        
        // Define qual coluna de hora atualizar
        switch ($new_status) {
            case 1: $sql_part = ", ts_start_pickup = :time"; break;
            case 2: $sql_part = ", ts_arrived_pickup = :time"; break;
            case 3: $sql_part = ", ts_start_trip = :time"; break;
            case 4: $sql_part = ", ts_completed = :time"; break;
        }

        // Se o status for 4 (terminado), paramos o tracking também na tabela Services se houver flag
        // Mas aqui focamos em atualizar o estado e a hora
        $sql = "UPDATE Services SET status_id = :status $sql_part WHERE ID = :id";
        
        $stmt = $pdo->prepare($sql);
        
        $params = [
            ':status' => $new_status,
            ':id' => $ride_id
        ];

        if ($sql_part !== "") {
            $params[':time'] = $current_time;
        }

        if ($stmt->execute($params)) {
            echo json_encode(['success' => true, 'status' => $new_status]);
        } else {
            throw new Exception("Erro ao executar update na BD.");
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
}
?>