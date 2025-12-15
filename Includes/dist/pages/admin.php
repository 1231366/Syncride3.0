<?php
session_start();

// 1. VERIFICAÇÃO DE ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

require __DIR__ . '/../../../auth/dbconfig.php';

// 2. LÓGICA DE DADOS (DASHBOARD ESPECÍFICO)
// =================================================================

// Foto de Perfil
$defaultPhoto = "../assets/img/user2-160x160.jpg";
$userPhoto = $defaultPhoto;
if (isset($_SESSION['profile_photo_path']) && !empty($_SESSION['profile_photo_path'])) {
    $userPhoto = "../../../" . $_SESSION['profile_photo_path'];
}

// Consultas Estatísticas
try {
    // Total Geral
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services"); 
    $stmt->execute(); 
    $totalTodasAsViagens = $stmt->fetchColumn();

    // Viagens Semana Atual
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE WEEK(serviceDate, 1) = WEEK(CURDATE(), 1) AND YEAR(serviceDate) = YEAR(CURDATE())"); 
    $stmt->execute(); 
    $totalViagensSemana = $stmt->fetchColumn();

    // Viagens Hoje
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE serviceDate = CURDATE()"); 
    $stmt->execute(); 
    $totalViagensHoje = $stmt->fetchColumn();

    // Taxa de Conclusão (Semana)
    $stmt = $pdo->prepare("SELECT COUNT(*) AS totalConcluidas FROM Services s INNER JOIN Services_Rides sr ON s.ID = sr.RideID WHERE WEEK(s.serviceDate, 1) = WEEK(CURDATE(), 1) AND YEAR(s.serviceDate) = YEAR(CURDATE())"); 
    $stmt->execute(); 
    $res = $stmt->fetch(); 
    $totalSemanaConcluidas = $res['totalConcluidas'] ?? 0;

    $percentagemSemanaConcluida = $totalViagensSemana > 0 ? round(($totalSemanaConcluidas / $totalViagensSemana) * 100, 2) : 0;

    // Dados do Gráfico (Anual)
    $chartLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    $monthlyDataCounts = array_fill(1, 12, 0);
    $sqlMensal = "SELECT MONTH(serviceDate) AS mes, COUNT(*) AS totalViagens FROM Services WHERE YEAR(serviceDate) = YEAR(CURDATE()) GROUP BY mes ORDER BY mes ASC";
    $stmtMensal = $pdo->prepare($sqlMensal);
    $stmtMensal->execute();
    $resultsMensal = $stmtMensal->fetchAll(PDO::FETCH_ASSOC);
    foreach ($resultsMensal as $row) { 
        $monthlyDataCounts[(int)$row['mes']] = (int)$row['totalViagens']; 
    }
    $chartData = array_values($monthlyDataCounts);

} catch (PDOException $e) {
    die("Erro BD: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Dashboard | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        /* --- DESIGN SYSTEM MODERNO (SaaS) --- */
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
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            height: 70px;
        }
        [data-bs-theme="dark"] .app-header { background: rgba(30, 41, 59, 0.85); }

        .app-sidebar {
            background-color: var(--bg-card);
            border-right: 1px solid var(--border-color);
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

        .stat-blue .stat-icon-wrapper { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .stat-green .stat-icon-wrapper { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .stat-purple .stat-icon-wrapper { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .stat-orange .stat-icon-wrapper { background: rgba(249, 115, 22, 0.1); color: #f97316; }

        /* --- UPLOAD AREA (Dashboard Specific) --- */
        #drop-area {
            border: 2px dashed var(--border-color); background-color: var(--bg-body); border-radius: var(--radius-md);
            padding: 2rem; text-align: center; transition: all 0.3s; cursor: pointer;
        }
        #drop-area:hover, #drop-area.highlight { border-color: var(--primary-accent); background-color: rgba(79, 70, 229, 0.05); }
        .upload-icon { font-size: 2.5rem; color: var(--primary-accent); margin-bottom: 0.5rem; opacity: 0.8; }
        .btn-modern { 
            background-color: var(--primary-accent); color: #fff; border: none; 
            border-radius: var(--radius-sm); padding: 0.75rem 1.5rem; font-weight: 500; transition: background 0.2s; 
        }
        .btn-modern:hover { background-color: var(--primary-hover); color: #fff; }

        /* --- BOTTOM NAV --- */
        .bottom-navbar {
            background-color: var(--bg-card); border-top: 1px solid var(--border-color);
            z-index: 1050; padding-bottom: env(safe-area-inset-bottom);
        }
        .nav-item-bottom { color: var(--text-muted); font-size: 0.75rem; transition: color 0.2s; }
        .nav-item-bottom.active { color: var(--primary-accent); }
        .nav-item-bottom i { font-size: 1.5rem; margin-bottom: 2px; }

        /* --- MOBILE MENU GRID (Adicionado para as apps) --- */
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
              
              <li class="nav-item">
                  <a href="admin.php" class="nav-link active">
                      <i class="nav-icon bi bi-grid-fill"></i>
                      <p>Dashboard</p>
                  </a>
              </li>
              <li class="nav-item">
                  <a href="live_map.php" class="nav-link">
                      <i class="nav-icon bi bi-map-fill"></i>
                      <p>Live Map</p>
                  </a>
              </li>
              <li class="nav-item">
                  <a href="ManageRides.php" class="nav-link">
                      <i class="nav-icon bi bi-car-front-fill"></i>
                      <p>Viagens</p>
                  </a>
              </li>
              
              <li class="nav-header text-muted fw-bold small text-uppercase px-3 mb-2 mt-4">Gestão</li>
              
              <li class="nav-item">
                  <a href="manageUsers.php" class="nav-link">
                      <i class="nav-icon bi bi-people-fill"></i>
                      <p>Equipa</p>
                  </a>
              </li>
              <li class="nav-item">
                  <a href="manageFleet.php" class="nav-link">
                      <i class="nav-icon bi bi-truck-front-fill"></i>
                      <p>Frota</p>
                  </a>
              </li>
              <li class="nav-item">
                  <a href="financial.php" class="nav-link">
                      <i class="nav-icon bi bi-cash-coin"></i>
                      <p>Financeiro</p>
                  </a>
              </li>
              <li class="nav-item">
                  <a href="admin_driver_stats.php" class="nav-link"> 
                      <i class="nav-icon bi bi-bar-chart-fill"></i>
                      <p>Estatísticas</p>
                  </a>
              </li>
              <li class="nav-item">
                  <a href="ManageNoShows.php" class="nav-link">
                      <i class="nav-icon bi bi-exclamation-triangle-fill"></i>
                      <p>No Shows</p>
                  </a>
              </li>
              <li class="nav-item">
                  <a href="manageStorage.php" class="nav-link">
                      <i class="nav-icon bi bi-hdd-network-fill"></i>
                      <p>Armazenamento</p>
                  </a>
              </li>
            </ul>
          </nav>
        </div>
      </aside>

      <div class="bottom-navbar position-fixed bottom-0 w-100 d-lg-none d-flex justify-content-around align-items-center shadow-lg">
        <a href="admin.php" class="nav-item-bottom active text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-grid-fill"></i><span>Home</span>
        </a>
        <a href="ManageRides.php" class="nav-item-bottom text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-car-front-fill"></i><span>Viagens</span>
        </a>
        <a href="live_map.php" class="nav-item-bottom text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-map-fill"></i><span>LiveMap</span>
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
                <div class="col-4 text-center">
                    <a href="financial.php" class="quick-action-btn shadow-sm">
                        <i class="bi bi-cash-coin text-success"></i><span>Finanças</span>
                    </a>
                </div>
                <div class="col-4 text-center">
                    <a href="manageFleet.php" class="quick-action-btn shadow-sm">
                        <i class="bi bi-truck-front-fill text-primary"></i><span>Frota</span>
                    </a>
                </div>
                <div class="col-4 text-center">
                    <a href="manageUsers.php" class="quick-action-btn shadow-sm">
                        <i class="bi bi-people-fill text-info"></i><span>Equipa</span>
                    </a>
                </div>
                <div class="col-4 text-center">
                    <a href="admin_driver_stats.php" class="quick-action-btn shadow-sm">
                        <i class="bi bi-bar-chart-fill text-warning"></i><span>Stats</span>
                    </a>
                </div>
                <div class="col-4 text-center">
                    <a href="ManageNoShows.php" class="quick-action-btn shadow-sm">
                        <i class="bi bi-camera-fill text-danger"></i><span>NoShow</span>
                    </a>
                </div>
                <div class="col-4 text-center">
                    <a href="manageStorage.php" class="quick-action-btn shadow-sm">
                        <i class="bi bi-hdd-fill text-secondary"></i><span>Storage</span>
                    </a>
                </div>
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
                    <h3 class="fw-bold mb-0 text-main">Dashboard</h3>
                    <p class="text-muted mb-0 small">Visão geral em <?php echo date('d/m/Y'); ?>.</p>
                </div>
            </div>

            <div class="row g-4 mb-4">
              <div class="col-lg-3 col-6">
                <div class="stat-card stat-blue">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi bi-globe-americas"></i></div>
                      <div class="stat-value"><?= $totalTodasAsViagens ?></div>
                      <div class="stat-label">Total Histórico</div>
                  </div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="stat-card stat-green">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi bi-calendar-check"></i></div>
                      <div class="stat-value"><?= $totalViagensSemana ?></div>
                      <div class="stat-label">Esta Semana</div>
                  </div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="stat-card stat-purple">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi bi-pie-chart-fill"></i></div>
                      <div class="stat-value"><?= $percentagemSemanaConcluida ?>%</div>
                      <div class="stat-label">Taxa Conclusão</div>
                  </div>
                  <div class="progress mt-3" style="height: 4px;"><div class="progress-bar bg-primary" style="width: <?= $percentagemSemanaConcluida ?>%"></div></div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="stat-card stat-orange">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi bi-clock-history"></i></div>
                      <div class="stat-value"><?= $totalViagensHoje ?></div>
                      <div class="stat-label">Viagens Hoje</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8 connectedSortable">
                    <div class="card h-100 border-0">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title fw-bold"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Performance Anual</h3>
                        </div>
                        <div class="card-body">
                            <div id="revenue-chart" style="min-height: 350px;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 connectedSortable">
                    <div class="card h-100 border-0">
                        <div class="card-header border-bottom">
                            <h3 class="card-title fw-bold">Importar Viagens</h3>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center">
                            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                <div class="mb-4">
                                    <div id="drop-area">
                                        <i class="bi bi-cloud-arrow-up-fill upload-icon d-block"></i>
                                        <h6 class="fw-bold mb-1 text-main">Arraste o XML</h6>
                                        <p class="text-muted small mb-0">ou clique para procurar</p>
                                        <input type="file" name="xmlFile" id="xmlFile" accept=".xml" class="d-none" required>
                                        <div id="file-name" class="mt-3 badge bg-light text-dark border p-2 d-none">
                                            <i class="bi bi-file-earmark-code me-1"></i> <span id="file-name-txt"></span>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-modern w-100 shadow-sm">
                                    <i class="bi bi-check-lg me-2"></i> Processar
                                </button>
                            </form>

                            <?php 
                                // LÓGICA DE PROCESSAMENTO DO XML
                                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['xmlFile']) && $_FILES['xmlFile']['error'] == 0) {
                                    $xmlContent = file_get_contents($_FILES['xmlFile']['tmp_name']);
                                    $xml = simplexml_load_string($xmlContent);
                                    if($xml){
                                        $sql = "INSERT INTO Services (ID, serviceDate, serviceStartTime, paxADT, paxCHD, serviceStartPoint, serviceTargetPoint, FlightNumber, NomeCliente, ClientNumber, serviceType) VALUES (:ID, :serviceDate, :serviceStartTime, :paxADT, :paxCHD, :serviceStartPoint, :serviceTargetPoint, :FlightNumber, :NomeCliente, :ClientNumber, :serviceType) ON DUPLICATE KEY UPDATE serviceDate = VALUES(serviceDate), serviceStartTime = VALUES(serviceStartTime), paxADT = VALUES(paxADT), paxCHD = VALUES(paxCHD), serviceStartPoint = VALUES(serviceStartPoint), serviceTargetPoint = VALUES(serviceTargetPoint), FlightNumber = VALUES(FlightNumber), NomeCliente = VALUES(NomeCliente), ClientNumber = VALUES(ClientNumber), serviceType = VALUES(serviceType)";
                                        $stmt = $pdo->prepare($sql);
                                        $success = false;
                                        if (isset($xml->Groupings->Grouping)) {
                                            foreach ($xml->Groupings->Grouping as $service) {
                                                $vehicleType = (string) $service->serviceUnitVehicleName;
                                                $serviceType = ($vehicleType == 'Taxi') ? 0 : 1;
                                                $serviceData = [
                                                    'ID' => (string) $service->serviceId,
                                                    'serviceDate' => (string) $service->serviceDate,
                                                    'serviceStartTime' => (string) $service->serviceStartTime,
                                                    'paxADT' => (int) $service->bookings->bookingItem->paxADT,
                                                    'paxCHD' => (int) $service->bookings->bookingItem->paxCHD,
                                                    'serviceStartPoint' => (string) $service->serviceStartPoint,
                                                    'serviceTargetPoint' => (string) $service->serviceTargetPoint,
                                                    'NomeCliente' => (string) $service->bookings->bookingItem->paxLeadName,
                                                    'ClientNumber' => isset($service->bookings->bookingItem->remarks) ? (preg_match('/Phone number: (\+?\d+)/', (string)$service->bookings->bookingItem->remarks, $matches) ? $matches[1] : 'N/A') : 'N/A',
                                                    'serviceType' => $serviceType
                                                ];
                                                $flightCodeNumber = 'N/A';
                                                if (isset($service->bookings->bookingItem->pickup->pickupPoint->flightNumber) && (string)$service->bookings->bookingItem->pickup->pickupPoint->flightNumber !== "") {
                                                    $flightCodeNumber = (string)$service->bookings->bookingItem->pickup->pickupPoint->flightCompanyCode . ' ' . (string)$service->bookings->bookingItem->pickup->pickupPoint->flightNumber;
                                                } elseif (isset($service->bookings->bookingItem->dropoff->pickupPoint->flightNumber) && (string)$service->bookings->bookingItem->dropoff->pickupPoint->flightNumber !== "") {
                                                    $flightCodeNumber = (string)$service->bookings->bookingItem->dropoff->pickupPoint->flightCompanyCode . ' ' . (string)$service->bookings->bookingItem->dropoff->pickupPoint->flightNumber;
                                                }
                                                $serviceData['FlightNumber'] = $flightCodeNumber;
                                                try { $stmt->execute($serviceData); $success = true; } catch (PDOException $e) {}
                                            }
                                        }
                                        if ($success) echo "<script>document.addEventListener('DOMContentLoaded', function(){ toastr.success('Dados importados com sucesso!', 'SyncRide'); });</script>";
                                    }
                                }
                            ?>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"></script>

    <script>
        // --- Dark Mode & Logo Logic (Preservada) ---
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const htmlElement = document.documentElement;
        
        // Elementos do Logo
        const headerLogo = document.getElementById('header-logo');
        const sidebarLogo = document.getElementById('sidebar-logo');

        // Caminhos das imagens
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
            if(typeof sales_chart !== 'undefined') sales_chart.updateOptions({ theme: { mode: newTheme } });
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5';
        }

        function updateLogo(theme) {
            // No modo dark do bootstrap, usamos o logo claro (branco), e vice versa, ou conforme sua preferência
            // Ajuste aqui se a lógica for inversa
            const newSrc = theme === 'dark' ? logoLight : logoDark;
            
            if(headerLogo) headerLogo.src = newSrc;
            if(sidebarLogo) sidebarLogo.src = newSrc;
        }

        // --- Gráfico (Moderno) ---
        const options = {
            series: [{
                name: 'Viagens',
                data: <?php echo json_encode($chartData); ?>
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
                categories: <?php echo json_encode($chartLabels); ?>,
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

        var sales_chart = new ApexCharts(document.querySelector("#revenue-chart"), options);
        sales_chart.render();

        // --- Drag & Drop (XML) ---
        const dropArea = document.getElementById('drop-area');
        const fileInput = document.getElementById('xmlFile');
        const fileNameTxt = document.getElementById('file-name-txt');
        const fileNameBadge = document.getElementById('file-name');

        if(dropArea) {
            dropArea.addEventListener('click', () => fileInput.click());
            dropArea.addEventListener('dragover', (e) => { e.preventDefault(); dropArea.classList.add('highlight'); });
            dropArea.addEventListener('dragleave', () => { dropArea.classList.remove('highlight'); });
            dropArea.addEventListener('drop', (e) => { 
                e.preventDefault(); dropArea.classList.remove('highlight');
                const files = e.dataTransfer.files; if (files.length) handleFile(files[0]);
            });
            fileInput.addEventListener('change', (e) => { if (e.target.files.length) handleFile(e.target.files[0]); });
        }

        function handleFile(file) {
            const dataTransfer = new DataTransfer(); dataTransfer.items.add(file); fileInput.files = dataTransfer.files;
            fileNameTxt.textContent = file.name; fileNameBadge.classList.remove('d-none');
            dropArea.style.borderColor = '#10b981'; dropArea.style.backgroundColor = 'rgba(16, 185, 129, 0.05)';
        }
    </script>
  </body>
</html>