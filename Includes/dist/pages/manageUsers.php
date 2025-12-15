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

// 3. LÓGICA DE DADOS DA PÁGINA
try {
    $stmt = $pdo->query("SELECT id, name, email, phone, role FROM Users ORDER BY name ASC");
    $users = $stmt->fetchAll();
    if (!$users) $users = [];

    $totalAdmins = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 1")->fetchColumn();
    $totalDrivers = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 2")->fetchColumn();
    $totalPartners = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 3")->fetchColumn(); 
} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Equipa | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
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
        .stat-card.clickable { cursor: pointer; }
        
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

        /* --- BOTTOM NAV & BUTTONS --- */
        .bottom-navbar {
            background-color: var(--bg-card); border-top: 1px solid var(--border-color);
            z-index: 1050; padding-bottom: env(safe-area-inset-bottom);
        }
        .nav-item-bottom { color: var(--text-muted); font-size: 0.75rem; transition: color 0.2s; }
        .nav-item-bottom.active { color: var(--primary-accent); }
        .nav-item-bottom i { font-size: 1.5rem; margin-bottom: 2px; }

        .btn-modern {
            background-color: var(--primary-accent); color: #fff; border: none;
            border-radius: var(--radius-sm); padding: 0.75rem 1.5rem;
            font-weight: 500; transition: background 0.2s;
        }
        .btn-modern:hover { background-color: var(--primary-hover); color: #fff; }

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
        
        /* Table Styles */
        .table { --bs-table-bg: transparent; --bs-table-color: var(--text-main); border-color: var(--border-color); }
        .table td, .table th { padding: 1rem; vertical-align: middle; }
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
                  <a href="ManageRides.php" class="nav-link">
                      <i class="nav-icon bi bi-car-front-fill"></i>
                      <p>Viagens</p>
                  </a>
              </li>
              
              <li class="nav-header text-muted fw-bold small text-uppercase px-3 mb-2 mt-4">Gestão</li>
              
              <li class="nav-item">
                  <a href="manageUsers.php" class="nav-link active"> 
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
        <a href="ManageRides.php" class="nav-item-bottom text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-car-front-fill"></i><span>Viagens</span>
        </a>
        <a href="manageUsers.php" class="nav-item-bottom active text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-people-fill"></i><span>Equipa</span>
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
                    <h3 class="fw-bold mb-0 text-main">Gestão de Equipa</h3>
                    <p class="text-muted mb-0 small">Administradores, Condutores e Parceiros.</p>
                </div>
            </div>

            <div class="row g-4 mb-4">
              <div class="col-lg-3 col-6">
                <div class="stat-card stat-blue">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi bi-person-badge-fill"></i></div>
                      <div class="stat-value"><?= $totalAdmins ?></div>
                      <div class="stat-label">Administradores</div>
                  </div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="stat-card stat-green">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi bi-car-front-fill"></i></div>
                      <div class="stat-value"><?= $totalDrivers ?></div>
                      <div class="stat-label">Condutores</div>
                  </div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="stat-card stat-purple">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi bi-shop"></i></div>
                      <div class="stat-value"><?= $totalPartners ?></div>
                      <div class="stat-label">Parceiros</div>
                  </div>
                </div>
              </div>
              
              <div class="col-lg-3 col-6">
                <div class="stat-card stat-orange clickable" data-bs-toggle="modal" data-bs-target="#modalCriarUtilizador">
                  <div>
                      <div class="stat-icon-wrapper"><i class="bi bi-person-plus-fill"></i></div>
                      <div class="stat-value" style="font-size: 1.5rem;">Novo</div>
                      <div class="stat-label">Criar Conta</div>
                  </div>
                  <div class="text-end text-muted"><i class="bi bi-arrow-right"></i></div>
                </div>
              </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card border-0">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title fw-bold">Colaboradores</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead style="background-color: var(--table-head-bg);">
                                        <tr>
                                            <th class="ps-4">Nome</th>
                                            <th class="d-none d-lg-table-cell">Email</th>
                                            <th class="d-none d-lg-table-cell">Telefone</th>
                                            <th class="d-none d-lg-table-cell">Cargo</th> 
                                            <th class="text-end pe-4">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $index => $user): ?>
                                            <?php 
                                                if ($user['role'] == 1) $roleBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Admin</span>';
                                                elseif ($user['role'] == 2) $roleBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Condutor</span>';
                                                elseif ($user['role'] == 3) $roleBadge = '<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">Parceiro</span>';
                                                else $roleBadge = '<span class="badge bg-secondary-subtle text-secondary rounded-pill">Outro</span>';
                                            ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-initial rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold text-main"><?= htmlspecialchars($user['name']) ?></div>
                                                            <div class="d-lg-none mt-1"><?= $roleBadge ?></div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="d-none d-lg-table-cell text-muted small"><?= htmlspecialchars($user['email']) ?></td>
                                                <td class="d-none d-lg-table-cell text-muted small"><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                                <td class="d-none d-lg-table-cell"><?= $roleBadge ?></td>
                                                
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-sm btn-light text-muted border me-1" data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                       data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>"
                                                       data-email="<?= htmlspecialchars($user['email']) ?>" data-phone="<?= htmlspecialchars($user['phone']) ?>"
                                                       data-role="<?= $user['role'] ?>">
                                                       <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-light text-danger border" data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                                       data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>">
                                                       <i class="bi bi-trash"></i>
                                                    </button>
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

          </div>
        </div>
      </main>

      <footer class="app-footer border-top-0 bg-transparent text-center py-4">
        <strong class="text-main">SyncRide</strong> <span class="text-muted small">© 2025</span>
      </footer>
    </div>

    <div class="modal fade" id="modalCriarUtilizador" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4" style="background-color: var(--bg-card); color: var(--text-main);">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-main ps-2">Criar Utilizador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="createUser.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Nome</label>
                            <input type="text" class="form-control bg-light border-0 py-2" name="name" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Email</label>
                            <input type="email" class="form-control bg-light border-0 py-2" name="email" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light border-0 py-2" id="inputPassword" name="password" required readonly />
                                <button type="button" class="btn btn-light border-0" id="generatePasswordBtn"><i class="bi bi-magic text-primary"></i></button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Telefone</label>
                                <input type="text" class="form-control bg-light border-0 py-2" name="phone" required />
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Cargo</label>
                                <select class="form-select bg-light border-0 py-2" name="role" required>
                                    <option value="2">Condutor</option>
                                    <option value="3">Parceiro (Agência)</option> 
                                    <option value="1">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-modern shadow-sm">Criar Conta</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4" style="background-color: var(--bg-card); color: var(--text-main);">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-main ps-2">Editar Utilizador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="editar_utilizador.php" method="POST">
                        <input type="hidden" id="editUserId" name="id">
                        <div class="mb-3"><label class="form-label small fw-bold text-muted">Nome</label><input type="text" class="form-control bg-light border-0 py-2" id="editUserName" name="name" required></div>
                        <div class="mb-3"><label class="form-label small fw-bold text-muted">Email</label><input type="email" class="form-control bg-light border-0 py-2" id="editUserEmail" name="email" required></div>
                        <div class="row">
                             <div class="col-6 mb-3"><label class="form-label small fw-bold text-muted">Telefone</label><input type="text" class="form-control bg-light border-0 py-2" id="editUserPhone" name="phone" required></div>
                             <div class="col-6 mb-3"><label class="form-label small fw-bold text-muted">Cargo</label>
                                <select class="form-select bg-light border-0 py-2" id="editUserRole" name="role" required>
                                    <option value="1">Admin</option>
                                    <option value="2">Condutor</option>
                                    <option value="3">Parceiro (Agência)</option> 
                                </select>
                            </div>
                        </div>
                        <div class="mb-4"><label class="form-label small fw-bold text-muted">Nova Password (Opcional)</label><input type="password" class="form-control bg-light border-0 py-2" name="password" placeholder="Manter atual"></div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-modern shadow-sm">Guardar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4" style="background-color: var(--bg-card); color: var(--text-main);">
                <div class="modal-body text-center p-5">
                    <div class="text-danger mb-3"><i class="bi bi-exclamation-circle-fill" style="font-size: 3rem;"></i></div>
                    <h5 class="fw-bold mb-2 text-main">Eliminar Conta?</h5>
                    <p class="text-muted mb-4">Vai apagar <strong id="deleteUserName" class="text-dark"></strong> permanentemente. Esta ação não pode ser desfeita.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                        <a href="#" id="confirmDeleteBtn" class="btn btn-danger px-4 rounded-pill fw-bold shadow-sm">Apagar</a>
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
    
    <script>
        // --- 1. Lógica do Dark Mode & Logo (Igual ao Modelo) ---
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

        // --- 2. Lógica dos Modais de Staff ---
        document.getElementById('editUserModal').addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            document.getElementById('editUserId').value = btn.getAttribute('data-id');
            document.getElementById('editUserName').value = btn.getAttribute('data-name');
            document.getElementById('editUserEmail').value = btn.getAttribute('data-email');
            document.getElementById('editUserPhone').value = btn.getAttribute('data-phone');
            document.getElementById('editUserRole').value = btn.getAttribute('data-role');
        });
        document.getElementById('deleteUserModal').addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            document.getElementById('deleteUserName').textContent = btn.getAttribute('data-name');
            document.getElementById('confirmDeleteBtn').href = 'apagar.php?id=' + btn.getAttribute('data-id');
        });
        
        // Gerador de Password
        const genBtn = document.getElementById("generatePasswordBtn");
        if(genBtn){
            genBtn.addEventListener("click", function() {
                const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
                let pass = ""; for (let i = 0; i < 12; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
                document.getElementById("inputPassword").value = pass;
            });
        }

        // --- 3. Notificações Toastr ---
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        
        toastr.options = { "closeButton": true, "progressBar": true, "positionClass": "toast-top-right", "timeOut": "3000" };
        
        if (success === "user_created") toastr.success("Utilizador criado com sucesso!", "SyncRide");
        if (success === "user_deleted") toastr.success("Utilizador eliminado.", "SyncRide");
        if (success === "user_updated") toastr.success("Dados atualizados.", "SyncRide");
        
        if (success) {
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({path: newUrl}, '', newUrl);
        }
    </script>
  </body>
</html>