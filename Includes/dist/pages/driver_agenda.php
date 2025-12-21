<?php
session_start(); 
require __DIR__ . '/../../../auth/dbconfig.php'; 

// 1. Verificação de Segurança
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 2) { 
    header("Location: ../../../index.php"); 
    exit(); 
}

$userId = $_SESSION['user_id']; 
$userName = $_SESSION['name'] ?? 'Condutor';
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// 2. Lógica da Foto de Perfil (Necessária para o novo Header)
$userPhotoPath = '../../dist/assets/img/user2-160x160.jpg'; 
if (isset($_SESSION['profile_photo_path']) && !empty($_SESSION['profile_photo_path'])) {
    $userPhotoPath = '../../../' . $_SESSION['profile_photo_path'];
} else {
    try {
        $stmt = $pdo->prepare("SELECT profile_photo_path FROM Users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['profile_photo_path'])) {
            $userPhotoPath = '../../../' . $result['profile_photo_path'];
            $_SESSION['profile_photo_path'] = $result['profile_photo_path'];
        }
    } catch (PDOException $e) {}
}

// 3. Buscar viagens da data selecionada
try {
    $stmt = $pdo->prepare("
        SELECT s.* FROM Services_Rides sr
        INNER JOIN Services s ON sr.RideID = s.ID
        WHERE sr.UserID = ? AND s.serviceDate = ?
        ORDER BY s.serviceStartTime ASC
    ");
    $stmt->execute([$userId, $selectedDate]);
    $viagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $viagens = []; }
?>
<!doctype html>
<html lang="pt" data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <title>Minha Agenda | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    
    <style>
        /* --- DESIGN SYSTEM (Igual ao driver.php) --- */
        :root {
            --font-primary: 'Inter', sans-serif;
            --font-display: 'Poppins', sans-serif;
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --primary-accent: #4f46e5;
            --primary-hover: #4338ca;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --radius-md: 16px;

            /* UPDATE: Variáveis de Safe Area */
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        [data-bs-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f9fafb;
            --text-muted: #94a3b8;
            --primary-accent: #6366f1;
            --primary-hover: #818cf8;
            --border-color: #334155;
        }

        body {
            font-family: var(--font-primary);
            background-color: var(--bg-body);
            color: var(--text-main);
            /* UPDATE: Padding ajustado para Safe Area */
            padding-bottom: calc(80px + var(--safe-bottom));
            padding-top: 0;
            margin: 0;
        }

        /* --- HEADER --- */
        .app-header {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            /* UPDATE: Padding superior com Safe Top */
            padding: calc(15px + var(--safe-top)) 20px 15px 20px;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 1020;
        }
        .brand-logo { height: 30px; width: auto; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }

        /* --- DATE SELECTOR (Estilo Atualizado) --- */
        .date-selector-card {
            background-color: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
            text-align: center;
        }
        .date-input-styled {
            border: 2px solid var(--border-color);
            background-color: var(--bg-body);
            color: var(--text-main);
            border-radius: 12px;
            padding: 12px 20px;
            font-size: 1.1rem;
            font-family: var(--font-display);
            font-weight: 600;
            outline: none;
            width: 100%;
            text-align: center;
            transition: border-color 0.2s;
        }
        .date-input-styled:focus { border-color: var(--primary-accent); }

        /* --- RIDE CARD (Igual ao driver.php) --- */
        .ride-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 15px;
            padding: 16px;
            position: relative;
            box-shadow: var(--shadow-sm);
        }
        
        .ride-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .ride-time { font-family: var(--font-display); font-size: 1.25rem; font-weight: 700; color: var(--text-main); }
        
        .ride-badge { font-size: 0.7rem; font-weight: 600; padding: 4px 10px; border-radius: 50px; text-transform: uppercase; }
        .badge-private { background: rgba(79, 70, 229, 0.1); color: var(--primary-accent); border: 1px solid rgba(79, 70, 229, 0.2); }
        .badge-shared { background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); }

        /* Timeline inside card */
        .card-timeline { position: relative; padding-left: 20px; border-left: 2px dashed var(--border-color); margin-left: 6px; }
        .ct-point { position: relative; margin-bottom: 15px; }
        .ct-point:last-child { margin-bottom: 0; }
        .ct-dot {
            width: 12px; height: 12px; border-radius: 50%;
            position: absolute; left: -27px; top: 4px;
            border: 2px solid var(--bg-card);
        }
        .dot-pickup { background-color: #10b981; } 
        .dot-dropoff { background-color: #ef4444; }
        
        .ct-text { font-size: 0.95rem; color: var(--text-main); line-height: 1.3; }

        /* Footer do Card */
        .card-footer-info {
            margin-top: 15px; padding-top: 12px; border-top: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }

        /* --- BOTTOM NAV --- */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; width: 100%; 
            /* UPDATE: Altura e Padding com Safe Area */
            height: calc(70px + var(--safe-bottom));
            background-color: var(--bg-card); border-top: 1px solid var(--border-color);
            display: flex; justify-content: space-around; 
            align-items: flex-start; /* Alinha ícones ao topo para não serem cobertos */
            z-index: 1030; 
            padding-bottom: var(--safe-bottom);
            padding-top: 10px;
        }
        .nav-item-mobile {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: var(--text-muted); text-decoration: none; font-size: 0.75rem; font-weight: 500;
            width: 100%; 
            height: 50px; /* Altura fixa clicável */
            transition: color 0.2s;
        }
        .nav-item-mobile i { font-size: 1.5rem; margin-bottom: 4px; }
        .nav-item-mobile.active { color: var(--primary-accent); }
    </style>
  </head>
  <body>
    
    <header class="app-header">
        <img src="../../../assets/images/icons/SyncRide.png" alt="SyncRide" class="brand-logo" id="header-logo">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link text-muted p-0" id="theme-toggle"><i class="bi bi-moon-stars-fill fs-5" id="theme-icon"></i></button>
            <img src="<?php echo $userPhotoPath; ?>" class="user-avatar shadow-sm" alt="User">
        </div>
    </header>

    <div class="container-fluid px-3 pt-3">
        
        <h4 class="fw-bold mb-3 text-main">Minha Agenda 📅</h4>

        <div class="date-selector-card">
            <label class="d-block text-muted small mb-2 fw-bold text-uppercase ls-1">Selecionar Dia</label>
            <form method="GET">
                <input type="date" name="date" class="date-input-styled" value="<?php echo $selectedDate; ?>" onchange="this.form.submit()">
            </form>
        </div>

        <?php if(count($viagens) > 0): ?>
            <?php foreach($viagens as $v): 
                $isPriv = ($v['serviceType'] == 1);
                $badgeClass = $isPriv ? "badge-private" : "badge-shared";
                $badgeText = $isPriv ? "Privado" : "Partilhado";
            ?>
            <div class="ride-card">
                <div class="ride-header">
                    <div class="ride-time"><?php echo substr($v['serviceStartTime'], 0, 5); ?></div>
                    <span class="ride-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                </div>
                
                <div class="card-timeline">
                    <div class="ct-point">
                        <div class="ct-dot dot-pickup"></div>
                        <span class="ct-text"><?php echo htmlspecialchars($v['serviceStartPoint']); ?></span>
                    </div>
                    <div class="ct-point">
                        <div class="ct-dot dot-dropoff"></div>
                        <span class="ct-text"><?php echo htmlspecialchars($v['serviceTargetPoint']); ?></span>
                    </div>
                </div>

                <div class="card-footer-info">
                    <small class="text-muted fw-medium">
                        <i class="bi bi-people-fill me-1"></i>
                        <?php echo $v['paxADT']; ?> ADT, <?php echo $v['paxCHD']; ?> CHD
                    </small>
                    <small class="fw-bold" style="color: var(--primary-accent);">
                        <?php echo htmlspecialchars($v['NomeCliente']); ?>
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5 text-muted opacity-50">
                <i class="bi bi-calendar-x fs-1"></i>
                <p class="mt-3 fw-medium">Livre! Sem serviços para este dia.</p>
            </div>
        <?php endif; ?>

    </div>

    <nav class="bottom-nav">
        <a href="driver.php" class="nav-item-mobile"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
        <a href="driver_agenda.php" class="nav-item-mobile active"><i class="bi bi-calendar3"></i><span>Agenda</span></a>
        <a href="driverstats.php" class="nav-item-mobile"><i class="bi bi-bar-chart-fill"></i><span>Stats</span></a>
        <a href="logout.php" class="nav-item-mobile text-danger"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- Dark Mode Logic (Igual ao driver.php) ---
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const htmlElement = document.documentElement;
        const headerLogo = document.getElementById('header-logo');
        const logoDark = "../../../assets/images/icons/SyncRide.png"; 
        const logoLight = "../../../assets/images/icons/Syncridewhite.png";

        // Carregar tema salvo
        const savedTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-bs-theme', savedTheme);
        updateThemeIcon(savedTheme);
        updateLogo(savedTheme);

        themeToggle.addEventListener('click', () => {
            const newTheme = htmlElement.getAttribute('data-bs-theme') === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            updateLogo(newTheme);
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5';
        }
        
        function updateLogo(theme) {
            const newSrc = theme === 'dark' ? logoLight : logoDark;
            if(headerLogo) headerLogo.src = newSrc;
        }
    </script>
  </body>
</html>