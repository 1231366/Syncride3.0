<?php
// 1. Configurações Básicas
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Lisbon');

// Se for correr via navegador, define como HTML para veres os acentos bem
header('Content-Type: text/html; charset=utf-8');

// 2. Carregar PHPMailer
require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 3. Ligação à Base de Dados
require __DIR__ . '/../../../auth/dbconfig.php';

// --- LÓGICA DO RELATÓRIO ---

// Definir "Amanhã"
$amanha = new DateTime('tomorrow');
$dataSql = $amanha->format('Y-m-d'); // Ex: 2023-12-05
$dataPt = $amanha->format('d/m/Y');  // Ex: 05/12/2023

echo "<h3>A processar relatório para: $dataPt ($dataSql)...</h3>";

$servicos = [];

try {
    // Query corrigida (removido s.obs que não existe na tua BD)
    $query = "
        SELECT 
            s.ID AS ServiceID, 
            s.serviceStartTime, 
            s.serviceStartPoint, 
            s.serviceTargetPoint, 
            s.FlightNumber, 
            s.NomeCliente, 
            s.ClientNumber, 
            s.paxADT, 
            s.paxCHD,
            s.serviceType,
            u.name AS NomeCondutor
        FROM Services s
        LEFT JOIN Services_Rides sr ON s.ID = sr.RideID
        LEFT JOIN Users u ON sr.UserID = u.id
        WHERE s.serviceDate = ?
        ORDER BY s.serviceStartTime ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$dataSql]);
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro na Base de Dados: " . $e->getMessage());
}

// Se não houver serviços, encerra (ou envia email vazio se preferires)
if (empty($servicos)) {
    echo "Sem serviços para amanhã. Email não enviado.";
    exit();
}

// --- CONSTRUÇÃO DO HTML DO EMAIL ---

$styleTable = "width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 13px; color: #333;";
$styleTh    = "background-color: #007bff; color: white; padding: 10px; text-align: left; border: 1px solid #ddd;";
$styleTd    = "padding: 8px; border: 1px solid #ddd; vertical-align: top;";
$styleTdAlt = "padding: 8px; border: 1px solid #ddd; vertical-align: top; background-color: #f9f9f9;";
$styleBadge = "display: inline-block; padding: 3px 6px; color: white; border-radius: 4px; font-size: 11px; font-weight: bold;";

$htmlBody = "
<div style='font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto; color: #333;'>
    <div style='text-align: center; margin-bottom: 20px;'>
        <h2 style='color: #28a745; margin-bottom: 5px;'>📅 Planeamento de Serviços</h2>
        <p style='font-size: 16px; margin-top: 0;'>Para amanhã: <strong>$dataPt</strong></p>
        <p style='font-size: 12px; color: #777;'>Total de Serviços: " . count($servicos) . "</p>
    </div>

    <table style='$styleTable'>
        <thead>
            <tr>
                <th style='$styleTh' width='10%'>Hora</th>
                <th style='$styleTh' width='10%'>ID</th>
                <th style='$styleTh' width='30%'>Rota</th>
                <th style='$styleTh' width='25%'>Cliente / Voo</th>
                <th style='$styleTh' width='10%'>Pax</th>
                <th style='$styleTh' width='15%'>Condutor</th>
            </tr>
        </thead>
        <tbody>
";

foreach ($servicos as $k => $svc) {
    $rowStyle = ($k % 2 == 0) ? $styleTd : $styleTdAlt;

    // Hora (formato HH:MM)
    $hora = substr($svc['serviceStartTime'], 0, 5);

    // Rota
    $rota = "<strong>De:</strong> " . htmlspecialchars($svc['serviceStartPoint']) . "<br>" .
            "<strong>Para:</strong> " . htmlspecialchars($svc['serviceTargetPoint']);

    // Cliente e Voo
    $cliente = "<strong>" . htmlspecialchars($svc['NomeCliente']) . "</strong>";
    if (!empty($svc['FlightNumber'])) {
        $cliente .= "<br>✈️ " . htmlspecialchars($svc['FlightNumber']);
    }
    if (!empty($svc['ClientNumber']) && $svc['ClientNumber'] !== 'Não disponível') {
        $cliente .= "<br>📞 " . htmlspecialchars($svc['ClientNumber']);
    }

    // Passageiros
    $totalPax = (int)$svc['paxADT'] + (int)$svc['paxCHD'];
    $paxInfo = "<strong>$totalPax</strong><br><small style='color:#666;'>(" . $svc['paxADT'] . "A + " . $svc['paxCHD'] . "C)</small>";

    // Condutor
    if ($svc['NomeCondutor']) {
        $condutor = "<span style='color: #28a745; font-weight: bold;'>👤 " . htmlspecialchars($svc['NomeCondutor']) . "</span>";
    } else {
        $condutor = "<span style='$styleBadge background-color: #dc3545;'>⚠️ POR ATRIBUIR</span>";
    }

    // Tipo de Serviço (Chegada/Partida/Serviço)
    $tipoBadge = "";
    if ($svc['serviceType'] == 1) { 
        $tipoBadge = "<br><span style='$styleBadge background-color: #17a2b8;'>Chegada</span>"; 
    } elseif ($svc['serviceType'] == 2) { 
        $tipoBadge = "<br><span style='$styleBadge background-color: #ffc107; color: black;'>Partida</span>"; 
    } else {
        $tipoBadge = "<br><span style='$styleBadge background-color: #6c757d;'>Serviço</span>";
    }

    $htmlBody .= "
        <tr>
            <td style='$rowStyle text-align: center; font-weight: bold;'>$hora</td>
            <td style='$rowStyle text-align: center;'>#{$svc['ServiceID']}$tipoBadge</td>
            <td style='$rowStyle'>$rota</td>
            <td style='$rowStyle'>$cliente</td>
            <td style='$rowStyle text-align: center;'>$paxInfo</td>
            <td style='$rowStyle'>$condutor</td>
        </tr>
    ";
}

$htmlBody .= "
        </tbody>
    </table>
    <br>
    <hr>
    <p style='text-align: center; font-size: 11px; color: #999;'>
        Este email foi gerado automaticamente pelo sistema SyncRide em " . date('d/m/Y H:i') . ".
    </p>
</div>
";

// --- ENVIO DE EMAIL ---

$mail = new PHPMailer(true);

try {
    // Configurações SMTP
    $mail->isSMTP();
    $mail->Host       = 'cloud865.thundercloud.uk';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'no-reply@syncride.wmservers.pt';
    $mail->Password   = 'KseZ@oMWo%nV';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8'; 

    // Remetente
    $mail->setFrom('no-reply@syncride.wmservers.pt', 'SyncRide Planeamento');

    // Destinatários
    $mail->addAddress('flexewar@gmail.com');
    $mail->addAddress('tiagofsilva04@gmail.com');

    // Conteúdo
    $mail->isHTML(true);
    $mail->Subject = "Planeamento para Amanhã - $dataPt";
    $mail->Body    = $htmlBody;
    $mail->AltBody = "Existem serviços agendados para $dataPt. Por favor consulte o email em modo HTML.";

    $mail->send();
    echo "<h3 style='color: green;'>✅ Sucesso! Relatório enviado.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Erro ao enviar email: {$mail->ErrorInfo}</h3>";
}
?>