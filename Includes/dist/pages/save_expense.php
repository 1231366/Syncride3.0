<?php
session_start();
require __DIR__ . '/../../../auth/dbconfig.php';

// Verificar Permissões (Admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../../../index.php");
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- LÓGICA DE APAGAR ---
if ($action == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Primeiro, apagar o ficheiro físico se existir
    $stmt = $pdo->prepare("SELECT file_path FROM Expenses WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetchColumn();
    
    if ($file && file_exists(__DIR__ . '/' . $file)) {
        unlink(__DIR__ . '/' . $file);
    }

    // Apagar da BD
    $stmt = $pdo->prepare("DELETE FROM Expenses WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: financial.php?success=deleted");
    exit();
}

// --- LÓGICA DE GUARDAR / EDITAR ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $id = $_POST['expense_id'] ?? null; // Se tiver ID, é edição
    $category = $_POST['category'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    
    $filePath = null;

    // 1. Processar Upload de Ficheiro (Se houver)
    if (isset($_FILES['proof']) && $_FILES['proof']['error'] == 0) {
        $uploadDir = 'uploads/expenses/';
        
        // Criar pasta se não existir
        if (!is_dir(__DIR__ . '/' . $uploadDir)) {
            mkdir(__DIR__ . '/' . $uploadDir, 0775, true);
        }

        $fileName = time() . '_' . basename($_FILES['proof']['name']);
        $targetPath = __DIR__ . '/' . $uploadDir . $fileName;
        $dbPath = $uploadDir . $fileName; // Caminho relativo para a BD

        if (move_uploaded_file($_FILES['proof']['tmp_name'], $targetPath)) {
            $filePath = $dbPath;
        }
    }

    try {
        if ($id) {
            // --- EDITAR ---
            // Se carregou novo ficheiro, atualiza path. Se não, mantém o antigo.
            $sql = "UPDATE Expenses SET category=?, description=?, amount=?, date=? " . 
                   ($filePath ? ", file_path=?" : "") . 
                   " WHERE id=?";
            
            $params = [$category, $description, $amount, $date];
            if ($filePath) $params[] = $filePath;
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $msg = "edited";

        } else {
            // --- NOVO ---
            $stmt = $pdo->prepare("INSERT INTO Expenses (category, description, amount, date, file_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$category, $description, $amount, $date, $filePath]);
            $msg = "added";
        }

        header("Location: financial.php?success=$msg");

    } catch (PDOException $e) {
        die("Erro na BD: " . $e->getMessage());
    }
}
?>