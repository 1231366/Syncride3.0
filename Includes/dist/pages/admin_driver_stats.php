<?php
session_start();

// 1. VERIFICAÇÃO DE ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

require __DIR__ . '/../../../auth/dbconfig.php';

// =================================================================
// 2. LÓGICA DE DADOS
// =================================================================

// Lógica da Foto de Perfil
$defaultPhoto = "../assets/img/user2-160x160.jpg";
$userPhoto = $defaultPhoto;
if (isset($_SESSION['profile_photo_path']) && !empty($_SESSION['profile_photo_path'])) {
    $userPhoto = "../../../" . $_SESSION['profile_photo_path'];
}

$driver_id = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : null;
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$page_title = "Estatísticas Gerais";
$driver_name = "Visão Geral";
$meses_nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

try {
    $available_drivers = $pdo->query("SELECT ID, name FROM Users WHERE role = 2 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $available_years = $pdo->query("SELECT DISTINCT YEAR(serviceDate) as ano FROM Services ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($available_years)) {
        $available_years[] = date('Y');
    }
} catch (PDOException $e) {
    die("Erro BD: " . $e->getMessage());
}

// Valores padrão
$box1_val = 0; $box1_lbl = "-"; $box1_icon = "bi-person-check-fill"; $box1_class = "stat-blue";
$box2_val = 0; $box2_lbl = "-"; $box2_icon = "bi-calendar-day"; $box2_class = "stat-green";
$box3_val = 0; $box3_lbl = "-"; $box3_icon = "bi-calendar-month"; $box3_class = "stat-purple";
$box4_val = 0; $box4_lbl = "-"; $box4_icon = "bi-bar-chart-fill"; $box4_class = "stat-orange";

$chart_labels = $meses_nomes;
$chart_data = array_fill(0, 12, 0);
$table_data = [];
$table_title = "";
$table_is_leaderboard = false;


