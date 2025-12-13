<?php
// 1. CONFIGURAÇÕES
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. BASE DE DADOS (Caminho igual ao ficheiro que funciona)
require __DIR__ . '/../../../auth/dbconfig.php';

// 3. DATAS
date_default_timezone_set('Europe/Lisbon');
$dateTomorrow = date('Y-m-d', strtotime('+1 day'));
$displayDate = date('d/m/Y', strtotime('+1 day'));

try {
    // 4. CONSULTA SQL
    $sql = "SELECT 
                s.serviceStartTime,
                s.serviceStartPoint,
                s.serviceTargetPoint,
                s.NomeCliente,
                s.ClientNumber,
                s.paxADT,
                s.paxCHD,
                s.FlightNumber,
                u.name as NomeCondutor
            FROM Services s
            LEFT JOIN Services_Rides sr ON s.ID = sr.RideID
            LEFT JOIN Users u ON sr.UserID = u.id
            WHERE s.serviceDate = :dateTomorrow
            ORDER BY s.serviceStartTime ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['dateTomorrow' => $dateTomorrow]);
    $viagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. CONTEÚDO HTML
    $message = "
    <html>
    <head>
      <title>Escala SyncRide</title>
      <style>
        body { font-family: Arial, sans-serif; color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
      </style>
    </head>
    <body>
      <h3>📅 Escala de Serviço: $displayDate</h3>";

    if (count($viagens) > 0) {
        $message .= "<table>
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Rota</th>
                    <th>Cliente</th>
                    <th>PAX</th>
                    <th>Voo</th>
                    <th>Condutor</th>
                </tr>
            </thead>
            <tbody>";

        foreach ($viagens as $viagem) {
            $hora = date('H:i', strtotime($viagem['serviceStartTime']));
            $rota = htmlspecialchars($viagem['serviceStartPoint']) . " > " . htmlspecialchars($viagem['serviceTargetPoint']);
            $cliente = htmlspecialchars($viagem['NomeCliente']) . " (" . htmlspecialchars($viagem['ClientNumber']) . ")";
            $pax = $viagem['paxADT'] . "/" . $viagem['paxCHD'];
            $voo = $viagem['FlightNumber'] ? $viagem['FlightNumber'] : '-';
            $condutor = $viagem['NomeCondutor'] ? $viagem['NomeCondutor'] : '<span style="color:red">POR ATRIBUIR</span>';

            $message .= "<tr>
                <td>$hora</td>
                <td>$rota</td>
                <td>$cliente</td>
                <td>$pax</td>
                <td>$voo</td>
                <td>$condutor</td>
            </tr>";
        }
        $message .= "</tbody></table>";
    } else {
        $message .= "<p>Sem viagens agendadas.</p>";
    }
    $message .= "</body></html>";

    // 6. ENVIAR EMAIL (EXATAMENTE COMO NO NO-SHOW)
    $to = 'tiagofsilva04@gmail.com';
    $subject = 'Escala SyncRide: ' . $displayDate;

    // Headers iguais ao script que funciona (sem o parâmetro -f no mail())
    $headers = "From: no-reply@syncride.webminds.pt\r\n"; 
    $headers .= "Reply-To: no-reply@syncride.webminds.pt\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    // Envio simples, sem parâmetros extra
    if(mail($to, $subject, $message, $headers)) {
        echo "Email enviado com sucesso!";
    } else {
        echo "Erro ao enviar email.";
    }

} catch (PDOException $e) {
    echo "Erro BD: " . $e->getMessage();
}
?>