<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['fileUpload']) && $_FILES['fileUpload']['error'] == 0) {
        $fileTmpPath = $_FILES['fileUpload']['tmp_name'];
        $fileName = $_FILES['fileUpload']['name'];
        $fileSize = $_FILES['fileUpload']['size'];
        $fileType = $_FILES['fileUpload']['type'];
        
        // Verifique a extensão do arquivo
        $allowedExtensions = ['xml'];
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            echo json_encode(['status' => 'error', 'message' => 'Formato de arquivo não permitido. Apenas XML é aceito.']);
            exit;
        }
        
        // Verifique o tamanho do arquivo
        if ($fileSize > 10 * 1024 * 1024) { // 10 MB
            echo json_encode(['status' => 'error', 'message' => 'O arquivo é muito grande. O tamanho máximo permitido é 10 MB.']);
            exit;
        }
        
        // Defina o diretório de upload
        $uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Cria o diretório se não existir
}

        
        $destination = $uploadDir . $fileName;
        
        if (move_uploaded_file($fileTmpPath, $destination)) {
            echo json_encode(['status' => 'success', 'message' => 'Arquivo enviado com sucesso!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Ocorreu um erro ao enviar o arquivo.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo enviado ou erro no envio.']);
    }
}
?>