if ($driver_id) {
    // --- VISTA INDIVIDUAL (Um Condutor) ---
    try {
        $stmt = $pdo->prepare("SELECT name FROM Users WHERE ID = ? AND role = 2");
        $stmt->execute([$driver_id]);
        $driver_name_db = $stmt->fetchColumn();
        if (!$driver_name_db) { header("Location: admin_driver_stats.php"); exit(); }
        $driver_name = $driver_name_db;
        $page_title = "Performance Individual";

        // Caixas
        $stmt = $pdo->prepare("SELECT 
            (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = ? AND s.serviceDate = CURDATE()) AS trips_today,
            (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = ? AND YEARWEEK(s.serviceDate, 1) = YEARWEEK(CURDATE(), 1)) AS trips_week,
            (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = ? AND YEAR(s.serviceDate) = ? AND MONTH(s.serviceDate) = MONTH(CURDATE())) AS trips_month_current,
            (SELECT COUNT(*) FROM Services_Rides sr WHERE sr.UserID = ?) AS trips_total");
        $stmt->execute([$driver_id, $driver_id, $driver_id, $selectedYear, $driver_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $box1_val = $stats['trips_today']; $box1_lbl = "Viagens Hoje"; $box1_icon = "bi-calendar-event";
        $box2_val = $stats['trips_week'];  $box2_lbl = "Esta Semana"; $box2_icon = "bi-calendar-week";
        $box3_val = $stats['trips_month_current']; $box3_lbl = "Este Mês"; $box3_icon = "bi-calendar-month";
        $box4_val = $stats['trips_total']; $box4_lbl = "Total Histórico"; $box4_icon = "bi-trophy-fill";

        // Gráfico
        $sqlChart = "SELECT MONTH(s.serviceDate) AS mes, COUNT(sr.associationID) AS total FROM Services_Rides AS sr JOIN Services AS s ON sr.RideID = s.ID WHERE sr.UserID = ? AND YEAR(s.serviceDate) = ? GROUP BY mes";
        $stmtChart = $pdo->prepare($sqlChart);
        $stmtChart->execute([$driver_id, $selectedYear]);
        $monthly_results = $stmtChart->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($monthly_results as $mes => $total) { $chart_data[$mes - 1] = (int)$total; }

        // Tabela (Histórico)
        $table_title = "Histórico Recente";
        $sqlTable = "SELECT s.ID, s.serviceDate, s.serviceStartTime, s.serviceStartPoint, s.serviceTargetPoint, s.serviceType FROM Services s JOIN Services_Rides sr ON s.ID = sr.RideID WHERE sr.UserID = ? ORDER BY s.serviceDate DESC, s.serviceStartTime DESC LIMIT 10";
        $stmtTable = $pdo->prepare($sqlTable);
        $stmtTable->execute([$driver_id]);
        $table_data = $stmtTable->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) { die("Erro: " . $e->getMessage()); }

} else {
    // --- VISTA GERAL (Todos) ---
    $page_title = "Visão Geral da Frota";
    $table_title = "Top Condutores ($selectedYear)";
    $table_is_leaderboard = true;

    try {
        // Caixas
        $box1_val = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 2")->fetchColumn(); $box1_lbl = "Condutores"; $box1_icon = "bi-people-fill";
        $box2_val = $pdo->query("SELECT COUNT(*) FROM Services s JOIN Services_Rides sr ON s.ID = sr.RideID WHERE s.serviceDate = CURDATE()")->fetchColumn(); $box2_lbl = "Viagens Hoje"; $box2_icon = "bi-car-front-fill";
        $box3_val = $pdo->query("SELECT COUNT(*) FROM Services s JOIN Services_Rides sr ON s.ID = sr.RideID WHERE YEAR(s.serviceDate) = YEAR(CURDATE()) AND MONTH(s.serviceDate) = MONTH(CURDATE())")->fetchColumn(); $box3_lbl = "Viagens Mês"; $box3_icon = "bi-bar-chart-steps";
        $box4_val = $pdo->query("SELECT COUNT(*) FROM Services_Rides")->fetchColumn(); $box4_lbl = "Total Geral"; $box4_icon = "bi-globe";
        
        // Gráfico
        $sqlChart = "SELECT MONTH(s.serviceDate) AS mes, COUNT(sr.associationID) AS total FROM Services_Rides AS sr JOIN Services AS s ON sr.RideID = s.ID WHERE YEAR(s.serviceDate) = ? GROUP BY mes";
        $stmtChart = $pdo->prepare($sqlChart);
        $stmtChart->execute([$selectedYear]);
        $monthly_results = $stmtChart->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($monthly_results as $mes => $total) { $chart_data[$mes - 1] = (int)$total; }

        // Tabela (Leaderboard)
        $sqlTable = "SELECT u.ID, u.name, 
            (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = u.ID AND s.serviceDate = CURDATE()) AS trips_today, 
            (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = u.ID AND YEARWEEK(s.serviceDate, 1) = YEARWEEK(CURDATE(), 1)) AS trips_week, 
            (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = u.ID AND YEAR(s.serviceDate) = ?) AS trips_year, 
            (SELECT COUNT(*) FROM Services_Rides sr WHERE sr.UserID = u.ID) AS trips_total 
            FROM Users u WHERE u.role = 2 ORDER BY trips_year DESC, trips_total DESC, u.name ASC";
        $stmtTable = $pdo->prepare($sqlTable);
        $stmtTable->execute([$selectedYear]);
        $table_data = $stmtTable->fetchAll(PDO::FETCH_ASSOC);
        
        $top3 = array_slice($table_data, 0, 3);
        $rest_of_drivers = array_slice($table_data, 3);

    } catch (PDOException $e) { die("Erro: " . $e->getMessage()); }
}

$js_data = json_encode([
    'labels' => $chart_labels,
    'data' => $chart_data,
    'driver_name' => $driver_name,
    'year' => $selectedYear
]);
?>

<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Estatísticas | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        /* --- DESIGN SYSTEM MODERNO (SaaS) - UNIFICADO --- */
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
            --table-head-bg: #f9fafb;
            
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --radius-md: 16px;
            --radius-sm: 10px;
        }

        [data-bs-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f9fafb;
            --text-muted: #94a3b8;
            --primary-accent: #6366f1;
            --primary-hover: #818cf8;
            --border-color: #334155;
            --table-head-bg: #1e293b;
            --shadow-sm: none;
            --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.5);
        }

        body {
            font-family: var(--font-primary);
            background-color: var(--bg-body);
            color: var(--text-main);
            transition: background-color 0.3s, color 0.3s;
        }

        /* --- NAVBAR & SIDEBAR --- */
        .app-header {
            background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color); height: 70px;
        }
        [data-bs-theme="dark"] .app-header { background: rgba(30, 41, 59, 0.85); }

        .app-sidebar {
            background-color: var(--bg-card); border-right: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .sidebar-brand {
            height: 70px; display: flex; align-items: center; justify-content: center;
            border-bottom: 1px solid var(--border-color);
        }
        .brand-link { text-decoration: none; }
        .brand-image { max-height: 45px; width: auto; transition: transform 0.3s; }
        .brand-image:hover { transform: scale(1.05); }
        
        .sidebar-menu .nav-link {
            color: var(--text-muted); border-radius: var(--radius-sm); margin: 4px 12px;
            padding: 10px 16px; font-weight: 500; transition: all 0.2s;
        }
        .sidebar-menu .nav-link:hover, .sidebar-menu .nav-link.active {
            background-color: var(--primary-accent); color: #fff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        .sidebar-menu .nav-icon { margin-right: 12px; font-size: 1.1rem; }

        /* --- CARDS & STATS --- */
        .card {
            background-color: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;
        }
        .card-header { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; }
        .card-title { font-family: var(--font-display); font-weight: 600; font-size: 1.125rem; color: var(--text-main); }

        .stat-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: var(--radius-md); padding: 1.5rem; position: relative;
            transition: transform 0.2s, box-shadow 0.2s; height: 100%;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
        .stat-icon-wrapper {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 1rem;
        }
        .stat-value {
            font-family: var(--font-display); font-size: 2rem; font-weight: 700;
            line-height: 1; margin-bottom: 0.5rem; color: var(--text-main);
        }
        .stat-label { color: var(--text-muted); font-size: 0.875rem; font-weight: 500; }

        /* Cores Stats */
        .stat-blue .stat-icon-wrapper { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .stat-green .stat-icon-wrapper { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .stat-purple .stat-icon-wrapper { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .stat-orange .stat-icon-wrapper { background: rgba(249, 115, 22, 0.1); color: #f97316; }

        /* --- LISTAS / LEADERBOARD --- */
        .list-group-item {
            background-color: transparent; border: none; border-bottom: 1px solid var(--border-color);
            padding: 1rem; transition: background 0.2s;
        }
        .list-group-item:last-child { border-bottom: none; }
        .list-group-item:hover { background-color: rgba(0,0,0,0.01); }
        
        .leaderboard-rank { 
            width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; 
            border-radius: 50%; font-weight: bold; font-size: 0.8rem; margin-right: 10px; 
        }
        .rank-1 { background-color: #fef3c7; color: #d97706; }
        .rank-2 { background-color: #f3f4f6; color: #4b5563; }
        .rank-3 { background-color: #ffedd5; color: #c2410c; }

        /* --- FILTROS (SELECTS) --- */
        .form-select-modern {
            background-color: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: var(--radius-sm); padding: 0.5rem 2rem 0.5rem 1rem; color: var(--text-main);
            font-size: 0.875rem; cursor: pointer; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 16px 12px;
            appearance: none;
        }
        [data-bs-theme="dark"] .form-select-modern {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23f8f9fa' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }

        /* --- BOTTOM NAV --- */
        .bottom-navbar {
            background-color: var(--bg-card); border-top: 1px solid var(--border-color);
            z-index: 1050; padding-bottom: env(safe-area-inset-bottom);
        }
        .nav-item-bottom { color: var(--text-muted); font-size: 0.75rem; transition: color 0.2s; }
        .nav-item-bottom.active { color: var(--primary-accent); }
        .nav-item-bottom i { font-size: 1.5rem; margin-bottom: 2px; }

        /* --- MOBILE MENU GRID --- */
        .quick-action-btn {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 15px; border-radius: 16px; background-color: var(--bg-body);
            color: var(--text-main); text-decoration: none; border: 1px solid var(--border-color);
            transition: transform 0.1s; height: 100%;
        }
        .quick-action-btn:active { transform: scale(0.96); }
        .quick-action-btn i { font-size: 1.8rem; margin-bottom: 8px; }
        .quick-action-btn span { font-size: 0.8rem; font-weight: 600; }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg">
    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                <i class="bi bi-list text-muted fs-4"></i>
              </a>
            </li>
            <li class="nav-item d-lg-none ms-2 d-flex align-items-center">
                <img src="../../../assets/images/icons/SyncRide.png" id="header-logo" alt="SyncRide" style="height: 30px;">
            </li>
          </ul>
          <ul class="navbar-nav ms-auto align-items-center">
            
            <li class="nav-item me-3">
                <button class="btn btn-link text-muted p-0 border-0" id="theme-toggle">
                    <i class="bi bi-moon-stars-fill fs-5" id="theme-icon"></i>
                </button>
            </li>

            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <img src="<?php echo $userPhoto; ?>" class="user-image rounded-circle shadow-sm" alt="User Image" style="width: 38px; height: 38px; object-fit: cover;">
                <div class="d-none d-md-block text-start lh-1">
                    <span class="d-block fw-semibold text-main small"><?php echo $_SESSION['name']; ?></span>
                    <span class="text-muted" style="font-size: 0.7rem;">Administrador</span>
                </div>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end border-0 shadow-lg rounded-4 overflow-hidden mt-2">
                <li class="user-header bg-primary text-white p-4 text-center">
                  <img src="<?php echo $userPhoto; ?>" class="rounded-circle shadow mb-2 border border-2 border-white" alt="User Image" style="width: 80px; height: 80px; object-fit: cover;">
                  <p class="mb-0 fw-bold"><?php echo $_SESSION['name']; ?></p>
                  <small class="opacity-75">Gestor de Frota</small>
                </li>
                <li class="user-footer p-3 bg-card d-flex justify-content-between">
                  <a href="#" class="btn btn-light btn-sm rounded-pill px-4">Perfil</a>
                  <a href="logout.php" class="btn btn-danger btn-sm rounded-pill px-4">Sair</a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <aside class="app-sidebar">
        <div class="sidebar-brand">
          <a href="./admin.php" class="brand-link">
            <img src="../../../assets/images/icons/SyncRide.png" id="sidebar-logo" alt="SyncRide Logo" class="brand-image" style="opacity: 1;">
          </a>
        </div>
        
        <div class="sidebar-wrapper mt-3">
          <nav>
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
              <li class="nav-header text-muted fw-bold small text-uppercase px-3 mb-2">Visão Geral</li>
              
              <li class="nav-item"><a href="admin.php" class="nav-link"><i class="nav-icon bi bi-grid-fill"></i><p>Dashboard</p></a></li>
              <li class="nav-item"><a href="live_map.php" class="nav-link"><i class="nav-icon bi bi-map-fill"></i><p>Live Map</p></a></li>
              <li class="nav-item"><a href="ManageRides.php" class="nav-link"><i class="nav-icon bi bi-car-front-fill"></i><p>Viagens</p></a></li>
              
              <li class="nav-header text-muted fw-bold small text-uppercase px-3 mb-2 mt-4">Gestão</li>
              
              <li class="nav-item"><a href="manageUsers.php" class="nav-link"><i class="nav-icon bi bi-people-fill"></i><p>Equipa</p></a></li>
              <li class="nav-item"><a href="manageFleet.php" class="nav-link"><i class="nav-icon bi bi-truck-front-fill"></i><p>Frota</p></a></li>
              <li class="nav-item"><a href="financial.php" class="nav-link"><i class="nav-icon bi bi-cash-coin"></i><p>Financeiro</p></a></li>
              <li class="nav-item">
                  <a href="admin_driver_stats.php" class="nav-link active"> 
                      <i class="nav-icon bi bi-bar-chart-fill"></i>
                      <p>Estatísticas</p>
                  </a>
              </li>
              <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-exclamation-triangle-fill"></i><p>No Shows</p></a></li>
              <li class="nav-item"><a href="manageStorage.php" class="nav-link"><i class="nav-icon bi bi-hdd-network-fill"></i><p>Armazenamento</p></a></li>
            </ul>
          </nav>
        </div>
      </aside>

      <div class="bottom-navbar position-fixed bottom-0 w-100 d-lg-none d-flex justify-content-around align-items-center shadow-lg">
        <a href="admin.php" class="nav-item-bottom text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-grid-fill"></i><span>Home</span>
        </a>
        <a href="ManageRides.php" class="nav-item-bottom text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-car-front-fill"></i><span>Viagens</span>
        </a>
        <a href="admin_driver_stats.php" class="nav-item-bottom active text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-bar-chart-fill"></i><span>Stats</span>
        </a>
        <a href="#" class="nav-item-bottom text-decoration-none d-flex flex-column align-items-center" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
            <i class="bi bi-three-dots"></i><span>Menu</span>
        </a>
      </div>

      <div class="offcanvas offcanvas-bottom rounded-top-4" tabindex="-1" id="mobileMenu" style="height: auto; min-height: 40vh;">
        <div class="offcanvas-header pb-0">
          <h5 class="offcanvas-title fw-bold text-main">Menu Rápido</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body pt-3">
            <div class="row g-3">
                <div class="col-4 text-center"><a href="financial.php" class="quick-action-btn shadow-sm"><i class="bi bi-cash-coin text-success"></i><span>Finanças</span></a></div>
                <div class="col-4 text-center"><a href="manageFleet.php" class="quick-action-btn shadow-sm"><i class="bi bi-truck-front-fill text-primary"></i><span>Frota</span></a></div>
                <div class="col-4 text-center"><a href="manageUsers.php" class="quick-action-btn shadow-sm"><i class="bi bi-people-fill text-info"></i><span>Equipa</span></a></div>
                <div class="col-4 text-center"><a href="ManageNoShows.php" class="quick-action-btn shadow-sm"><i class="bi bi-camera-fill text-danger"></i><span>NoShow</span></a></div>
                <div class="col-4 text-center"><a href="manageStorage.php" class="quick-action-btn shadow-sm"><i class="bi bi-hdd-fill text-secondary"></i><span>Storage</span></a></div>
                <div class="col-12 mt-3">
                    <a href="logout.php" class="d-block p-3 rounded-4 bg-light text-decoration-none shadow-sm text-center text-danger fw-bold">
                        <i class="bi bi-box-arrow-right me-2"></i> Sair
                    </a>
                </div>
            </div>
        </div>
      </div>

      <main class="app-main pt-4">
        <div class="app-content">
          <div class="container-fluid">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-0 text-main"><?php echo $page_title; ?></h3>
                    <p class="text-muted mb-0 small">Análise de performance: <?php echo $driver_name; ?>.</p>
                </div>
                
                <form method="GET" action="admin_driver_stats.php" class="d-flex align-items-center gap-2">
                    <select name="driver_id" class="form-select-modern shadow-sm" onchange="this.form.submit()">
                        <option value="">Todos os Condutores</option>
                        <?php foreach ($available_drivers as $driver): ?>
                            <option value="<?php echo $driver['ID']; ?>" <?php echo ($driver['ID'] == $driver_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($driver['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="year" class="form-select-modern shadow-sm" onchange="this.form.submit()" style="min-width: 90px;">
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php if($driver_id): ?>
                        <a href="admin_driver_stats.php" class="btn btn-light btn-sm shadow-sm border border-danger text-danger px-3 py-2 rounded-3"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="row g-4 mb-4">
              <div class="col-lg-3 col-6">
                <div class="stat-card <?php echo $box1_class; ?>">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi <?php echo $box1_icon; ?>"></i></div>
                      <div class="stat-value"><?php echo $box1_val; ?></div>
                      <div class="stat-label"><?php echo $box1_lbl; ?></div>
                  </div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="stat-card <?php echo $box2_class; ?>">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi <?php echo $box2_icon; ?>"></i></div>
                      <div class="stat-value"><?php echo $box2_val; ?></div>
                      <div class="stat-label"><?php echo $box2_lbl; ?></div>
                  </div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="stat-card <?php echo $box3_class; ?>">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi <?php echo $box3_icon; ?>"></i></div>
                      <div class="stat-value"><?php echo $box3_val; ?></div>
                      <div class="stat-label"><?php echo $box3_lbl; ?></div>
                  </div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="stat-card <?php echo $box4_class; ?>">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi <?php echo $box4_icon; ?>"></i></div>
                      <div class="stat-value"><?php echo $box4_val; ?></div>
                      <div class="stat-label"><?php echo $box4_lbl; ?></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8 connectedSortable">
                    <div class="card h-100 border-0">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title fw-bold"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Evolução Mensal</h3>
                        </div>
                        <div class="card-body">
                            <div id="mainChart" style="min-height: 350px;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 connectedSortable">
                    <div class="card h-100 border-0">
                        <div class="card-header border-bottom">
                            <h3 class="card-title fw-bold"><?php echo $table_title; ?></h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (empty($table_data)): ?>
                                    <div class="p-4 text-center text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                        Sem dados para apresentar.
                                    </div>
                                <?php else: ?>
                                    
                                    <?php if ($table_is_leaderboard): ?>
                                        <?php for($i=0; $i<3; $i++): 
                                            if(isset($top3[$i])): 
                                                $rankClass = ($i==0?'rank-1':($i==1?'rank-2':'rank-3'));
                                                $rankIcon = ($i==0?'1':($i==1?'2':'3'));
                                        ?>
                                            <div class="list-group-item d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center">
                                                    <span class="leaderboard-rank <?=$rankClass?>"><?=$rankIcon?></span>
                                                    <div>
                                                        <a href="admin_driver_stats.php?driver_id=<?=$top3[$i]['ID']?>&year=<?=$selectedYear?>" class="fw-bold text-main text-decoration-none stretched-link">
                                                            <?=htmlspecialchars($top3[$i]['name'])?>
                                                        </a>
                                                        <div class="small text-muted">Performance Top</div>
                                                    </div>
                                                </div>
                                                <span class="badge bg-primary-subtle text-primary rounded-pill px-3"><?=$top3[$i]['trips_year']?></span>
                                            </div>
                                        <?php endif; endfor; ?>
                                        
                                        <?php foreach ($rest_of_drivers as $key => $d): ?>
                                             <div class="list-group-item d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center">
                                                    <span class="leaderboard-rank text-muted small"><?=$key + 4?></span>
                                                    <a href="admin_driver_stats.php?driver_id=<?=$d['ID']?>&year=<?=$selectedYear?>" class="text-main text-decoration-none stretched-link">
                                                        <?=htmlspecialchars($d['name'])?>
                                                    </a>
                                                </div>
                                                <span class="badge bg-light text-muted border rounded-pill px-2"><?=$d['trips_year']?></span>
                                            </div>
                                        <?php endforeach; ?>

                                    <?php else: ?>
                                        <?php foreach ($table_data as $row): ?>
                                            <?php 
                                                $icon = $row['serviceType'] == 1 ? '<i class="bi bi-car-front-fill text-primary"></i>' : '<i class="bi bi-bus-front-fill text-warning"></i>';
                                            ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                                    <span class="fw-bold text-main small"><?=htmlspecialchars($row['serviceDate'])?></span>
                                                    <span class="badge bg-light text-dark border"><?=substr($row['serviceStartTime'],0,5)?></span>
                                                </div>
                                                <div class="d-flex align-items-center text-muted small">
                                                    <span class="me-2"><?=$icon?></span>
                                                    <span class="text-truncate" style="max-width: 200px;">
                                                        <?=htmlspecialchars($row['serviceStartPoint'])?> <i class="bi bi-arrow-right mx-1"></i> <?=htmlspecialchars($row['serviceTargetPoint'])?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

          </div>
        </div>
      </main>

      <footer class="app-footer border-top-0 bg-transparent text-center py-4">
        <strong class="text-main">SyncRide</strong> <span class="text-muted small">© 2025</span>
      </footer>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"></script>

    <script>
        // --- Dark Mode & Logo Logic ---
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const htmlElement = document.documentElement;
        
        // Elementos do Logo
        const headerLogo = document.getElementById('header-logo');
        const sidebarLogo = document.getElementById('sidebar-logo');
        const logoDark = "../../../assets/images/icons/SyncRide.png"; 
        const logoLight = "../../../assets/images/icons/Syncridewhite.png";

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
            
            // Atualiza o gráfico se existir
            if(chart) chart.updateOptions({ theme: { mode: newTheme } });
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5';
        }

        function updateLogo(theme) {
            const newSrc = theme === 'dark' ? logoLight : logoDark;
            if(headerLogo) headerLogo.src = newSrc;
            if(sidebarLogo) sidebarLogo.src = newSrc;
        }

        // --- Chart Logic ---
        const chartConfig = <?php echo $js_data; ?>;
        
        const options = {
            series: [{
                name: 'Viagens',
                data: chartConfig.data
            }],
            chart: {
                height: 350,
                type: 'area',
                fontFamily: 'Inter, sans-serif',
                toolbar: { show: false },
                background: 'transparent'
            },
            colors: ['#4f46e5'],
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
            },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: {
                categories: chartConfig.labels,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: '#9ca3af' } }
            },
            yaxis: { labels: { style: { colors: '#9ca3af' } } },
            grid: {
                borderColor: 'rgba(226, 232, 240, 0.5)',
                strokeDashArray: 4,
                yaxis: { lines: { show: true } },
                xaxis: { lines: { show: false } }
            },
            theme: { mode: savedTheme }
        };

        const chart = new ApexCharts(document.querySelector("#mainChart"), options);
        chart.render();
    </script>
  </body>
</html>