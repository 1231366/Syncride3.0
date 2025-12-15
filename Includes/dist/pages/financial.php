<?php
session_start();

// 1. VERIFICAÇÃO DE ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

require __DIR__ . '/../../../auth/dbconfig.php';

// 2. FILTROS E LÓGICA DE DADOS
// Filtro de Mês (Default: Mês Atual)
$mesFiltro = $_GET['month'] ?? date('Y-m');
$ano = date('Y', strtotime($mesFiltro));
$mes = date('m', strtotime($mesFiltro));

// Foto de Perfil
$defaultPhoto = "../assets/img/user2-160x160.jpg"; 
$userPhoto = $defaultPhoto;
if (isset($_SESSION['profile_photo_path']) && !empty($_SESSION['profile_photo_path'])) {
    $userPhoto = "../../../" . $_SESSION['profile_photo_path'];
}

// 3. CÁLCULOS FINANCEIROS
// Receita Estimada (Exemplo: Total Viagens * 15€ - Ajustar conforme tua lógica real)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE YEAR(serviceDate) = ? AND MONTH(serviceDate) = ?");
$stmt->execute([$ano, $mes]);
$totalViagens = $stmt->fetchColumn();
$receitaEstimada = $totalViagens * 15; 

// Total Despesas
$stmt = $pdo->prepare("SELECT SUM(amount) FROM Expenses WHERE YEAR(date) = ? AND MONTH(date) = ?");
$stmt->execute([$ano, $mes]);
$totalDespesas = $stmt->fetchColumn() ?: 0;

$lucroLiquido = $receitaEstimada - $totalDespesas;

// 4. DADOS DO GRÁFICO
$stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM Expenses WHERE YEAR(date) = ? AND MONTH(date) = ? GROUP BY category");
$stmt->execute([$ano, $mes]);
$chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$catLabels = [];
$catValues = [];
foreach($chartData as $d) {
    $catLabels[] = $d['category'];
    $catValues[] = (float)$d['total'];
}

