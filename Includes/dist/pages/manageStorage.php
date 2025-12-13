<?php
session_start();

// 1. VERIFICAÇÃO DE ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../auth/dbconfig.php';
$success = isset($_GET['success']) ? $_GET['success'] : null;
?>

<!DOCTYPE html>
<html lang="pt">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Armazenamento - SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        :root { --header-height-base: 56px; --bottom-nav-height: 65px; }

        /* --- HEADER --- */
        .app-header { position: fixed; top: 0; left: 0; right: 0; z-index: 1030; height: var(--header-height-base); }
        
        /* MOBILE APP (< 992px) */
        @media (max-width: 991.98px) {
            .app-header {
                padding-top: env(safe-area-inset-top);
                height: calc(var(--header-height-base) + env(safe-area-inset-top));
                background-color: #ffffff !important; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .app-sidebar, .navbar-toggler, .bi-list { display: none !important; }
            .app-main {
                margin-top: calc(var(--header-height-base) + env(safe-area-inset-top)) !important;
                padding-bottom: calc(var(--bottom-nav-height) + 20px + env(safe-area-inset-bottom)) !important;
            }
            .bottom-navbar { display: flex !important; }
        }

        /* WEB DESKTOP (>= 992px) */
        @media (min-width: 992px) {
            .bottom-navbar { display: none !important; }
            .app-main { margin-top: var(--header-height-base) !important; }
        }

        /* --- BARRA INFERIOR --- */
        .bottom-navbar {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom));
            background: #ffffff; box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            display: none; justify-content: space-around; align-items: flex-start;
            padding-top: 10px; padding-bottom: env(safe-area-inset-bottom); z-index: 1040;
            border-top-left-radius: 20px; border-top-right-radius: 20px;
        }
        .nav-item-bottom { display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: #adb5bd; font-size: 10px; font-weight: 500; transition: all 0.3s ease; width: 20%; }
        .nav-item-bottom i { font-size: 22px; margin-bottom: 4px; transition: transform 0.2s; }
        .nav-item-bottom.active { color: #0d6efd; }
        .nav-item-bottom.active i { transform: translateY(-3px); }

        /* --- GERAL --- */
        .card { border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .card-header { background-color: #fff; border-bottom: 1px solid #f0f0f0; padding: 1rem; }
        .card-title { font-weight: 600; }
        .list-group-item { border-left: none; border-right: none; }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">

    <script>
        const success = "<?php echo $success; ?>";
        if (success) {
            // Remove o parâmetro da URL
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, document.title, url.pathname);
        }
    </script>

    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list" style="font-size: 1.5rem;"></i></a></li>
            <li class="nav-item d-lg-none ms-2"><span class="fw-bold fs-5">Armazenamento</span></li>
            <li class="nav-item d-none d-lg-block"><a href="#" class="nav-link">Home</a></li>
          </ul>
          <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link" href="#" data-lte-toggle="fullscreen"><i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i><i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i></a></li>
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="User" />
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="rounded-circle shadow" alt="User" />
                  <p><?php echo $_SESSION['name']; ?> - Admin</p>
                </li>
                <li class="user-footer"><a href="logout.php" class="btn btn-default btn-flat float-end">Sair</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand"><a href="./admin.php" class="brand-link"><span class="brand-text fw-light">SyncRide</span></a></div>
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
              <li class="nav-item"><a href="admin.php" class="nav-link"><i class="nav-icon bi bi-speedometer"></i><p>Dashboard</p></a></li>
              <li class="nav-item"><a href="ManageRides.php" class="nav-link"><i class="nav-icon bi bi-box-seam-fill"></i><p>Viagens</p></a></li>
              <li class="nav-item"><a href="manageUsers.php" class="nav-link"><i class="nav-icon bi bi-people-fill"></i><p>Funcionários</p></a></li>
              <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link"><i class="nav-icon bi bi-graph-up"></i><p>Estatísticas</p></a></li>
              <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-camera-fill"></i><p>No Shows</p></a></li>
              <li class="nav-item"><a href="manageStorage.php" class="nav-link active"><i class="nav-icon bi bi-archive-fill"></i><p>Armazenamento</p></a></li>
            </ul>
          </nav>
        </div>
      </aside>

      <div class="bottom-navbar">
        <a href="admin.php" class="nav-item-bottom"><i class="bi bi-house-door-fill"></i><span>Home</span></a>
        <a href="ManageRides.php" class="nav-item-bottom"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
        <a href="admin_driver_stats.php" class="nav-item-bottom"><i class="bi bi-bar-chart-fill"></i><span>Stats</span></a>
        <a href="manageUsers.php" class="nav-item-bottom"><i class="bi bi-people-fill"></i><span>Staff</span></a>
        <a href="#" class="nav-item-bottom" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"><i class="bi bi-grid-fill"></i><span>Mais</span></a>
      </div>

      <div class="offcanvas offcanvas-bottom" tabindex="-1" id="mobileMenu" style="height: 50vh; border-top-left-radius: 20px; border-top-right-radius: 20px;">
        <div class="offcanvas-header"><h5 class="offcanvas-title fw-bold">Menu</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
        <div class="offcanvas-body">
            <div class="row g-3 text-center">
                <div class="col-4"><a href="ManageNoShows.php" class="d-block p-3 rounded bg-light text-dark text-decoration-none"><i class="bi bi-camera-fill fs-1 text-danger"></i><div class="small mt-2">No Shows</div></a></div>
                <div class="col-4"><a href="manageStorage.php" class="d-block p-3 rounded bg-light text-dark text-decoration-none"><i class="bi bi-hdd-fill fs-1 text-warning"></i><div class="small mt-2">Storage</div></a></div>
                <div class="col-4"><a href="logout.php" class="d-block p-3 rounded bg-light text-dark text-decoration-none"><i class="bi bi-box-arrow-right fs-1 text-secondary"></i><div class="small mt-2">Sair</div></a></div>
            </div>
        </div>
      </div>

      <main class="app-main">
        <div class="app-content-header">
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-6"><h3 class="mb-0">Gestão de Armazenamento</h3></div>
              <div class="col-sm-6 d-none d-sm-block">
                 <ol class="breadcrumb float-sm-end"><li class="breadcrumb-item"><a href="admin.php">Home</a></li><li class="breadcrumb-item active">Storage</li></ol>
              </div>
            </div>
          </div>
        </div>

        <div class="app-content">
          <div class="container-fluid">
            
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">Ações Rápidas</h3></div>
                <div class="card-body">
                    <div class="row g-3"> <div class="col-md-4">
                            <button class="btn btn-primary w-100 py-2" id="backup-btn">
                                <i class="bi bi-box-arrow-down me-2"></i> Fazer Backup
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-danger w-100 py-2" id="delete-rides-btn">
                                <i class="bi bi-trash me-2"></i> Eliminar Viagens
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-warning w-100 py-2 text-white" id="clear-data-btn">
                                <i class="bi bi-eraser me-2"></i> Limpar Logs
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><h3 class="card-title">Saúde do Sistema</h3></div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                            <?php
                            $sql = "SELECT date FROM Logs WHERE Action = 'Backup da base de dados realizado' ORDER BY date DESC LIMIT 1";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                            $lastBackup = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($lastBackup) {
                                $diff = (time() - strtotime($lastBackup['date'])) / (60 * 60 * 24);
                                if ($diff < 7) { $progress = 100; $status = "Ótimo! Backup recente."; $badge = "success"; }
                                elseif ($diff < 30) { $progress = 60; $status = "Aviso: Backup antigo."; $badge = "warning"; }
                                else { $progress = 30; $status = "Perigo! Faça backup agora."; $badge = "danger"; }
                            } else {
                                $progress = 0; $status = "Nenhum backup encontrado!"; $badge = "danger";
                            }
                            ?>
                            <span class="badge bg-<?php echo $badge; ?> p-2 mb-3 fs-6"><?php echo $status; ?></span>
                            <div class="progress w-100" style="height: 20px;">
                                <div class="progress-bar bg-<?php echo $badge; ?>" role="progressbar" style="width: <?php echo $progress; ?>%">
                                    <?php echo $progress; ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><h3 class="card-title">Histórico de Logs</h3></div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php
                                $sql = "SELECT Action, date FROM Logs ORDER BY date DESC LIMIT 8";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (count($logs) > 0) {
                                    foreach ($logs as $row) {
                                        echo '<li class="list-group-item d-flex justify-content-between align-items-center py-3">';
                                        echo '<span class="fw-500">' . htmlspecialchars($row['Action']) . '</span>';
                                        echo '<small class="text-muted">' . date("d/m H:i", strtotime($row['date'])) . '</small>';
                                        echo '</li>';
                                    }
                                } else {
                                    echo '<li class="list-group-item text-center p-3">Sem registos.</li>';
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

      <footer class="app-footer">
        <div class="float-end d-none d-sm-inline"></div>
        <strong>SyncRide All rights reserved.</strong>
      </footer>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <script>
        // Toastr config
        toastr.options = { "closeButton": true, "progressBar": true, "positionClass": "toast-top-right", "timeOut": "3000" };

        document.getElementById('backup-btn').addEventListener('click', function () {
            fetch('backup.php').then(r => {
                if(!r.ok) throw new Error('Erro no backup');
                return r.blob();
            }).then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a'); a.href = url; a.download = 'backup.sql';
                document.body.appendChild(a); a.click(); a.remove();
                toastr.success('Backup transferido!');
                setTimeout(() => window.location.reload(), 1000);
            }).catch(e => toastr.error(e.message));
        });

        document.getElementById('delete-rides-btn').addEventListener('click', function () {
            if (confirm('Eliminar TODAS as viagens? Irreversível!')) {
                fetch('delete_rides.php', { method: 'POST' }).then(r => r.json()).then(data => {
                    if (data.success) { toastr.success('Viagens eliminadas!'); setTimeout(() => window.location.reload(), 1000); }
                    else toastr.error(data.message);
                });
            }
        });
        
        document.getElementById('clear-data-btn').addEventListener('click', function () {
            if (confirm('Limpar histórico de ações?')) {
                fetch('clear_logs.php', { method: 'POST' }).then(r => r.json()).then(data => {
                    if (data.success) { toastr.success('Logs limpos!'); setTimeout(() => window.location.reload(), 1000); }
                    else toastr.error(data.message);
                });
            }
        });
    </script>
  </body>
</html>