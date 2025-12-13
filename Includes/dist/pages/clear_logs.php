<?php
$host = 'localhost';
$dbname = 'syncride_Syncride'; // Nome da base de dados correto
$username = 'syncride_tiago'; // Nome de utilizador correto
$password = '8mf7dvU+,@4T'; // Password correta

$conn = new mysqli($host, $username, $password, $dbname);

// Verificar se a conexão foi bem-sucedida
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
// Apagar todos os registos da tabela Logs
$sql = "DELETE FROM Logs";

if ($conn->query($sql) === TRUE) {
    // Inserir log da ação de limpeza
    $conn->query("INSERT INTO Logs (Action, date) VALUES ('Histórico de ações limpo', NOW())");

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao limpar o histórico: ' . $conn->error]);
}
?>