// 5. LISTA DE DESPESAS
$stmt = $pdo->prepare("SELECT * FROM Expenses WHERE YEAR(date) = ? AND MONTH(date) = ? ORDER BY date DESC");
$stmt->execute([$ano, $mes]);
$listaDespesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Financeiro | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
            font-family: var(--font-display); font-size: 1.8rem; font-weight: 700;
            line-height: 1; margin-bottom: 0.5rem; color: var(--text-main);
        }
        .stat-label { color: var(--text-muted); font-size: 0.875rem; font-weight: 500; }

        /* Cores Stats */
        .stat-green .stat-icon-wrapper { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .stat-red .stat-icon-wrapper { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .stat-blue .stat-icon-wrapper { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

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
        
        /* --- ESTILOS ESPECÍFICOS FINANCEIRO --- */
        .btn-modern { 
            background-color: var(--primary-accent); color: #fff; border: none; 
            border-radius: var(--radius-sm); padding: 0.5rem 1.2rem; font-weight: 500; transition: background 0.2s; 
        }
        .btn-modern:hover { background-color: var(--primary-hover); color: #fff; }

        /* Tabela Desktop */
        .table { --bs-table-bg: transparent; --bs-table-color: var(--text-main); border-color: var(--border-color); width: 100%; }
        .table td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid var(--border-color); }
        .table thead th { 
            background-color: var(--table-head-bg) !important; color: var(--text-muted);
            border-bottom: 1px solid var(--border-color); font-weight: 600;
            padding: 1rem; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px;
        }

        /* Mobile Expense Card (Tabela em Cards) */
        @media (max-width: 991.98px) {
            #tabelaDespesas thead { display: none; }
            #tabelaDespesas tbody tr {
                display: flex; flex-direction: column; position: relative;
                background: var(--bg-card); border: 1px solid var(--border-color);
                border-radius: 16px; margin-bottom: 12px; padding: 16px;
                box-shadow: var(--shadow-sm);
            }
            #tabelaDespesas tbody td { display: block; border: none !important; padding: 2px 0; width: 100% !important; }
            
            /* Ajustes visuais das células em mobile */
            /* Data */
            #tabelaDespesas tbody td:nth-child(1) { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 4px; }
            /* Categoria (Badge) */
            #tabelaDespesas tbody td:nth-child(2) { display: inline-block; width: auto !important; margin-bottom: 8px; }
            /* Descrição */
            #tabelaDespesas tbody td:nth-child(3) { font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 4px; }
            /* Valor */
            #tabelaDespesas tbody td:nth-child(4) { position: absolute; top: 16px; right: 16px; width: auto !important; font-size: 1.1rem; font-weight: 800; color: #ef4444; }
            /* Ações */
            #tabelaDespesas tbody td:last-child { margin-top: 10px; padding-top: 10px !important; border-top: 1px solid var(--border-color) !important; display: flex; gap: 10px; }
            #tabelaDespesas tbody td:last-child .btn, #tabelaDespesas tbody td:last-child a { flex: 1; display: flex; justify-content: center; align-items: center; padding: 8px; border-radius: 8px; border: 1px solid var(--border-color); color: var(--text-muted); text-decoration: none; }
        }

        .dataTables_filter { display: none; }
        .badge-cat { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-muted); }
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
              <li class="nav-item">
                  <a href="financial.php" class="nav-link active">
                      <i class="nav-icon bi bi-cash-coin"></i>
                      <p>Financeiro</p>
                  </a>
              </li>
              <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link"><i class="nav-icon bi bi-bar-chart-fill"></i><p>Estatísticas</p></a></li>
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
        <a href="financial.php" class="nav-item-bottom active text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-cash-coin"></i><span>Finanças</span>
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
                <div class="col-4 text-center"><a href="admin_driver_stats.php" class="quick-action-btn shadow-sm"><i class="bi bi-bar-chart-fill text-warning"></i><span>Stats</span></a></div>
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
                    <h3 class="fw-bold mb-0 text-main">Financeiro</h3>
                    <p class="text-muted mb-0 small">Controlo de receitas e despesas.</p>
                </div>
                
                <div class="d-flex gap-2 w-100 w-md-auto align-items-center">
                    <form method="GET" class="flex-grow-1 flex-md-grow-0">
                        <input type="month" name="month" class="form-control bg-card border border-color text-main shadow-sm" value="<?php echo $mesFiltro; ?>" onchange="this.form.submit()" style="min-width: 150px;">
                    </form>
                    <button class="btn btn-modern shadow-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#modalExpense">
                        <i class="bi bi-plus-lg me-1"></i> <span class="d-none d-sm-inline">Despesa</span>
                    </button>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4 col-12">
                    <div class="stat-card stat-green">
                        <div>
                            <div class="stat-icon-wrapper"><i class="bi bi-graph-up-arrow"></i></div>
                            <div class="stat-value"><?php echo number_format($receitaEstimada, 0, ',', '.'); ?>€</div>
                            <div class="stat-label">Receita Estimada</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="stat-card stat-red">
                        <div>
                            <div class="stat-icon-wrapper"><i class="bi bi-cart-dash"></i></div>
                            <div class="stat-value"><?php echo number_format($totalDespesas, 0, ',', '.'); ?>€</div>
                            <div class="stat-label">Despesas</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="stat-card stat-blue">
                        <div>
                            <div class="stat-icon-wrapper"><i class="bi bi-wallet2"></i></div>
                            <div class="stat-value"><?php echo number_format($lucroLiquido, 0, ',', '.'); ?>€</div>
                            <div class="stat-label">Lucro Líquido</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8 connectedSortable">
                    <div class="card h-100 border-0">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title fw-bold">Despesas</h3>
                            <span class="badge bg-light text-muted border"><?php echo count($listaDespesas); ?> registos</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="tabelaDespesas" class="table table-hover w-100 m-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Data</th>
                                            <th>Categoria</th>
                                            <th>Descrição</th>
                                            <th class="text-end">Valor</th>
                                            <th class="text-center pe-4">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($listaDespesas)): ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted">Sem despesas este mês.</td></tr>
                                        <?php else: ?>
                                            <?php foreach($listaDespesas as $d): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold"><?php echo date('d/m/Y', strtotime($d['date'])); ?></td>
                                                <td><span class="badge-cat"><?php echo htmlspecialchars($d['category']); ?></span></td>
                                                <td class="text-muted small"><?php echo htmlspecialchars($d['description']); ?></td>
                                                <td class="text-end fw-bold text-danger">-<?php echo number_format($d['amount'], 2, ',', '.'); ?>€</td>
                                                <td class="text-center pe-4">
                                                    
                                                    <div class="d-flex gap-1 justify-content-end">
                                                        <?php if($d['file_path']): $ext=strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION)); ?>
                                                            <button class="btn btn-sm btn-light border" onclick="openPreview('<?php echo htmlspecialchars($d['file_path']); ?>','<?php echo $ext; ?>')"><i class="bi bi-eye"></i> <span class="d-md-none">Ver</span></button>
                                                        <?php endif; ?>
                                                        <button class="edit-btn btn btn-sm btn-light border" data-id="<?php echo $d['id']; ?>" data-cat="<?php echo $d['category']; ?>" data-desc="<?php echo $d['description']; ?>" data-amount="<?php echo $d['amount']; ?>" data-date="<?php echo $d['date']; ?>"><i class="bi bi-pencil"></i> <span class="d-md-none">Editar</span></button>
                                                        <a href="save_expense.php?action=delete&id=<?php echo $d['id']; ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Apagar?');"><i class="bi bi-trash"></i> <span class="d-md-none">Apagar</span></a>
                                                    </div>

                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 connectedSortable">
                    <div class="card h-100 border-0">
                        <div class="card-header border-bottom">
                            <h3 class="card-title fw-bold">Distribuição</h3>
                        </div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <div id="expenses-chart" class="w-100"></div>
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

    <div class="modal fade" id="modalExpense" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg" style="background-color: var(--bg-card); color: var(--text-main);">
          <form action="save_expense.php" method="POST" enctype="multipart/form-data">
              <div class="modal-header border-bottom-0 pb-0"><h5 class="modal-title fw-bold" id="modalTitle">Nova Despesa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
              <div class="modal-body p-4">
                <input type="hidden" name="expense_id" id="expense_id">
                <div class="row g-3">
                    <div class="col-6"><label class="small fw-bold text-muted">Data</label><input type="date" name="date" id="date" class="form-control bg-light border-0" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="col-6"><label class="small fw-bold text-muted">Valor (€)</label><input type="number" step="0.01" name="amount" id="amount" class="form-control bg-light border-0 fw-bold" placeholder="0.00" required></div>
                    <div class="col-12"><label class="small fw-bold text-muted">Categoria</label><select name="category" id="category" class="form-select bg-light border-0" required><option value="Combustível">⛽ Combustível</option><option value="Manutenção">🔧 Manutenção</option><option value="Pessoal">👔 Pessoal</option><option value="Portagens">🛣️ Portagens</option><option value="Outros">📝 Outros</option></select></div>
                    <div class="col-12"><label class="small fw-bold text-muted">Descrição</label><input type="text" name="description" id="description" class="form-control bg-light border-0" placeholder="Ex: Gasóleo Carrinha 1" required></div>
                    <div class="col-12"><label class="small fw-bold text-muted">Comprovativo</label><input type="file" name="proof" class="form-control bg-light border-0" accept="image/*,application/pdf"></div>
                </div>
              </div>
              <div class="modal-footer border-top-0 pt-0 px-4 pb-4">
                <button type="submit" class="btn btn-modern w-100 rounded-pill shadow-sm">Guardar</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="previewModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
          <div class="modal-body p-0 position-relative bg-dark rounded-4 overflow-hidden text-center">
            <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3" data-bs-dismiss="modal"></button>
            <img src="" id="previewImage" class="d-none img-fluid" style="max-height: 80vh;">
            <iframe src="" id="previewFrame" class="d-none w-100" style="height:60vh; border:none;"></iframe>
          </div>
          <div class="modal-footer border-0 justify-content-center bg-card">
            <a href="#" id="downloadBtn" class="btn btn-light rounded-pill px-4" download target="_blank"><i class="bi bi-download me-2"></i> Baixar</a>
          </div>
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
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
      // --- Dark Mode & Logo Logic ---
      const themeToggle = document.getElementById('theme-toggle');
      const themeIcon = document.getElementById('theme-icon');
      const htmlElement = document.documentElement;
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
          updateChartTheme(newTheme);
      });

      function updateThemeIcon(theme) {
          themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5';
      }

      function updateLogo(theme) {
          const newSrc = theme === 'dark' ? logoLight : logoDark;
          if(headerLogo) headerLogo.src = newSrc;
          if(sidebarLogo) sidebarLogo.src = newSrc;
      }

      let expenseChart;

      $(document).ready(function() {
            // DataTables (apenas inicializa em desktop para permitir a view de cartões em mobile funcionar)
            if ($(window).width() >= 992) {
                $('#tabelaDespesas').DataTable({
                    "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json", search: "", searchPlaceholder: "Procurar..." },
                    "pageLength": 10, "lengthChange": false, "searching": false, "ordering": false, "dom": 'tp'
                });
            }

            // Chart
            const vals = <?php echo json_encode($catValues); ?>;
            const labs = <?php echo json_encode($catLabels); ?>;
            
            if(vals.length > 0) {
                const options = {
                    series: vals, labels: labs, chart: { type: 'donut', height: 280, fontFamily: 'Inter, sans-serif', background: 'transparent' },
                    colors: ['#ef4444', '#3b82f6', '#f59e0b', '#10b981', '#6b7280'],
                    legend: { position: 'bottom', labels: { colors: savedTheme === 'dark' ? '#cbd5e1' : '#374151' } }, stroke: { show: false },
                    plotOptions: { pie: { donut: { size: '70%' } } },
                    theme: { mode: savedTheme }
                };
                expenseChart = new ApexCharts(document.querySelector("#expenses-chart"), options);
                expenseChart.render();
            } else {
                document.querySelector("#expenses-chart").innerHTML = "<div class='text-center small py-5 text-muted'>Sem dados para este mês.</div>";
            }

            // Edit Modal
            $(document).on('click', '.edit-btn', function() {
                $('#modalTitle').text('Editar Despesa');
                $('#expense_id').val($(this).data('id'));
                $('#date').val($(this).data('date'));
                $('#category').val($(this).data('cat'));
                $('#description').val($(this).data('desc'));
                $('#amount').val($(this).data('amount'));
                new bootstrap.Modal(document.getElementById('modalExpense')).show();
            });
            $('#modalExpense').on('hidden.bs.modal', function(){ 
                $(this).find('form').trigger('reset'); $('#modalTitle').text('Nova Despesa'); $('#expense_id').val(''); 
            });
            
            // Toastr
            toastr.options = { "closeButton": true, "progressBar": true, "positionClass": "toast-top-right", "timeOut": "5000" };
            const success = "<?php echo isset($_GET['success']) ? $_GET['success'] : ''; ?>";
            if(success === "deleted") toastr.success("Registo apagado.");
            if(success === "saved") toastr.success("Guardado com sucesso.");
            if(success) {
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?month=<?php echo $mesFiltro; ?>";
                window.history.replaceState({path: newUrl}, '', newUrl);
            }
      });

      function updateChartTheme(theme) {
          if(expenseChart) {
              expenseChart.updateOptions({
                  theme: { mode: theme },
                  legend: { labels: { colors: theme === 'dark' ? '#cbd5e1' : '#374151' } }
              });
          }
      }

      function openPreview(path, ext) {
          const m = new bootstrap.Modal(document.getElementById('previewModal'));
          $('#previewImage').addClass('d-none'); $('#previewFrame').addClass('d-none');
          $('#downloadBtn').attr('href', path);
          if(ext==='pdf'){ $('#previewFrame').attr('src', path).removeClass('d-none'); }
          else { $('#previewImage').attr('src', path).removeClass('d-none'); }
          m.show();
      }
    </script>
  </body>
</html>