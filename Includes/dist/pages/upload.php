<?php
if (isset($_POST['submit'])) {
    $targetDir = "uploads/"; // Pasta onde os ficheiros serão guardados
    $targetFile = $targetDir . basename($_FILES["file"]["name"]);
    $uploadOk = 1;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Verifica se o ficheiro é realmente um ficheiro de imagem (exemplo)
    if (isset($_POST["submit"])) {
        $check = getimagesize($_FILES["file"]["tmp_name"]);
        if ($check !== false) {
            echo "O ficheiro é uma imagem - " . $check["mime"] . ".<br>";
            $uploadOk = 1;
        } else {
            echo "O ficheiro não é uma imagem.<br>";
            $uploadOk = 0;
        }
    }

    // Verifica se o ficheiro já existe
    if (file_exists($targetFile)) {
        echo "Desculpe, o ficheiro já existe.<br>";
        $uploadOk = 0;
    }

    // Limita o tamanho do ficheiro (exemplo: 5MB)
    if ($_FILES["file"]["size"] > 5000000) {
        echo "Desculpe, o ficheiro é demasiado grande.<br>";
        $uploadOk = 0;
    }

    // Limita os tipos de ficheiros permitidos (exemplo: JPG, PNG, JPEG)
    if ($fileType != "jpg" && $fileType != "png" && $fileType != "jpeg") {
        echo "Desculpe, apenas ficheiros JPG, JPEG, PNG são permitidos.<br>";
        $uploadOk = 0;
    }

    // Verifica se há erros durante o upload
    if ($uploadOk == 0) {
        echo "Desculpe, o ficheiro não foi carregado.<br>";
    } else {
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile)) {
            echo "O ficheiro " . basename($_FILES["file"]["name"]) . " foi carregado com sucesso.<br>";
        } else {
            echo "Desculpe, houve um erro ao carregar o ficheiro.<br>";
        }
    }
}
?>
