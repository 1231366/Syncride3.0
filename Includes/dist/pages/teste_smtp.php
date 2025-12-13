<?php
// Ativar exibição de TODOS os erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico de Email (PHPMailer)</h1>";

// 1. VERIFICAR FICHEIROS
echo "<h3>1. A verificar ficheiros...</h3>";

$files = [
    'PHPMailer/Exception.php',
    'PHPMailer/PHPMailer.php',
    'PHPMailer/SMTP.php'
];

$tudo_ok = true;
foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<div style='color:green'>[OK] Encontrado: $file</div>";
    } else {
        echo "<div style='color:red; font-weight:bold'>[ERRO] Ficheiro em falta: $file</div>";
        echo "<div>O sistema procurou em: " . __DIR__ . '/' . $file . "</div>";
        $tudo_ok = false;
    }
}

if (!$tudo_ok) {
    die("<br><h2 style='color:red'>PARAGEM: A estrutura de pastas está incorreta. Verifique se a pasta se chama 'PHPMailer' (maiúsculas importam) e se os ficheiros estão lá dentro e não numa sub-pasta.</h2>");
}

// 2. CARREGAR PHPMAILER
require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 3. TENTAR ENVIO
echo "<h3>2. A tentar enviar email via SMTP...</h3>";
echo "<div style='background:#f0f0f0; padding:10px; border:1px solid #ccc; font-family:monospace'>";

$mail = new PHPMailer(true);

try {
    // Debug profundo
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; 

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'tiagofsilva04@gmail.com';
    $mail->Password   = 'hpxa vkfx clmx fdit'; // A tua senha de app
    
    // Tenta a porta 587 (TLS)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Se a 587 falhar, tenta descomentar estas linhas para usar a 465 (SSL)
    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    // $mail->Port       = 465;

    $mail->setFrom('tiagofsilva04@gmail.com', 'Teste Diagnostico');
    $mail->addAddress('tiagofsilva04@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'Teste SMTP - Sucesso';
    $mail->Body    = 'Se leres isto, o SMTP esta a funcionar!';

    $mail->send();
    echo "</div>";
    echo "<h2 style='color:green'>SUCESSO! Email enviado.</h2>";
    echo "<p>Pode agora usar o código original do upload_no_show.php.</p>";

} catch (Exception $e) {
    echo "</div>";
    echo "<h2 style='color:red'>FALHA NO ENVIO</h2>";
    echo "<strong>Erro PHPMailer:</strong> " . $mail->ErrorInfo;
    echo "<br><br><strong>Dicas:</strong>";
    echo "<ul>";
    echo "<li>Se o erro for 'Connection timed out' ou 'connect() failed', o servidor está a bloquear a porta 587. Tente mudar para a porta 465 no código.</li>";
    echo "<li>Se o erro for 'Username and Password not accepted', gere uma nova senha de app.</li>";
    echo "</ul>";
}
?>