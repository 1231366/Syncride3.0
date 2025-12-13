<?php
// save_profile_photo.php (No diretório raiz)
session_start();
require 'auth/dbconfig.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$defaultImgPath = 'Includes/dist/assets/img/user2-160x160.jpg';

// Define o URL de redirecionamento para o Painel do Condutor
$redirectUrl = 'Includes/dist/pages/driver.php';


// Lógica para REMOVER FOTO
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    try {
        $stmt = $pdo->prepare("SELECT profile_photo_path FROM Users WHERE id = ?");
        $stmt->execute([$user_id]);
        $oldPath = $stmt->fetchColumn();
        
        if ($oldPath && strpos($oldPath, 'user2-160x160.jpg') === false) {
            $fullOldPath = __DIR__ . '/' . $oldPath;
            if (file_exists($fullOldPath)) {
                unlink($fullOldPath);
            }
        }

        $stmt = $pdo->prepare("UPDATE Users SET profile_photo_path = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        unset($_SESSION['profile_photo_path']);

        header("Location: $redirectUrl?success=photo_deleted");
        exit();
    } catch (PDOException $e) {
        error_log("DB Error during profile photo delete: " . $e->getMessage());
        header("Location: $redirectUrl?error=db_error");
        exit();
    }
}


// Lógica para UPLOAD DE FOTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    
    $dbUploadDir = 'Includes/dist/pages/uploads/profiles/';
    $fullUploadPath = __DIR__ . '/' . $dbUploadDir; 

    if (!is_dir($fullUploadPath)) {
        // Criar diretório se não existir
        if (!mkdir($fullUploadPath, 0775, true)) {
             header("Location: $redirectUrl?error=dir_perm_error");
             exit();
        }
    }
    
    $file = $_FILES['profile_photo'];
    $fileName = basename($file['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedTypes = array('jpg', 'jpeg', 'png');
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($fileExt, $allowedTypes)) {
        header("Location: $redirectUrl?error=invalid_file");
        exit();
    }
    
    if ($file['size'] > $maxFileSize) {
        header("Location: $redirectUrl?error=file_too_large");
        exit();
    }

    $newFileName = 'profile_' . $user_id . '_' . time() . '.' . $fileExt;
    $targetFilePath = $fullUploadPath . $newFileName;
    $dbPath = $dbUploadDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        
        try {
            // Remove foto antiga
            $stmt = $pdo->prepare("SELECT profile_photo_path FROM Users WHERE id = ?");
            $stmt->execute([$user_id]);
            $oldPath = $stmt->fetchColumn();
            
            if ($oldPath && strpos($oldPath, 'user2-160x160.jpg') === false) {
                $fullOldPath = __DIR__ . '/' . $oldPath;
                if (file_exists($fullOldPath)) {
                    unlink($fullOldPath);
                }
            }

            // Atualizar base de dados
            $stmt = $pdo->prepare("UPDATE Users SET profile_photo_path = ? WHERE id = ?");
            $stmt->execute([$dbPath, $user_id]);

            // Atualizar sessão
            $_SESSION['profile_photo_path'] = $dbPath;

            header("Location: $redirectUrl?success=photo_uploaded");
            exit();

        } catch (PDOException $e) {
            if (file_exists($targetFilePath)) unlink($targetFilePath);
            error_log("DB Error during profile photo update: " . $e->getMessage());
            header("Location: $redirectUrl?error=db_error");
            exit();
        }
    } else {
        header("Location: $redirectUrl?error=upload_failed");
        exit();
    }
}

header("Location: $redirectUrl");
exit();
?>