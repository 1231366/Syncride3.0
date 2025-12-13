<?php
require __DIR__ . '/../../../auth/dbconfig.php';

header('Content-Type: application/json');

// Verificar a ligação à base de dados
if ($conn->connect_error) {
    echo json_encode(['error' => 'Erro na ligação à base de dados']);
    exit;
}

// Obter o número total de viagens concluídas
$query_completed = "SELECT COUNT(*) AS total FROM Services WHERE status = 'concluída'";
$result_completed = $conn->query($query_completed);
$total_completed = ($result_completed) ? $result_completed->fetch_assoc()['total'] : 0;

// Obter o número de viagens programadas para esta semana
$query_scheduled_week = "SELECT COUNT(*) AS total FROM Services WHERE serviceDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$result_scheduled_week = $conn->query($query_scheduled_week);
$total_scheduled_week = ($result_scheduled_week) ? $result_scheduled_week->fetch_assoc()['total'] : 0;

// Obter o número de viagens programadas para hoje
$query_scheduled_today = "SELECT COUNT(*) AS total FROM Services WHERE serviceDate = CURDATE()";
$result_scheduled_today = $conn->query($query_scheduled_today);
$total_scheduled_today = ($result_scheduled_today) ? $result_scheduled_today->fetch_assoc()['total'] : 0;

// Calcular a percentagem de viagens semanais concluídas
$query_weekly_completed = "SELECT COUNT(*) AS total FROM Services WHERE status = 'concluída' AND serviceDate BETWEEN CURDATE() - INTERVAL 7 DAY AND CURDATE()";
$result_weekly_completed = $conn->query($query_weekly_completed);
$total_weekly_completed = ($result_weekly_completed) ? $result_weekly_completed->fetch_assoc()['total'] : 0;

$weekly_completion_percentage = ($total_scheduled_week > 0) ? round(($total_weekly_completed / $total_scheduled_week) * 100, 2) : 0;

// Retornar os dados em JSON
echo json_encode([
    'total_completed' => (int) $total_completed,
    'total_scheduled_week' => (int) $total_scheduled_week,
    'weekly_completion_percentage' => (float) $weekly_completion_percentage,
    'total_scheduled_today' => (int) $total_scheduled_today
]);
?>

