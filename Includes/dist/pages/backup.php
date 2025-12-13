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

// Função para obter o conteúdo SQL de todas as tabelas
function export_database($conn, $database)
{
    $tables = [];
    $result = $conn->query('SHOW TABLES');

    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $sql = "SET NAMES utf8mb4;\n";
    foreach ($tables as $table) {
        // Obter o CREATE TABLE
        $createTableResult = $conn->query("SHOW CREATE TABLE `$table`");
        $createTableRow = $createTableResult->fetch_row();
        $sql .= "\n\n" . $createTableRow[1] . ";\n\n";

        // Obter todos os dados da tabela
        $dataResult = $conn->query("SELECT * FROM `$table`");
        while ($row = $dataResult->fetch_assoc()) {
            $sql .= "INSERT INTO `$table` (" . implode(', ', array_keys($row)) . ") VALUES ('" . implode("', '", array_map([$conn, 'real_escape_string'], array_values($row))) . "');\n";
        }
        $sql .= "\n\n";
    }

    return $sql;
}

// Gerar o conteúdo do backup
$backupContent = export_database($conn, $database);

// Registar o log do backup na tabela Logs
$stmt = $conn->prepare("INSERT INTO Logs (Action, date) VALUES ('Backup da base de dados realizado', NOW())");
$stmt->execute();

// Defina o nome do arquivo de backup
$filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';

// Defina o cabeçalho para download do arquivo
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Envie o conteúdo SQL para o navegador
echo $backupContent;
// Feche a conexão com o banco de dados
$conn->close();
?>
