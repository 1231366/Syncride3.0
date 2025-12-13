<?php
session_start();
require __DIR__ . '/../../../auth/dbconfig.php';

// Apenas Admin (Role 1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../../../index.php");
    exit();
}

// Configuração do Diretório de Upload
// O diretório de upload está DENTRO da pasta "pages/"
$UPLOAD_DIR_BASE = __DIR__ . '/uploads/vehicles/'; 
// O caminho que é salvo na DB (relativo ao domínio web)
$UPLOAD_PATH_DB = 'Includes/dist/pages/uploads/vehicles/';


// --- LÓGICA DE APAGAR VEÍCULO ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $vehicle_id = $_GET['id'];
    try {
        // 1. Opcional: Apagar ficheiro de foto antigo (se existir)
        $stmt_fetch = $pdo->prepare("SELECT photo_path FROM Vehicles WHERE id = ?");
        $stmt_fetch->execute([$vehicle_id]);
        $old_photo_path_db = $stmt_fetch->fetchColumn();
        if ($old_photo_path_db && file_exists(__DIR__ . '/../../../' . $old_photo_path_db)) {
            unlink(__DIR__ . '/../../../' . $old_photo_path_db);
        }

        // 2. Desassociar o veículo de qualquer motorista antes de apagar
        $pdo->prepare("UPDATE Users SET assigned_vehicle_id = NULL WHERE assigned_vehicle_id = ?")
            ->execute([$vehicle_id]);

        // 3. Apagar o veículo
        $pdo->prepare("DELETE FROM Vehicles WHERE id = ?")
            ->execute([$vehicle_id]);
            
        header("Location: ManageFleet.php?success=deleted");
        exit();
    } catch (PDOException $e) {
        die("Erro ao apagar veículo ou desassociar motorista: " . $e->getMessage());
    }
}

// --- LÓGICA DE GUARDAR / EDITAR ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Variáveis do Modal (sem KM de revisão)
    $id = $_POST['vehicle_id'] ?? null;
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $plate = strtoupper(trim($_POST['license_plate']));
    $inspection = $_POST['inspection_date'];
    $insurance = $_POST['insurance_date'];
    $status = $_POST['status'];
    $assigned_driver_id = $_POST['assigned_driver_id'] ?? null;
    $existing_photo_path = $_POST['existing_photo_path'] ?? null;

    $new_photo_path = $existing_photo_path; // Assume que a foto não mudou

    // --- LÓGICA DE UPLOAD DA FOTO ---
    if (isset($_FILES['vehicle_photo']) && $_FILES['vehicle_photo']['error'] == UPLOAD_ERR_OK) {
        
        // 1. Gerar nome de ficheiro único
        $file_info = pathinfo($_FILES['vehicle_photo']['name']);
        $extension = strtolower($file_info['extension']);
        $filename = 'vehicle_' . time() . '.' . $extension;
        $target_file = $UPLOAD_DIR_BASE . $filename;
        
        // 2. Tentar mover ficheiro
        if (move_uploaded_file($_FILES['vehicle_photo']['tmp_name'], $target_file)) {
            
            // Sucesso: Caminho para guardar na DB (relativo ao domínio web)
            $new_photo_path = $UPLOAD_PATH_DB . $filename; 
            
            // Apagar a foto antiga se estiver a editar
            if ($id && $existing_photo_path && file_exists(__DIR__ . '/../../../' . $existing_photo_path)) {
                @unlink(__DIR__ . '/../../../' . $existing_photo_path); // @ evita erro se o ficheiro não existir
            }

        } else {
            // Falha no upload (Permissões, etc.)
            // Podemos logar, mas não abortamos a gravação de outros dados
             error_log("Upload failed for vehicle photo. Error code: " . $_FILES['vehicle_photo']['error']);
        }
    }
    // --- FIM LÓGICA DE UPLOAD ---


    try {
        if ($id) {
            // EDITAR VEÍCULO
            $sql = "UPDATE Vehicles SET license_plate=?, brand=?, model=?, inspection_date=?, insurance_date=?, status=?, photo_path=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plate, $brand, $model, $inspection, $insurance, $status, $new_photo_path, $id]);
            $msg = "updated";
        } else {
            // NOVO VEÍCULO
            $sql = "INSERT INTO Vehicles (license_plate, brand, model, inspection_date, insurance_date, status, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plate, $brand, $model, $inspection, $insurance, $status, $new_photo_path]);
            // Obter o ID inserido para a associação
            $id = $pdo->lastInsertId();
            $msg = "created";
        }
        
        // --- LÓGICA DE ASSOCIAÇÃO DO MOTORISTA ---
        
        // 1. Desassociar o veículo de QUALQUER motorista antigo que possa estar associado a ele
        $pdo->prepare("UPDATE Users SET assigned_vehicle_id = NULL WHERE assigned_vehicle_id = ?")
            ->execute([$id]);

        // 2. Associar o veículo ao NOVO motorista (se um foi selecionado)
        if (!empty($assigned_driver_id)) {
            // Primeiro, garantir que o motorista não está noutro carro.
            $pdo->prepare("UPDATE Users SET assigned_vehicle_id = NULL WHERE id = ?")
                ->execute([$assigned_driver_id]);
                
            // Associar o novo veículo
            $pdo->prepare("UPDATE Users SET assigned_vehicle_id = ? WHERE id = ?")
                ->execute([$id, $assigned_driver_id]);
        }
        // ----------------------------------------


        header("Location: ManageFleet.php?success=$msg");
        exit();
    } catch (PDOException $e) {
        die("Erro ao salvar: " . $e->getMessage());
    }
}
?>