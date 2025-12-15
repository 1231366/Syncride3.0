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
?>

<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Armazenamento | SyncRide</title>
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

        /* --- CARDS --- */
        .card {
            background-color: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;
        }
        .card-header { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; }
        .card-title { font-family: var(--font-display); font-weight: 600; font-size: 1.125rem; color: var(--text-main); }

        /* --- ACTION BUTTONS --- */
        .btn-action {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            width: 100%; padding: 1.5rem; border: none; border-radius: var(--radius-md);
            font-weight: 600; font-size: 1rem; transition: transform 0.2s, box-shadow 0.2s;
            color: #fff; box-shadow: var(--shadow-sm); height: 100%;
        }
        .btn-action:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); color: #fff; }
        .btn-action i { font-size: 2rem; margin-bottom: 10px; }

        .btn-backup { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .btn-delete { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .btn-clear { background: linear-gradient(135deg, #f59e0b, #d97706); }

        /* --- LISTS --- */
        .list-group-item {
            background-color: transparent; border: none; border-bottom: 1px solid var(--border-color);
            padding: 1rem; color: var(--text-main); font-size: 0.9rem;
        }
        .list-group-item:last-child { border-bottom: none; }
        
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
              <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link"><i class="nav-icon bi bi-bar-chart-fill"></i><p>Estatísticas</p></a></li>
              <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-exclamation-triangle-fill"></i><p>No Shows</p></a></li>
              <li class="nav-item">
                  <a href="manageStorage.php" class="nav-link active"> 
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
        <a href="manageStorage.php" class="nav-item-bottom active text-decoration-none d-flex flex-column align-items-center">
            <i class="bi bi-hdd-fill"></i><span>Storage</span>
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
                    <h3 class="fw-bold mb-0 text-main">Gestão de Armazenamento</h3>
                    <p class="text-muted mb-0 small">Backups, limpeza e logs do sistema.</p>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <button class="btn btn-action btn-backup" id="backup-btn">
                        <i class="bi bi-cloud-download-fill"></i>
                        <span>Fazer Backup</span>
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-action btn-delete" id="delete-rides-btn">
                        <i class="bi bi-trash3-fill"></i>
                        <span>Eliminar Viagens</span>
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-action btn-clear" id="clear-data-btn">
                        <i class="bi bi-eraser-fill"></i>
                        <span>Limpar Logs</span>
                    </button>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6 connectedSortable">
                    <div class="card h-100 border-0">
                        <div class="card-header border-bottom">
                            <h3 class="card-title fw-bold"><i class="bi bi-activity me-2 text-primary"></i>Saúde do Sistema</h3>
                        </div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-5">
                            <?php
                            $sql = "SELECT date FROM Logs WHERE Action = 'Backup da base de dados realizado' ORDER BY date DESC LIMIT 1";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                            $lastBackup = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $progress = 0; $status = "Sem Dados"; $badge = "secondary"; $msg = "Nenhum registo encontrado.";

                            if ($lastBackup) {
                                $diff = (time() - strtotime($lastBackup['date'])) / (60 * 60 * 24);
                                if ($diff < 7) { 
                                    $progress = 100; $status = "Sistema Saudável"; $badge = "success"; $msg = "Último backup: " . date("d/m/Y", strtotime($lastBackup['date'])); 
                                } elseif ($diff < 30) { 
                                    $progress = 60; $status = "Atenção Necessária"; $badge = "warning"; $msg = "Backup antigo (" . round($diff) . " dias)."; 
                                } else { 
                                    $progress = 30; $status = "Risco Crítico"; $badge = "danger"; $msg = "Recomendado backup urgente!"; 
                                }
                            }
                            ?>
                            
                            <div class="mb-4 position-relative">
                                <i class="bi bi-database-check text-<?php echo $badge; ?>" style="font-size: 4rem;"></i>
                                <span class="position-absolute top-0 start-100 translate-middle p-2 bg-<?php echo $badge; ?> border border-light rounded-circle"></span>
                            </div>

                            <h4 class="fw-bold text-main mb-1"><?php echo $status; ?></h4>
                            <p class="text-muted small mb-3"><?php echo $msg; ?></p>
                            
                            <div class="progress w-100 rounded-pill bg-light" style="height: 10px;">
                                <div class="progress-bar bg-<?php echo $badge; ?>" role="progressbar" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 connectedSortable">
                    <div class="card h-100 border-0">
                        <div class="card-header border-bottom">
                            <h3 class="card-title fw-bold"><i class="bi bi-journal-text me-2 text-secondary"></i>Histórico Recente</h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php
                                $sql = "SELECT Action, date FROM Logs ORDER BY date DESC LIMIT 6";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (count($logs) > 0) {
                                    foreach ($logs as $row) {
                                        echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                        echo '<span class="fw-medium text-truncate" style="max-width: 70%;">' . htmlspecialchars($row['Action']) . '</span>';
                                        echo '<small class="text-muted bg-light px-2 py-1 rounded">' . date("d/m H:i", strtotime($row['date'])) . '</small>';
                                        echo '</li>';
                                    }
                                } else {
                                    echo '<li class="list-group-item text-center p-5 text-muted">Sem registos recentes.</li>';
                                }
                                ?>
                            </ul>
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
    
    <script>
        // --- Dark Mode Logic ---
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
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5';
        }

        function updateLogo(theme) {
            const newSrc = theme === 'dark' ? logoLight : logoDark;
            if(headerLogo) headerLogo.src = newSrc;
            if(sidebarLogo) sidebarLogo.src = newSrc;
        }

        // --- Action Buttons Logic ---
        toastr.options = { "closeButton": true, "progressBar": true, "positionClass": "toast-top-right", "timeOut": "3000" };

        document.getElementById('backup-btn').addEventListener('click', function () {
            fetch('backup.php').then(r => {
                if(!r.ok) throw new Error('Erro no backup');
                return r.blob();
            }).then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a'); a.href = url; a.download = 'backup.sql';
                document.body.appendChild(a); a.click(); a.remove();
                toastr.success('Backup transferido com sucesso!', 'SyncRide');
                setTimeout(() => window.location.reload(), 1500);
            }).catch(e => toastr.error(e.message, 'Erro'));
        });

        document.getElementById('delete-rides-btn').addEventListener('click', function () {
            if (confirm('ATENÇÃO: Vai apagar TODAS as viagens do sistema. Esta ação é irreversível.\n\nTem a certeza?')) {
                fetch('delete_rides.php', { method: 'POST' }).then(r => r.json()).then(data => {
                    if (data.success) { toastr.success('Todas as viagens foram eliminadas.', 'Concluído'); setTimeout(() => window.location.reload(), 1500); }
                    else toastr.error(data.message, 'Erro');
                });
            }
        });
        
        document.getElementById('clear-data-btn').addEventListener('click', function () {
            if (confirm('Tem a certeza que deseja limpar o histórico de logs?')) {
                fetch('clear_logs.php', { method: 'POST' }).then(r => r.json()).then(data => {
                    if (data.success) { toastr.success('Histórico de logs limpo.', 'Concluído'); setTimeout(() => window.location.reload(), 1500); }
                    else toastr.error(data.message, 'Erro');
                });
            }
        });
    </script>
  </body>
</html>