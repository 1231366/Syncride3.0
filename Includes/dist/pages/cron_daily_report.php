<?php
// 1. Configurações Básicas
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Lisbon');

// Se este ficheiro estiver na raiz, os caminhos serão estes:
// (Ajusta se mudares o ficheiro de sítio)
require __DIR__ . '/../../../auth/dbconfig.php';

// Caminho para o PHPMailer (Baseado na estrutura que vimos antes)
require __DIR__ . '/Includes/dist/pages/PHPMailer/Exception.php';
require __DIR__ . '/Includes/dist/pages/PHPMailer/PHPMailer.php';
require __DIR__ . '/Includes/dist/pages/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 2. Definir Data de Amanhã
$amanha = new DateTime('tomorrow');
$dataSql = $amanha->format('Y-m-d'); // Formato BD (2023-10-25)
$dataPt = $amanha->format('d/m/Y');  // Formato Leitura (25/10/2023)

echo "A preparar relatório para: " . $dataPt . "<br>";

// 3. Buscar Serviços de Amanhã (Com nome do Condutor)
$servicos = [];
try {
    $sql = "
        SELECT 
            s.ID, 
            s.serviceStartTime, 
            s.serviceStartPoint, 
            s.serviceTargetPoint, 
            s.FlightNumber, 
            s.NomeCliente, 
            s.ClientNumber, 
            s.paxADT, 
            s.paxCHD,
            s.obs,
            u.name AS NomeCondutor
        FROM Services s
        LEFT JOIN Services_Rides sr ON s.ID = sr.RideID
        LEFT JOIN Users u ON sr.UserID = u.id
        WHERE s.serviceDate = :data
        ORDER BY s.serviceStartTime ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':data' => $dataSql]);
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro de Base de Dados: " . $e->getMessage());
}

// Se não houver viagens, opcionalmente aborta ou manda email a dizer "Dia Livre"
if (empty($servicos)) {
    echo "Sem serviços agendados para amanhã.";
    // Podes colocar um exit() aqui se não quiseres receber email vazio
}

// 4. Construir o HTML do Email
// CSS Inline é obrigatório para emails
$cssTable = "width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px;";
$cssTh = "background-color: #007bff; color: white; padding: 10px; text-align: left; border: 1px solid #ddd;";
$cssTd = "padding: 8px; border: 1px solid #ddd; color: #333;";
$cssTdAlt = "padding: 8px; border: 1px solid #ddd; background-color: #f9f9f9; color: #333;";

$corpoEmail = "
<div style='font-family: Arial, sans-serif; color: #333; max-width: 800px; margin: 0 auto;'>
    <h2 style='color: #2c3e50; text-align: center;'>📅 Planeamento: $dataPt</h2>
    <p style='text-align: center;'>Seguem os detalhes dos serviços agendados para amanhã.</p>
    <br>
    <table style='$cssTable'>
        <thead>
            <tr>
                <th style='$cssTh' width='10%'>Hora</th>
                <th style='$cssTh' width='30%'>Rota</th>
                <th style='$cssTh' width='25%'>Cliente / Voo</th>
                <th style='$cssTh' width='10%'>Pax</th>
                <th style='$cssTh' width='25%'>Condutor</th>
            </tr>
        </thead>
        <tbody>
";

foreach ($servicos as $k => $svc) {
    // Alternar cor das linhas para leitura fácil
    $estiloLinha = ($k % 2 == 0) ? $cssTd : $cssTdAlt;

    $hora = substr($svc['serviceStartTime'], 0, 5);
    
    // Formatação da Rota
    $rota = "<b>De:</b> " . htmlspecialchars($svc['serviceStartPoint']) . 
            "<br><b>Para:</b> " . htmlspecialchars($svc['serviceTargetPoint']);
    
    // Dados Cliente
    $cliente = "<b>" . htmlspecialchars($svc['NomeCliente']) . "</b>";
    if (!empty($svc['FlightNumber'])) $cliente .= "<br>✈️ " . htmlspecialchars($svc['FlightNumber']);
    if (!empty($svc['ClientNumber'])) $cliente .= "<br>📞 " . htmlspecialchars($svc['ClientNumber']);
    if (!empty($svc['obs'])) $cliente .= "<br><br><i>⚠️ Obs: " . htmlspecialchars($svc['obs']) . "</i>";

    // Passageiros
    $totalPax = ($svc['paxADT'] + $svc['paxCHD']);
    $paxInfo = "<b>$totalPax</b> <small>($svc[paxADT]A/$svc[paxCHD]C)</small>";

    // Condutor (Vermelho se não houver)
    if ($svc['NomeCondutor']) {
        $condutor = "✅ " . htmlspecialchars($svc['NomeCondutor']);
    } else {
        $condutor = "<span style='color: white; background-color: #dc3545; padding: 2px 5px; border-radius: 3px; font-weight: bold;'>⚠️ POR ATRIBUIR</span>";
    }

    $corpoEmail .= "
        <tr>
            <td style='$estiloLinha; text-align: center;'><b>$hora</b></td>
            <td style='$estiloLinha'>$rota</td>
            <td style='$estiloLinha'>$cliente</td>
            <td style='$estiloLinha; text-align: center;'>$paxInfo</td>
            <td style='$estiloLinha'>$condutor</td>
        </tr>
    ";
}

$corpoEmail .= "
        </tbody>
    </table>
    <br>
    <p style='font-size: 11px; color: #777; text-align: center;'>
        Relatório gerado automaticamente pelo sistema SyncRide em " . date('d/m/Y H:i') . "
    </p>
</div>
";

// 5. Enviar Email
$mail = new PHPMailer(true);

try {
    // Configurações do Servidor (NOVAS)
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
    $mail->addAddress('tiagofsilva04@gmail.com');

    // Conteúdo
    $mail->isHTML(true);
    $mail->Subject = "📅 Planeamento para Amanhã ($dataPt)";
    $mail->Body    = $corpoEmail;
    $mail->AltBody = "Consulte o seu email em formato HTML para ver a tabela de serviços.";

    $mail->send();
    echo "SUCESSO: Relatório enviado para os emails configurados!";

} catch (Exception $e) {
    echo "ERRO ao enviar email: {$mail->ErrorInfo}";
}
?>