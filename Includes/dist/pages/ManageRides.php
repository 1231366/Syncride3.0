<?php
session_start();

// 1. VERIFICAÇÃO DE ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

require __DIR__ . '/../../../auth/dbconfig.php';

// 2. LÓGICA DE DADOS (Manage Rides Específico)
// Carregar condutores para os Modais
try {
    $stmt = $pdo->prepare("SELECT ID, name FROM Users WHERE role = 2 ORDER BY name ASC");
    $stmt->execute();
    $condutores = $stmt->fetchAll();
} catch (PDOException $e) {
    $condutores = []; 
}

// Foto de Perfil
$defaultPhoto = "../assets/img/user2-160x160.jpg";
$userPhoto = $defaultPhoto;
if (isset($_SESSION['profile_photo_path']) && !empty($_SESSION['profile_photo_path'])) {
    $userPhoto = "../../../" . $_SESSION['profile_photo_path'];
}
?>

<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Gerir Viagens | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        /* --- DESIGN SYSTEM MODERNO (SaaS) - BASEADO NO MODELO --- */
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
            --table-head-bg: #f9fafb; /* Adicionado para tabela */
            
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
            --table-head-bg: #1e293b; /* Adicionado para tabela */
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
        .card-header-custom { 
            padding: 1.25rem; border-bottom: 1px solid var(--border-color); 
            display: flex; flex-wrap: wrap; align-items: center; gap: 15px; justify-content: space-between; 
        }

        /* --- BUTTONS & INPUTS --- */
        .btn-modern { 
            background-color: var(--primary-accent); color: #fff; border: none; 
            border-radius: var(--radius-sm); padding: 0.5rem 1.2rem; font-weight: 500; transition: background 0.2s; 
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

        /* ========================================= */
        /* --- ESTILOS ESPECÍFICOS MANAGE RIDES ---  */
        /* ========================================= */
        
        /* Abas (Pills) */
        .nav-pills .nav-link { 
            color: var(--text-muted); font-weight: 500; border-radius: 50px; 
            padding: 8px 20px; border: 1px solid var(--border-color);
            background: var(--bg-body); transition: all 0.2s; white-space: nowrap;
        }
        .nav-pills .nav-link.active { 
            background-color: var(--primary-accent); color: #fff; 
            border-color: var(--primary-accent); box-shadow: var(--shadow-sm);
        }

        /* Tabela Desktop */
        .table { --bs-table-bg: transparent; --bs-table-color: var(--text-main); border-color: var(--border-color); width: 100%; }
        .table td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid var(--border-color); }
        .table thead th { 
            background-color: var(--table-head-bg) !important; color: var(--text-muted);
            border-bottom: 1px solid var(--border-color); font-weight: 600;
            padding: 1rem; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px;
        }

        /* DataTables Search Input */
        .dataTables_filter input { 
            background-color: var(--bg-body); border: 1px solid var(--border-color); 
            color: var(--text-main); border-radius: 50px; padding: 6px 15px; width: 100%;
        }
        .dataTables_length { display: none; } /* Esconder seletor de quantidade */

        /* Mobile Card View (Tabela em Cards em ecrãs pequenos) */
        @media (max-width: 991.98px) {
            .card-header-custom { flex-direction: column; align-items: stretch; }
            .nav-pills { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 5px; -webkit-overflow-scrolling: touch; }
            .nav-pills::-webkit-scrollbar { display: none; }
            
            .search-and-buttons { flex-direction: column; gap: 10px; width: 100%; }
            #filter-container { width: 100%; }
            
            #tabelaViagens thead { display: none; }
            #tabelaViagens tbody tr {
                display: flex; flex-direction: column; position: relative;
                background: var(--bg-card); border: 1px solid var(--border-color);
                border-radius: 16px; margin-bottom: 12px; padding: 16px;
                box-shadow: var(--shadow-sm);
            }
            #tabelaViagens tbody td { display: block; border: none !important; padding: 2px 0; width: 100% !important; }
            
            #tabelaViagens tbody td:nth-child(1) { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; margin-bottom: 4px; }
            #tabelaViagens tbody td:nth-child(2) { font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
            #tabelaViagens tbody td:nth-child(3) { margin-bottom: 8px; font-size: 0.9rem; }
            #tabelaViagens tbody td:nth-child(4), #tabelaViagens tbody td:nth-child(5) {
                font-size: 0.9rem; color: var(--text-main); display: flex; align-items: center; 
                margin-bottom: 4px; padding-left: 24px !important; position: relative;
            }
            #tabelaViagens tbody td:nth-child(4):before { content: "\F309"; font-family: "bootstrap-icons"; position: absolute; left: 0; color: #10b981; }
            #tabelaViagens tbody td:nth-child(5):before { content: "\F37F"; font-family: "bootstrap-icons"; position: absolute; left: 0; color: #ef4444; }
            #tabelaViagens tbody td:nth-child(6) {
                display: inline-block; width: auto !important; background: var(--bg-body); 
                padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; margin-top: 5px; border: 1px solid var(--border-color);
            }
            #tabelaViagens tbody td:last-child { position: absolute; top: 16px; right: 16px; width: auto !important; }
        }

        .req-pending { background-color: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); padding: 4px 10px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; }

        /* Logs Timeline */
        .logs-timeline { border-left: 2px solid var(--border-color); margin-left: 10px; padding-left: 25px; }
        .logs-item { position: relative; margin-bottom: 25px; }
        .logs-dot {
            width: 14px; height: 14px; background: var(--bg-card); border: 3px solid var(--border-color);
            border-radius: 50%; position: absolute; left: -33px; top: 5px; z-index: 1;
        }
        .logs-item.completed .logs-dot { border-color: #10b981; background: #10b981; }
        .logs-title { font-weight: 600; color: var(--text-main); margin-bottom: 2px; }
        .logs-date { font-size: 0.8rem; color: var(--text-muted); }

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
                  <a href="admin.php" class="nav-link">
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
                  <a href="ManageRides.php" class="nav-link active">
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
        <a href="admin.php" class="nav-item-bottom text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-grid-fill"></i><span>Home</span>
        </a>
        <a href="ManageRides.php" class="nav-item-bottom active text-decoration-none d-flex flex-column align-items-center">
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
                    <h3 class="fw-bold mb-0 text-main">Gestão de Viagens</h3>
                    <p class="text-muted mb-0 small">Controlo de frota, atribuições e estado dos serviços.</p>
                </div>
            </div>

            <div class="card border-0">
                <div class="card-header-custom">
                    <ul class="nav nav-pills" id="ride-tabs">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#today" data-status="today">Hoje</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pending" data-status="pending">Pendentes</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#assigned" data-status="assigned">Atribuídas</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#all" data-status="all">Todas</a></li>
                        <li class="nav-item ms-lg-3"><a class="nav-link text-warning border-warning" data-bs-toggle="tab" href="#requests" data-status="requests"><i class="bi bi-bell-fill"></i> Pedidos</a></li>
                    </ul>
                    
                    <div class="search-and-buttons d-flex align-items-center">
                        <div id="filter-container" class="flex-grow-1"></div>
                        
                        <div class="d-flex gap-2 ms-2">
                            <div class="dropdown">
                                <button class="btn btn-light border rounded-circle" type="button" data-bs-toggle="dropdown"><i class="bi bi-sort-down"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                    <li><a class="dropdown-item" href="#" onclick="sortRides(1, 'asc')">Mais antigas</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="sortRides(1, 'desc')">Mais recentes</a></li>
                                </ul>
                            </div>
                            <button class="btn btn-modern btn-nova-viagem" data-bs-toggle="modal" data-bs-target="#modalCriarViagem">
                                <i class="bi bi-plus-lg me-1"></i> Nova
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tabelaViagens" class="table table-hover align-middle mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Data & Hora</th>
                                    <th>Condutor / Estado</th>
                                    <th>Recolha</th>
                                    <th>Entrega</th>
                                    <th>Tipo</th>
                                    <th class="text-center pe-4">Ações</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
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

    <div class="modal fade" id="modalCriarViagem" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4" style="background-color: var(--bg-card); color: var(--text-main);">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold ps-2">Nova Viagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="addRide.php" method="POST">
                        <div class="row mb-3">
                            <div class="col-6"><label class="form-label small text-muted">Data</label><input type="date" class="form-control bg-light border-0" name="serviceDate" required /></div>
                            <div class="col-6"><label class="form-label small text-muted">Hora</label><input type="time" class="form-control bg-light border-0" name="serviceStartTime" required /></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6"><label class="form-label small text-muted">Adultos</label><input type="number" class="form-control bg-light border-0" name="paxADT" required /></div>
                            <div class="col-6"><label class="form-label small text-muted">Crianças</label><input type="number" class="form-control bg-light border-0" name="paxCHD" required /></div>
                        </div>
                        <div class="mb-3"><label class="form-label small text-muted">Partida</label><input type="text" class="form-control bg-light border-0" name="serviceStartPoint" required /></div>
                        <div class="mb-3"><label class="form-label small text-muted">Chegada</label><input type="text" class="form-control bg-light border-0" name="serviceTargetPoint" required /></div>
                        <div class="row mb-3">
                            <div class="col-6"><label class="form-label small text-muted">Condutor</label><select class="form-select bg-light border-0" name="driver"><option value="later">Depois</option><?php foreach ($condutores as $c) echo "<option value='{$c['ID']}'>".htmlspecialchars($c['name'])."</option>"; ?></select></div>
                            <div class="col-6"><label class="form-label small text-muted">Tipo</label><select class="form-select bg-light border-0" name="serviceType"><option value="1">Privado</option><option value="0">Partilhado</option></select></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6"><label class="form-label small text-muted">Voo</label><input type="text" class="form-control bg-light border-0" name="FlightNumber" /></div>
                            <div class="col-6"><label class="form-label small text-muted">Cliente</label><input type="text" class="form-control bg-light border-0" name="NomeCliente" /></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Número do Cliente</label>
                            <input type="text" class="form-control bg-light border-0" name="ClientNumber" />
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-success fw-bold small"><i class="bi bi-cash-coin"></i> Valor a Cobrar (€)</label>
                            <input type="number" step="0.01" class="form-control border-success bg-light" name="totalPrice" placeholder="Ex: 45.50 (Deixar vazio se já pago)">
                        </div>
                        
                        <button type="submit" class="btn btn-modern w-100 rounded-pill fw-bold shadow-sm">Criar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changeTripTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4" style="background-color: var(--bg-card); color: var(--text-main);">
                <div class="modal-header border-bottom-0"><h5 class="modal-title fw-bold">Alterar Tipo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form action="update_trip_type.php" method="POST">
                    <div class="modal-body text-center">
                        <input type="hidden" id="tripId_changeType" name="tripId">
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="tripType" id="private" value="1" autocomplete="off">
                            <label class="btn btn-outline-primary" for="private">Privado</label>
                            <input type="radio" class="btn-check" name="tripType" id="shared" value="0" autocomplete="off">
                            <label class="btn btn-outline-warning" for="shared">Partilhado</label>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0"><button type="submit" class="btn btn-modern w-100 rounded-pill">Guardar</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="atribuirCondutorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4" style="background-color: var(--bg-card); color: var(--text-main);">
                <div class="modal-header border-bottom-0"><h5 class="modal-title fw-bold">Atribuir Condutor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form action="atribuircondutor.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="viagemId_assign" name="viagemId">
                        <select name="condutorId" class="form-select bg-light border-0 py-2 text-center fw-bold">
                            <?php foreach ($condutores as $c): ?><option value="<?= $c['ID'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer border-top-0"><button type="submit" class="btn btn-modern w-100 rounded-pill">Confirmar</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteTripModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4" style="background-color: var(--bg-card); color: var(--text-main);">
                <div class="modal-body text-center p-5">
                    <div class="text-danger mb-3"><i class="bi bi-trash3-fill" style="font-size: 3rem;"></i></div>
                    <h5 class="fw-bold mb-2">Apagar Viagem?</h5>
                    <p class="text-muted mb-4">Vai eliminar <strong id="deleteTripName"></strong>.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Não</button>
                        <a href="#" id="confirmDeleteTripBtn" class="btn btn-danger px-4 rounded-pill fw-bold shadow-sm">Sim</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4" style="background-color: var(--bg-card); color: var(--text-main);">
                <div class="modal-header border-bottom-0"><h5 class="modal-title fw-bold">Editar Detalhes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                <form id="editTripForm" action="updateRide.php" method="POST">
                    <input type="hidden" name="edit_trip_id" id="editTripId">
                    <div class="row mb-3"><div class="col-6"><label class="small text-muted">Data/Hora</label><input type="datetime-local" class="form-control bg-light border-0" id="editDataHora" name="edit_departure_datetime" disabled></div><div class="col-6"><label class="small text-muted">Condutor</label><input type="text" class="form-control bg-light border-0" id="editCondutor" name="edit_driverName" disabled></div></div>
                    <div class="mb-3"><label class="small text-muted">Recolha</label><input type="text" class="form-control bg-light border-0" id="editRecolha" name="edit_origin" disabled></div>
                    <div class="mb-3"><label class="small text-muted">Entrega</label><input type="text" class="form-control bg-light border-0" id="editEntrega" name="edit_destination" disabled></div>
                    <div class="row mb-3"><div class="col-4"><label class="small text-muted">ADT</label><input type="number" class="form-control bg-light border-0" id="editpaxADT" name="edit_paxADT" disabled></div><div class="col-4"><label class="small text-muted">CHD</label><input type="number" class="form-control bg-light border-0" id="editpaxCHD" name="edit_paxCHD" disabled></div><div class="col-4"><label class="small text-muted">Voo</label><input type="text" class="form-control bg-light border-0" id="editflightNumber" name="edit_flightNumber" disabled></div></div>
                    <div class="row mb-3">
                        <div class="col-md-6"><label class="small text-muted">Cliente</label><input type="text" class="form-control bg-light border-0" id="editclientName" name="edit_clientName" disabled></div>
                        <div class="col-md-6"><label class="small text-muted">Nº Cliente</label><input type="text" class="form-control bg-light border-0" id="editclientNumber" name="edit_clientNumber" disabled></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-success fw-bold small">Valor a Cobrar (€)</label>
                        <input type="number" step="0.01" class="form-control border-success bg-light" id="editTotalPrice" name="edit_totalPrice" disabled>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                        <div><span class="text-muted small me-2">Tipo:</span><input type="text" class="d-inline-block form-control-sm border-0 bg-transparent fw-bold" id="editTripTypeDisplay" disabled style="width: 100px;"></div>
                        <button type="button" id="btnChangeTypeEdit" class="btn btn-outline-primary btn-sm rounded-pill"><i class="bi bi-shuffle"></i> Alterar</button>
                    </div>
                </form>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-warning w-100 rounded-pill" id="enableEditBtn" onclick="enableEdit()">Editar Dados</button>
                    <button type="submit" class="btn btn-modern w-100 rounded-pill" form="editTripForm" id="saveChangesBtn" style="display: none;">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalLogs" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4" style="background-color: var(--bg-card); color: var(--text-main);">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold ps-2">Histórico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-4">
                    <div id="logsContent">
                        <div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // --- Dark Mode & Logo Logic (Do Modelo) ---
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
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5';
        }

        function updateLogo(theme) {
            const newSrc = theme === 'dark' ? logoLight : logoDark;
            if(headerLogo) headerLogo.src = newSrc;
            if(sidebarLogo) sidebarLogo.src = newSrc;
        }

        // --- DataTables & Lógica Específica Manage Rides ---
        let tabelaViagens;

        $(document).ready(function () {
            tabelaViagens = $('#tabelaViagens').DataTable({
                "processing": true, 
                "serverSide": false, 
                "ajax": { "url": "load_rides_data.php?status=today", "type": "GET", "dataSrc": "data" },
                "columns": [
                    { "data": "id", "className": "ps-4" }, 
                    { "data": "data_hora", "className": "fw-bold" }, 
                    { 
                        "data": "condutor",
                        "render": function(data, type, row) {
                            if (row.status_pedido === 'pendente') {
                                const partner = row.partner_name || 'Agência';
                                return `<span class="req-pending"><i class="bi bi-shop me-1"></i> ${partner}</span>`;
                            }
                            return data;
                        }
                    }, 
                    { "data": "recolha" }, 
                    { "data": "entrega" }, 
                    { "data": "tipo" }, 
                    { 
                        "data": "acoes", 
                        "orderable": false,
                        "render": function (data, type, row) {
                            if (row.status_pedido === 'pendente') {
                                const tripId = row.raw_id ? row.raw_id : row.id.replace('#', '');
                                return `
                                <div class="d-flex gap-2 justify-content-end">
                                    <button class="btn btn-success btn-sm rounded-circle shadow-sm" onclick="handleRequest(${tripId}, 'approve')" title="Aprovar"><i class="bi bi-check-lg"></i></button>
                                    <button class="btn btn-danger btn-sm rounded-circle shadow-sm" onclick="handleRequest(${tripId}, 'reject')" title="Rejeitar"><i class="bi bi-x-lg"></i></button>
                                </div>`;
                            }
                            return `<div class="d-flex gap-1 justify-content-end align-items-center">${data}</div>`;
                        }
                    }
                ],
                "language": { "search": "", "searchPlaceholder": "Pesquisar...", "lengthMenu": "", "info": "", "paginate": { "next": "→", "previous": "←" }, "zeroRecords": "Sem dados" },
                "order": [[1, 'asc']], "pageLength": 10,
                "dom": 'rt<"d-flex justify-content-center mt-3"p>'
            });
            
            // Move search to custom header
            $('#tabelaViagens_filter').appendTo('#filter-container');

            $('#ride-tabs a').on('shown.bs.tab', function (e) {
                const status = $(e.target).data('status');
                tabelaViagens.search('').draw(); 
                tabelaViagens.ajax.url(`load_rides_data.php?status=${status}`).load();
            });
        });

        function sortRides(col, dir) { tabelaViagens.order([col, dir]).draw(); }
        function setViagemId(id) { document.getElementById('viagemId_assign').value = id; }
        
        function changeTripType(tripId, currentType) {
            document.getElementById('tripId_changeType').value = tripId;
            if (currentType == 1) document.getElementById('private').checked = true; else document.getElementById('shared').checked = true;
            new bootstrap.Modal(document.getElementById('changeTripTypeModal')).show();
        }
        
        function handleRequest(id, action) {
            if(!confirm(action === 'approve' ? 'Aprovar este pedido?' : 'Rejeitar este pedido?')) return;
            $.post('api_handle_request.php', { id: id, action: action }, function(res) {
                if(res.success) { toastr.success('Atualizado!'); tabelaViagens.ajax.reload(); } 
                else { toastr.error('Erro: ' + (res.message || 'Falha')); }
            });
        }
        
        function editTravel(id, dataHora, condutor, recolha, entrega, paxADT, paxCHD, flightNumber, clientName, clientNumber, serviceType, totalPrice) {
            disableEdit();
            document.getElementById('editTripId').value = id;
            document.getElementById('editDataHora').value = dataHora.replace(" ", "T");
            document.getElementById('editCondutor').value = condutor;
            document.getElementById('editRecolha').value = recolha;
            document.getElementById('editEntrega').value = entrega;
            document.getElementById('editpaxADT').value = paxADT;
            document.getElementById('editpaxCHD').value = paxCHD;
            document.getElementById('editflightNumber').value = flightNumber;
            document.getElementById('editclientName').value = clientName;
            document.getElementById('editclientNumber').value = clientNumber;
            document.getElementById('editTotalPrice').value = totalPrice;
            document.getElementById('editTripTypeDisplay').value = serviceType == 1 ? "Privado" : "Partilhado";

            const btnChange = document.getElementById('btnChangeTypeEdit');
            btnChange.onclick = function() {
                bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                changeTripType(id, serviceType);
            };
        }

        function enableEdit() {
            document.querySelectorAll('#editTripForm input').forEach(input => { 
                if(input.id !== 'editCondutor' && input.id !== 'editTripTypeDisplay') input.disabled = false;
            });
            document.getElementById('saveChangesBtn').style.display = 'inline-block';
            const btn = document.getElementById('enableEditBtn');
            btn.textContent = 'Cancelar';
            btn.setAttribute('onclick', 'disableEdit()');
            btn.classList.remove('btn-warning'); btn.classList.add('btn-secondary');
        }

        function disableEdit() {
            document.querySelectorAll('#editTripForm input').forEach(input => input.disabled = true);
            document.getElementById('saveChangesBtn').style.display = 'none';
            const btn = document.getElementById('enableEditBtn');
            btn.textContent = 'Editar Dados';
            btn.setAttribute('onclick', 'enableEdit()');
            btn.classList.add('btn-warning'); btn.classList.remove('btn-secondary');
        }

        function setDeleteTrip(id, name) {
            document.getElementById("deleteTripName").textContent = name;
            document.getElementById("confirmDeleteTripBtn").href = `apagar_viagem.php?id=${id}`;
        }

        function viewTripLogs(id) {
            const modal = new bootstrap.Modal(document.getElementById('modalLogs'));
            const content = document.getElementById('logsContent');
            content.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></div>';
            modal.show();

            fetch(`get_ride_logs.php?id=${id}`).then(r => r.json()).then(res => {
                if (res.success) {
                    const d = res.data;
                    const formatTime = (t) => t ? new Date(t).toLocaleString('pt-PT', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' }) : '<span class="text-muted fst-italic small">Pendente</span>';
                    const steps = [
                        { label: 'Iniciou Recolha', time: d.ts_start_pickup },
                        { label: 'Chegou ao Ponto', time: d.ts_arrived_pickup },
                        { label: 'Iniciou Viagem', time: d.ts_start_trip },
                        { label: 'Terminou Viagem', time: d.ts_completed }
                    ];
                    let html = '<div class="logs-timeline">';
                    steps.forEach(step => {
                        const isDone = step.time !== null;
                        const statusClass = isDone ? 'completed' : 'pending';
                        html += `<div class="logs-item ${statusClass}"><div class="logs-dot"></div><div class="logs-content"><div class="logs-title ${isDone ? 'text-main' : 'text-muted'}">${step.label}</div><div class="logs-date">${formatTime(step.time)}</div></div></div>`;
                    });
                    content.innerHTML = html + '</div>';
                } else content.innerHTML = '<p class="text-center text-danger">Erro ao carregar logs.</p>';
            });
        }

        toastr.options = { "closeButton": true, "progressBar": true, "positionClass": "toast-top-right", "timeOut": "3000" };
        const success = "<?php echo isset($_GET['success']) ? $_GET['success'] : ''; ?>";
        if (success) {
            let msg = "";
            if(success === "ride_created") msg = "Viagem criada!";
            if(success === "rideUpdated") msg = "Viagem atualizada!";
            if(msg) toastr.success(msg);
            const url = new URL(window.location); url.searchParams.delete('success'); window.history.replaceState({}, '', url);
        }
    </script>
  </body>
</html>