<?php
session_start();

// 1. VERIFICAÇÃO DE ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

require __DIR__ . '/../../../auth/dbconfig.php';

// 2. Lógica da Foto de Perfil
$defaultPhoto = "../assets/img/user2-160x160.jpg";
$userPhoto = $defaultPhoto;
if (isset($_SESSION['profile_photo_path']) && !empty($_SESSION['profile_photo_path'])) {
    $userPhoto = "../../../" . $_SESSION['profile_photo_path'];
}

// 3. BUSCAR DADOS
try {
    // Buscar Veículos (Assumindo que Users.assigned_vehicle_id liga ao ID do carro)
    $stmt = $pdo->query("
        SELECT 
            v.*, 
            u.name AS assigned_driver_name,
            u.id AS assigned_driver_user_id
        FROM Vehicles v
        LEFT JOIN Users u ON u.assigned_vehicle_id = v.id 
        ORDER BY v.status DESC, v.brand ASC
    ");
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar Condutores para o Select
    $stmtDrivers = $pdo->query("SELECT id, name FROM Users WHERE role = 2 ORDER BY name ASC");
    $drivers = $stmtDrivers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $vehicles = []; $drivers = [];
}

// 4. CÁLCULOS DE ALERTAS
$alerts = 0;
$totalVehicles = count($vehicles);
$activeVehicles = 0;

foreach($vehicles as $v) {
    if($v['status'] == 1) $activeVehicles++;
    
    // Validar datas para evitar erros
    if (!empty($v['inspection_date']) && !empty($v['insurance_date'])) {
        $today = new DateTime();
        $insp = new DateTime($v['inspection_date']);
        $insu = new DateTime($v['insurance_date']);
        
        // Alerta se faltarem menos de 30 dias
        if($today->diff($insp)->format("%r%a") < 30 || $today->diff($insu)->format("%r%a") < 30) {
            $alerts++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Frota | SyncRide</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        /* --- DESIGN SYSTEM MODERNO --- */
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
            --table-head-bg: #f8f9fa;
            
            /* Inputs Light */
            --input-bg: #f9fafb;
            --input-border: #e5e7eb;
            --input-text: #111827;
            --input-placeholder: #9ca3af;
            
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --radius-md: 16px;
            --radius-sm: 10px;

            /* CORREÇÃO 2: Variáveis de Safe Area */
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
            --table-head-bg: #1e293b;
            
            /* Inputs Dark */
            --input-bg: #334155;
            --input-border: #475569;
            --input-text: #f8fafc;
            --input-placeholder: #cbd5e1;

            --shadow-sm: none;
            --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.5);
        }

        body {
            font-family: var(--font-primary);
            background-color: var(--bg-body);
            color: var(--text-main);
            transition: background-color 0.3s, color 0.3s;
            
            /* CORREÇÃO 3: Padding para Mobile (Menu Inferior) */
            padding-bottom: calc(80px + var(--safe-bottom)); 
            padding-top: 0;
            margin: 0;
            min-height: 100vh;
        }

        /* Ajuste Desktop: Remove padding bottom excessivo */
        @media (min-width: 992px) {
            body { padding-bottom: 0; }
        }

        /* --- NAVBAR & SIDEBAR (CORRIGIDOS) --- */
        .app-header {
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            
            /* Header Sticky e com Padding do Notch */
            padding-top: var(--safe-top); 
            height: calc(70px + var(--safe-top));
            display: flex; align-items: center;
            
            position: sticky; top: 0; z-index: 1020;
        }
        [data-bs-theme="dark"] .app-header { background: rgba(30, 41, 59, 0.85); }

        .app-sidebar {
            background-color: var(--bg-card);
            border-right: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            top: 0; 
            padding-top: 0;
        }
        .sidebar-brand {
            /* Alinhamento com o header */
            height: calc(70px + var(--safe-top)); 
            padding-top: var(--safe-top);
            display: flex; align-items: center; justify-content: center;
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
        .card-header-custom { padding: 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; flex-wrap: wrap; align-items: center; gap: 15px; justify-content: space-between; }

        .stat-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: var(--radius-md); padding: 1.5rem; position: relative;
            transition: transform 0.2s, box-shadow 0.2s; height: 100%;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
        .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem; }
        .stat-value { font-family: var(--font-display); font-size: 2rem; font-weight: 700; line-height: 1; margin-bottom: 0.5rem; color: var(--text-main); }
        .stat-label { color: var(--text-muted); font-size: 0.875rem; font-weight: 500; }

        .stat-blue .stat-icon-wrapper { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .stat-green .stat-icon-wrapper { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .stat-orange .stat-icon-wrapper { background: rgba(249, 115, 22, 0.1); color: #f97316; }

        /* --- INPUTS & MODALS OTIMIZADOS --- */
        .modal-content { background-color: var(--bg-card); border: 1px solid var(--border-color); }
        .modal-header, .modal-footer { border-color: var(--border-color); }
        
        .form-control-custom, .form-select-custom {
            background-color: var(--input-bg); border: 1px solid var(--input-border);
            color: var(--input-text); border-radius: var(--radius-sm); padding: 0.6rem 0.8rem; font-size: 0.95rem; display: block; width: 100%;
        }
        .form-control-custom:focus, .form-select-custom:focus {
            outline: none; border-color: var(--primary-accent); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        [data-bs-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }

        /* Botões de Ação na Tabela */
        .btn-action {
            background: transparent; border: 1px solid var(--border-color); color: var(--text-muted);
            transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 50%; padding: 0;
        }
        .btn-action:hover { background-color: var(--bg-body); color: var(--primary-accent); border-color: var(--primary-accent); }
        .btn-action.delete:hover { color: #ef4444; border-color: #ef4444; }

        /* --- MOBILE MODAL TWEAKS --- */
        @media (max-width: 576px) {
            .modal-body { padding: 1rem !important; } 
            .modal-title { font-size: 1.1rem; }
            .form-control-custom, .form-select-custom { padding: 0.5rem 0.7rem; font-size: 0.9rem; min-height: 38px; }
            .row.g-3 { --bs-gutter-y: 0.75rem; }
            .form-label { margin-bottom: 0.2rem; font-size: 0.75rem !important; }
        }

        /* --- BOTTOM NAV & BUTTONS (CORRIGIDA) --- */
        .bottom-navbar {
            position: fixed; bottom: 0; left: 0; width: 100%;
            height: calc(70px + var(--safe-bottom));
            background-color: var(--bg-card); border-top: 1px solid var(--border-color);
            display: flex; justify-content: space-around; align-items: flex-start;
            z-index: 1030; 
            padding-bottom: var(--safe-bottom);
            padding-top: 10px;
        }
        .nav-item-bottom { 
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: var(--text-muted); text-decoration: none; font-size: 0.75rem; font-weight: 500;
            width: 100%; height: 50px; transition: color 0.2s;
        }
        .nav-item-bottom.active { color: var(--primary-accent); }
        .nav-item-bottom i { font-size: 1.5rem; margin-bottom: 2px; }

        .btn-modern { background-color: var(--primary-accent); color: #fff; border: none; border-radius: var(--radius-sm); padding: 0.5rem 1.2rem; font-weight: 500; transition: background 0.2s; }
        .btn-modern:hover { background-color: var(--primary-hover); color: #fff; }

        .quick-action-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px; border-radius: 16px; background-color: var(--bg-body); color: var(--text-main); text-decoration: none; border: 1px solid var(--border-color); transition: transform 0.1s; height: 100%; }
        .quick-action-btn:active { transform: scale(0.96); }
        .quick-action-btn i { font-size: 1.8rem; margin-bottom: 8px; }
        .quick-action-btn span { font-size: 0.8rem; font-weight: 600; }
        
        /* Tabela Desktop */
        .table { --bs-table-bg: transparent; --bs-table-color: var(--text-main); border-color: var(--border-color); width: 100%; }
        .table td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid var(--border-color); }
        .table thead th { background-color: var(--table-head-bg) !important; color: var(--text-muted); border-bottom: 1px solid var(--border-color); font-weight: 600; padding: 1rem; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        .badge-plate { font-family: monospace; font-size: 0.9rem; background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 4px; }

        /* Mobile Card View */
        @media (max-width: 991.98px) {
            .search-and-buttons { flex-direction: column; gap: 10px; width: 100%; }
            #filter-container { width: 100%; }
            #fleetTable thead { display: none; }
            #fleetTable tbody tr {
                display: flex; flex-direction: column; position: relative;
                background: var(--bg-card); border: 1px solid var(--border-color);
                border-radius: 12px; margin-bottom: 10px; padding: 12px; box-shadow: var(--shadow-sm);
            }
            #fleetTable tbody td { display: block; border: none !important; padding: 2px 0; width: 100% !important; margin-bottom: 4px; }
            
            /* Status Badge (Topo Direito) */
            #fleetTable tbody td:nth-child(1) { position: absolute; top: 12px; right: 12px; width: auto !important; text-align: right; margin: 0; padding: 0 !important; }
            
            /* Viatura */
            #fleetTable tbody td:nth-child(2) { font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 2px; padding-right: 60px !important; }
            
            /* Matrícula */
            #fleetTable tbody td:nth-child(3) { margin-bottom: 8px; }
            
            /* Condutor */
            #fleetTable tbody td:nth-child(4) { font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; margin-bottom: 6px; }
            #fleetTable tbody td:nth-child(4):before { content: "\F4E1"; font-family: "bootstrap-icons"; margin-right: 6px; color: var(--primary-accent); }
            
            /* Inspeção e Seguro */
            #fleetTable tbody td:nth-child(5) { font-size: 0.8rem; color: var(--text-muted); display: flex; justify-content: space-between; border-top: 1px dashed var(--border-color) !important; padding-top: 6px !important; margin-top: 4px; }
            #fleetTable tbody td:nth-child(5):before { content: "Inspeção:"; font-weight: 600; }
            
            #fleetTable tbody td:nth-child(6) { font-size: 0.8rem; color: var(--text-muted); display: flex; justify-content: space-between; padding-bottom: 6px !important; margin-bottom: 0px; }
            #fleetTable tbody td:nth-child(6):before { content: "Seguro:"; font-weight: 600; }

            /* Ações Flutuantes no Card */
            #fleetTable tbody td:last-child {
                display: flex; gap: 8px; margin-top: 8px; border-top: 1px solid var(--border-color) !important;
                padding-top: 10px !important; justify-content: flex-end;
            }
        }

        .dataTables_filter input { background-color: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-main); border-radius: 50px; padding: 6px 15px; width: 100%; }
        .dataTables_length { display: none; }
        
        .vehicle-photo-preview { width: 100%; max-height: 150px; object-fit: cover; border-radius: 12px; margin-bottom: 10px; border: 1px solid var(--border-color); display: none; }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg">
    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list text-muted fs-4"></i></a>
            </li>
            <li class="nav-item d-lg-none ms-2 d-flex align-items-center">
                <img src="../../../assets/images/icons/Syncride.png" id="header-logo" alt="SyncRide" style="height: 30px;">
            </li>
          </ul>
          <ul class="navbar-nav ms-auto align-items-center">
            <li class="nav-item me-3">
                <button class="btn btn-link text-muted p-0 border-0" id="theme-toggle"><i class="bi bi-moon-stars-fill fs-5" id="theme-icon"></i></button>
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
          <a href="./admin.php" class="brand-link"><img src="../../../assets/images/icons/Syncride.png" id="sidebar-logo" alt="SyncRide Logo" class="brand-image" style="opacity: 1;"></a>
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
              <li class="nav-item"><a href="manageFleet.php" class="nav-link active"><i class="nav-icon bi bi-truck-front-fill"></i><p>Frota</p></a></li>
              <li class="nav-item"><a href="financial.php" class="nav-link"><i class="nav-icon bi bi-cash-coin"></i><p>Financeiro</p></a></li>
              <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link"><i class="nav-icon bi bi-bar-chart-fill"></i><p>Estatísticas</p></a></li>
              <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-exclamation-triangle-fill"></i><p>No Shows</p></a></li>
              <li class="nav-item"><a href="manageStorage.php" class="nav-link"><i class="nav-icon bi bi-hdd-network-fill"></i><p>Armazenamento</p></a></li>
            </ul>
          </nav>
        </div>
      </aside>

      <div class="bottom-navbar d-lg-none shadow-lg">
        <a href="admin.php" class="nav-item-bottom text-decoration-none d-flex flex-column align-items-center"><i class="bi bi-grid-fill"></i><span>Home</span></a>
        <a href="ManageRides.php" class="nav-item-bottom text-decoration-none d-flex flex-column align-items-center"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
        <a href="manageFleet.php" class="nav-item-bottom active text-decoration-none d-flex flex-column align-items-center"><i class="bi bi-truck-front-fill"></i><span>Frota</span></a>
        <a href="#" class="nav-item-bottom text-decoration-none d-flex flex-column align-items-center" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"><i class="bi bi-three-dots"></i><span>Menu</span></a>
      </div>

      <div class="offcanvas offcanvas-bottom rounded-top-4" tabindex="-1" id="mobileMenu" style="height: auto; min-height: 40vh; z-index: 2000;">
        <div class="offcanvas-header pb-0"><h5 class="offcanvas-title fw-bold text-main">Menu Rápido</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
        <div class="offcanvas-body pt-3">
            <div class="row g-3">
                <div class="col-4 text-center"><a href="financial.php" class="quick-action-btn shadow-sm"><i class="bi bi-cash-coin text-success"></i><span>Finanças</span></a></div>
                <div class="col-4 text-center"><a href="manageFleet.php" class="quick-action-btn shadow-sm"><i class="bi bi-truck-front-fill text-primary"></i><span>Frota</span></a></div>
                <div class="col-4 text-center"><a href="manageUsers.php" class="quick-action-btn shadow-sm"><i class="bi bi-people-fill text-info"></i><span>Equipa</span></a></div>
                <div class="col-4 text-center"><a href="admin_driver_stats.php" class="quick-action-btn shadow-sm"><i class="bi bi-bar-chart-fill text-warning"></i><span>Stats</span></a></div>
                <div class="col-4 text-center"><a href="ManageNoShows.php" class="quick-action-btn shadow-sm"><i class="bi bi-camera-fill text-danger"></i><span>NoShow</span></a></div>
                <div class="col-4 text-center"><a href="manageStorage.php" class="quick-action-btn shadow-sm"><i class="bi bi-hdd-fill text-secondary"></i><span>Storage</span></a></div>
                <div class="col-12 mt-3"><a href="logout.php" class="d-block p-3 rounded-4 bg-light text-decoration-none shadow-sm text-center text-danger fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Sair</a></div>
            </div>
        </div>
      </div>

      <main class="app-main pt-4">
        <div class="app-content">
          <div class="container-fluid">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-3">
                <div><h3 class="fw-bold mb-0 text-main">Gestão de Frota</h3><p class="text-muted mb-0 small">Manutenção de veículos e atribuição de condutores.</p></div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-4 col-6"><div class="stat-card stat-blue"><div><div class="stat-icon-wrapper"><i class="bi bi-truck"></i></div><div class="stat-value"><?= $totalVehicles ?></div><div class="stat-label">Total Veículos</div></div></div></div>
                <div class="col-lg-4 col-6"><div class="stat-card stat-green"><div><div class="stat-icon-wrapper"><i class="bi bi-check-circle-fill"></i></div><div class="stat-value"><?= $activeVehicles ?></div><div class="stat-label">Ativos</div></div></div></div>
                <div class="col-lg-4 col-12"><div class="stat-card stat-orange"><div><div class="stat-icon-wrapper"><i class="bi bi-exclamation-triangle-fill"></i></div><div class="stat-value"><?= $alerts ?></div><div class="stat-label">Alertas (Doc)</div></div></div></div>
            </div>

            <div class="card border-0">
                <div class="card-header-custom">
                    <h3 class="card-title fw-bold"><i class="bi bi-truck-front-fill me-2 text-primary"></i> Viaturas</h3>
                    <div class="search-and-buttons d-flex align-items-center">
                        <div id="filter-container" class="flex-grow-1 me-2" style="min-width: 200px;"></div>
                        <button class="btn btn-modern shadow-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#modalVehicle">
                            <i class="bi bi-plus-lg me-1"></i> <span class="d-none d-sm-inline">Adicionar</span>
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="fleetTable" class="table table-hover align-middle mb-0 w-100">
                            <thead>
                                <tr>
                                    <th class="ps-4">Estado</th>
                                    <th>Viatura</th>
                                    <th>Matrícula</th>
                                    <th>Condutor</th>
                                    <th>Inspeção</th>
                                    <th>Seguro</th>
                                    <th class="text-end pe-4">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($vehicles as $v): 
                                    $today = new DateTime();
                                    $inspDate = new DateTime($v['inspection_date']); $diffInsp = $today->diff($inspDate)->format("%r%a");
                                    $insuDate = new DateTime($v['insurance_date']); $diffInsu = $today->diff($insuDate)->format("%r%a");
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php if($v['status'] == 1): ?><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Ativo</span>
                                        <?php else: ?><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Inativo</span><?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($v['brand'] . ' ' . $v['model']); ?></td>
                                    <td><span class="badge-plate"><?php echo htmlspecialchars($v['license_plate']); ?></span></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($v['assigned_driver_name'] ?? '-'); ?></td>
                                    <td class="<?php echo ($diffInsp < 30) ? 'text-danger fw-bold' : 'text-muted'; ?>"><?php echo date('d/m/Y', strtotime($v['inspection_date'])); ?></td>
                                    <td class="<?php echo ($diffInsu < 30) ? 'text-danger fw-bold' : 'text-muted'; ?>"><?php echo date('d/m/Y', strtotime($v['insurance_date'])); ?></td>
                                    <td class="pe-4 text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button class="btn-action edit-btn" data-json='<?php echo json_encode($v); ?>' title="Editar"><i class="bi bi-pencil"></i></button>
                                            <a href="save_vehicle.php?action=delete&id=<?php echo $v['id']; ?>" class="btn-action delete" onclick="return confirm('Tem a certeza que deseja apagar?');" title="Apagar"><i class="bi bi-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

          </div>
        </div>
      </main>

      <footer class="app-footer border-top-0 bg-transparent text-center py-4"><strong class="text-main">SyncRide</strong> <span class="text-muted small">© 2025</span></footer>
    </div>

    <div class="modal fade" id="modalVehicle" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg" style="background-color: var(--bg-card); color: var(--text-main);">
          <form id="vehicleForm" action="save_vehicle.php" method="POST" enctype="multipart/form-data">
              <div class="modal-header border-bottom-0 pb-0">
                  <h5 class="modal-title fw-bold ps-2" id="modalTitle">Adicionar Veículo</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-4">
                <input type="hidden" name="vehicle_id" id="vehicle_id">
                
                <div class="text-center mb-4">
                    <img id="currentVehiclePhoto" src="" class="vehicle-photo-preview">
                    <label class="btn btn-light btn-sm border w-100 rounded-pill"><i class="bi bi-camera me-2"></i> Carregar Foto <input type="file" name="vehicle_photo" id="vehicle_photo_input" hidden accept="image/*"></label>
                    <input type="hidden" name="existing_photo_path" id="existing_photo_path">
                </div>
                
                <div class="row g-3">
                    <div class="col-6"><label class="form-label small text-muted fw-bold">Marca</label><input type="text" name="brand" id="brand" class="form-control-custom" required></div>
                    <div class="col-6"><label class="form-label small text-muted fw-bold">Modelo</label><input type="text" name="model" id="model" class="form-control-custom" required></div>
                    
                    <div class="col-12"><label class="form-label small text-muted fw-bold">Matrícula</label><input type="text" name="license_plate" id="license_plate" class="form-control-custom text-center fw-bold" style="letter-spacing: 2px;" required></div>
                    
                    <div class="col-6"><label class="form-label small text-muted fw-bold">Inspeção</label><input type="date" name="inspection_date" id="inspection_date" class="form-control-custom" required></div>
                    <div class="col-6"><label class="form-label small text-muted fw-bold">Seguro</label><input type="date" name="insurance_date" id="insurance_date" class="form-control-custom" required></div>
                    
                    <div class="col-12"><label class="form-label small text-muted fw-bold">Condutor Atribuído</label><select name="assigned_driver_id" id="assigned_driver_id" class="form-select-custom"><option value="">Nenhum</option><?php foreach($drivers as $driver): ?><option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['name']); ?></option><?php endforeach; ?></select></div>
                    
                    <div class="col-12"><label class="form-label small text-muted fw-bold">Estado</label><select name="status" id="status" class="form-select-custom"><option value="1">Ativo</option><option value="0">Inativo</option></select></div>
                </div>
              </div>
              <div class="modal-footer border-top-0 pt-0 px-4 pb-4">
                <button type="submit" class="btn btn-modern w-100 rounded-pill shadow-sm">Guardar</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <script>
        // --- Dark Mode & Logo Logic ---
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const htmlElement = document.documentElement;
        const headerLogo = document.getElementById('header-logo');
        const sidebarLogo = document.getElementById('sidebar-logo');
        const logoDark = "../../../assets/images/icons/Syncride.png"; 
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
        });

        function updateThemeIcon(theme) { themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5'; }
        function updateLogo(theme) { const newSrc = theme === 'dark' ? logoLight : logoDark; if(headerLogo) headerLogo.src = newSrc; if(sidebarLogo) sidebarLogo.src = newSrc; }

        $(document).ready(function() {
            var table = $('#fleetTable').DataTable({
                language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json", search: "", searchPlaceholder: "Procurar..." },
                pageLength: 20, lengthChange: false, ordering: false, dom: 'rtp'
            });
            $('#fleetTable_filter').appendTo('#filter-container');
            
            $(document).on('click', '.edit-btn', function() {
                const data = $(this).data('json');
                $('#modalTitle').text('Editar Veículo');
                $('#vehicle_id').val(data.id);
                $('#brand').val(data.brand);
                $('#model').val(data.model);
                $('#license_plate').val(data.license_plate);
                $('#inspection_date').val(data.inspection_date);
                $('#insurance_date').val(data.insurance_date);
                $('#status').val(data.status);
                if(data.assigned_driver_user_id) { $('#assigned_driver_id').val(data.assigned_driver_user_id); } 
                else if (data.assigned_driver_id) { $('#assigned_driver_id').val(data.assigned_driver_id); } 
                else { $('#assigned_driver_id').val(""); }

                if (data.photo_path) { $('#currentVehiclePhoto').attr('src', data.photo_path).show(); $('#existing_photo_path').val(data.photo_path); } 
                else { $('#currentVehiclePhoto').hide(); $('#existing_photo_path').val(''); }
                new bootstrap.Modal(document.getElementById('modalVehicle')).show();
            });
            
            $('#modalVehicle').on('hidden.bs.modal', function () {
                $(this).find('form').trigger('reset'); $('#modalTitle').text('Adicionar Veículo'); $('#vehicle_id').val(''); $('#currentVehiclePhoto').hide();
            });
            
            $('#vehicle_photo_input').on('change', function(event) {
                const [file] = event.target.files;
                if (file) $('#currentVehiclePhoto').attr('src', URL.createObjectURL(file)).show();
            });
            toastr.options = { "closeButton": true, "progressBar": true, "positionClass": "toast-top-right", "timeOut": "5000" };
        });
    </script>
  </body>
</html>