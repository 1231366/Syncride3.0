<?php
// 1. Iniciar sessão e incluir a configuração (que contém a lógica de Auto-Login)
session_start();
require_once 'auth/dbconfig.php'; 

// 2. Verificar se o utilizador JÁ está logado (Sessão ou Cookie recuperado pelo dbconfig)
if (isset($_SESSION['user_id'])) {
    // Se já estiver logado, redireciona imediatamente para a página correta
    if ($_SESSION['role'] == 1) {
        header("Location: Includes/dist/pages/admin.php");
    } else {
        header("Location: Includes/dist/pages/driver.php");
    }
    exit(); // Importante: parar a execução para não carregar o HTML de login
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="icon" type="image/png" href="assets/images/icons/Syncride.png"/>
    <link rel="stylesheet" type="text/css" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/fonts/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="assets/fonts/iconic/css/material-design-iconic-font.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/animate/animate.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css-hamburgers/hamburgers.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/animsition/css/animsition.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/select2/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" type="text/css" href="assets/css/util.css">
    <link rel="stylesheet" type="text/css" href="assets/css/main.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
    
    <style>
        /* Estilo para a checkbox */
        .remember-me-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            padding-bottom: 20px;
        }
        .remember-check {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .remember-check input {
            margin-right: 8px;
            transform: scale(1.2);
            cursor: pointer;
        }
        .remember-check label {
            font-size: 14px;
            color: #666;
            cursor: pointer;
            margin: 0;
        }
    </style>
</head>
<body>

    <div class="limiter">
        <div class="container-login100">
            <div class="wrap-login100">

                <form class="login100-form validate-form" method="POST" action="auth/auth.php">
                    <span class="login100-form-title p-b-26">
                        Welcome
                    </span>
                    <span class="login100-form-title p-b-48">
                        <img src="assets/images/icons/Syncride.png" alt="Syncride Icon" style="width: 200px; height: 200px;">
                    </span>

                    <div class="wrap-input100 validate-input" data-validate="Valid email is: a@b.c">
                        <input class="input100" type="text" name="email" required>
                        <span class="focus-input100" data-placeholder="Email"></span>
                    </div>

                    <div class="wrap-input100 validate-input" data-validate="Enter password">
                        <span class="btn-show-pass">
                            <i class="zmdi zmdi-eye"></i>
                        </span>
                        <input class="input100" type="password" name="pass" required>
                        <span class="focus-input100" data-placeholder="Password"></span>
                    </div>

                    <div class="remember-me-wrapper">
                        <div class="remember-check">
                            <input type="checkbox" name="remember" id="rememberBox">
                            <label for="rememberBox">Guardar sessão</label>
                        </div>
                    </div>

                    <div class="container-login100-form-btn">
                        <div class="wrap-login100-form-btn">
                            <div class="login100-form-bgbtn"></div>
                            <button class="login100-form-btn">
                                Login
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="dropDownSelect1"></div>

    <script src="assets/vendor/jquery/jquery-3.2.1.min.js"></script>
    <script src="assets/vendor/animsition/js/animsition.min.js"></script>
    <script src="assets/vendor/bootstrap/js/popper.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="assets/vendor/select2/select2.min.js"></script>
    <script src="assets/vendor/daterangepicker/moment.min.js"></script>
    <script src="assets/vendor/daterangepicker/daterangepicker.js"></script>
    <script src="assets/vendor/countdowntime/countdowntime.js"></script>
    <script src="assets/js/main.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
    // Verificar erros na URL
    <?php if (isset($_GET['error'])): ?>
        let errorType = '<?php echo $_GET['error']; ?>';
        let msg = 'Ocorreu um erro.';
        
        if(errorType == 'senha_incorreta') msg = 'Senha incorreta. Tente novamente.';
        else if(errorType == 'utilizador_nao_encontrado') msg = 'Utilizador não encontrado.';
        else if(errorType == 'campos_vazios') msg = 'Preencha todos os campos.';

        toastr.error(msg, 'Erro!', {
            closeButton: true,
            progressBar: true,
            timeOut: 5000,
            positionClass: 'toast-top-right'
        });
    <?php endif; ?>
    </script>

</body>
</html>