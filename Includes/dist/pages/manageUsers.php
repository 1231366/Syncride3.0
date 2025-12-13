<?php
session_start();

// 1. VERIFICAÇÃO DE ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

require __DIR__ . '/../../../auth/dbconfig.php';

// 2. LÓGICA DE DADOS
try {
    $stmt = $pdo->query("SELECT id, name, email, phone, role FROM Users ORDER BY name ASC");
    $users = $stmt->fetchAll();
    if (!$users) $users = [];

    $totalAdmins = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 1")->fetchColumn();
    $totalDrivers = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 2")->fetchColumn();
} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Gestão de Utilizadores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        :root { --header-height-base: 56px; --bottom-nav-height: 65px; }

        /* --- 1. CABEÇALHO E LAYOUT --- */
        .app-header { position: fixed; top: 0; left: 0; right: 0; z-index: 1030; height: var(--header-height-base); }
        
        /* MOBILE APP (< 992px) */
        @media (max-width: 991.98px) {
            .app-header {
                padding-top: env(safe-area-inset-top);
                height: calc(var(--header-height-base) + env(safe-area-inset-top));
                background-color: #ffffff !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .app-sidebar, .navbar-toggler, .bi-list { display: none !important; }
            .app-main {
                margin-top: calc(var(--header-height-base) + env(safe-area-inset-top)) !important;
                padding-bottom: calc(var(--bottom-nav-height) + 20px + env(safe-area-inset-bottom)) !important;
            }
            .bottom-navbar { display: flex !important; }
            
            /* CORREÇÃO DOS MODAIS NA NOTCH */
            .modal-dialog {
                /* Empurra o modal para baixo da área segura (notch) + um pouco extra */
                margin-top: calc(env(safe-area-inset-top) + 50px) !important; 
            }
        }

        /* WEB DESKTOP (>= 992px) */
        @media (min-width: 992px) {
            .bottom-navbar { display: none !important; }
            .app-main { margin-top: var(--header-height-base) !important; padding-bottom: 0; }
        }

        /* --- 2. BARRA INFERIOR --- */
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

        /* --- 3. TABLE STYLES --- */
        /* Truncar texto no mobile */
        .truncate-mobile {
            max-width: 140px; /* Ajuste conforme necessário */
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* --- 4. CAIXAS --- */
        .small-box { position: relative; overflow: hidden; border-radius: 0.5rem; box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2); margin-bottom: 20px; }
        .small-box .icon { position: absolute; top: 10px; right: 10px; z-index: 0; font-size: 60px; color: rgba(0, 0, 0, 0.15); transition: all 0.3s linear; }
        .small-box:hover .icon { transform: scale(1.1); }
        .small-box .inner { position: relative; z-index: 1; padding: 15px; color: white; }

        .notificacao {
            position: fixed; top: 20px; right: 20px; background-color: #28a745; color: #fff; padding: 15px 20px;
            border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.2); z-index: 9999; opacity: 0; animation: fadeInOut 4s ease-in-out;
        }
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-10px); } 10% { opacity: 1; transform: translateY(0); } 90% { opacity: 1; transform: translateY(0); } 100% { opacity: 0; transform: translateY(-10px); }
        }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    
    <script>
        function mostrarNotificacao(msg) {
            const n = document.createElement("div"); n.classList.add("notificacao"); n.textContent = msg;
            document.body.appendChild(n); setTimeout(() => n.remove(), 4000);
        }
        const success = "<?php echo isset($_GET['success']) ? $_GET['success'] : ''; ?>";
        if (success === "user_created") mostrarNotificacao("Utilizador criado!");
        if (success === "user_deleted") mostrarNotificacao("Utilizador eliminado!");
        if (success === "user_updated") mostrarNotificacao("Dados atualizados!");
        if (success) window.history.replaceState({}, document.title, window.location.pathname);
    </script>

    <div class="app-wrapper">
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list" style="font-size: 1.5rem;"></i></a>
            </li>
            <li class="nav-item d-lg-none ms-2"><span class="fw-bold fs-5">Staff</span></li>
            <li class="nav-item d-none d-lg-block"><a href="#" class="nav-link">Home</a></li>
          </ul>
          <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link" href="#" data-lte-toggle="fullscreen"><i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i><i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i></a></li>
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="User" />
                <span class="d-none d-md-inline"><?php  echo $_SESSION['name']; ?></span>
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
        <div class="sidebar-brand">
          <a href="./admin.php" class="brand-link">
            <img src="https://syncride.webminds.pt/Includes/dist/assets/img/AdminLTELogo.png" alt="Logo" class="brand-image opacity-75 shadow" />
            <span class="brand-text fw-light">SyncRide</span>
          </a>
        </div>
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
              <li class="nav-item"><a href="admin.php" class="nav-link"><i class="nav-icon bi bi-speedometer"></i><p>Dashboard</p></a></li>
              <li class="nav-item"><a href="ManageRides.php" class="nav-link"><i class="nav-icon bi bi-box-seam-fill"></i><p>Viagens</p></a></li>
              <li class="nav-item"><a href="manageUsers.php" class="nav-link active"><i class="nav-icon bi bi-people-fill"></i><p>Funcionários</p></a></li>
              <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link"><i class="nav-icon bi bi-graph-up"></i><p>Estatísticas</p></a></li>
              <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-camera-fill"></i><p>No Shows</p></a></li>
              <li class="nav-item"><a href="manageStorage.php" class="nav-link"><i class="nav-icon bi bi-archive-fill"></i><p>Armazenamento</p></a></li>
            </ul>
          </nav>
        </div>
      </aside>

      <div class="bottom-navbar">
        <a href="admin.php" class="nav-item-bottom"><i class="bi bi-house-door-fill"></i><span>Home</span></a>
        <a href="ManageRides.php" class="nav-item-bottom"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
        <a href="admin_driver_stats.php" class="nav-item-bottom"><i class="bi bi-bar-chart-fill"></i><span>Stats</span></a>
        <a href="manageUsers.php" class="nav-item-bottom active"><i class="bi bi-people-fill"></i><span>Staff</span></a>
        <a href="#" class="nav-item-bottom" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"><i class="bi bi-grid-fill"></i><span>Mais</span></a>
      </div>

      <div class="offcanvas offcanvas-bottom" tabindex="-1" id="mobileMenu" style="height: 50vh; border-top-left-radius: 20px; border-top-right-radius: 20px;">
        <div class="offcanvas-header"><h5 class="offcanvas-title fw-bold">Menu Completo</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
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
              <div class="col-sm-6"><h3 class="mb-0">Gestão de Utilizadores</h3></div>
              <div class="col-sm-6 d-none d-sm-block">
                <ol class="breadcrumb float-sm-end"><li class="breadcrumb-item"><a href="admin.php">Home</a></li><li class="breadcrumb-item active">Funcionários</li></ol>
              </div>
            </div>
          </div>
        </div>

        <div class="app-content">
          <div class="container-fluid">
            
            <div class="row g-3 mb-4">
              <div class="col-lg-3 col-6">
                <div class="small-box text-bg-primary mb-0 h-100">
                  <div class="inner"><h3><?= $totalAdmins ?></h3><p>Admins</p></div>
                  <div class="icon"><i class="bi bi-person-badge-fill"></i></div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="small-box text-bg-success mb-0 h-100">
                  <div class="inner"><h3><?= $totalDrivers ?></h3><p>Condutores</p></div>
                  <div class="icon"><i class="bi bi-car-front-fill"></i></div>
                </div>
              </div>
              <div class="col-12 col-lg-6">
                  <div class="info-box text-bg-warning h-100 mb-0" data-bs-toggle="modal" data-bs-target="#modalCriarUtilizador" style="cursor: pointer; display: flex; align-items: center; padding: 1rem; border-radius: 0.5rem;">
                    <span class="info-box-icon text-white" style="font-size: 2rem; background: rgba(0,0,0,0.1); width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 0.5rem;"><i class="bi bi-person-plus-fill"></i></span>
                    <div class="info-box-content text-white ps-3">
                        <span class="d-block fw-bold fs-5">Adicionar Membro</span>
                        <span class="small">Criar nova conta</span>
                    </div>
                  </div>
              </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white"><h3 class="card-title">Colaboradores</h3></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 10px">#</th>
                                    <th>Nome</th>
                                    <th class="d-none d-lg-table-cell">Email</th>
                                    <th class="d-none d-lg-table-cell">Telefone</th>
                                    <th class="d-none d-lg-table-cell">Cargo</th> <th class="text-end" style="width: 120px">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $index => $user): ?>
                                    <?php 
                                        $roleBadge = $user['role'] == 1 ? '<span class="badge bg-primary">Admin</span>' : '<span class="badge bg-success">Condutor</span>';
                                    ?>
                                    <tr>
                                        <td><?= $index + 1 ?>.</td>
                                        
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-bold truncate-mobile text-dark"><?= htmlspecialchars($user['name']) ?></span>
                                                <div class="d-lg-none ms-2"><?= $roleBadge ?></div>
                                            </div>
                                        </td>

                                        <td class="d-none d-lg-table-cell"><?= htmlspecialchars($user['email']) ?></td>
                                        <td class="d-none d-lg-table-cell"><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                        
                                        <td class="d-none d-lg-table-cell"><?= $roleBadge ?></td>
                                        
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-warning me-1 text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#editUserModal"
                                               data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>"
                                               data-email="<?= htmlspecialchars($user['email']) ?>" data-phone="<?= htmlspecialchars($user['phone']) ?>"
                                               data-role="<?= $user['role'] ?>">
                                               <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#deleteUserModal"
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
      </main>

      <footer class="app-footer">
        <div class="float-end d-none d-sm-inline"></div>
        <strong>SyncRide All rights reserved.</strong>
      </footer>
    </div>

    <div class="modal fade" id="modalCriarUtilizador" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary">Criar Utilizador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="createUser.php" method="POST">
                        <div class="mb-3"><label class="form-label small text-muted">Nome</label><input type="text" class="form-control bg-light border-0" name="name" required /></div>
                        <div class="mb-3"><label class="form-label small text-muted">Email</label><input type="email" class="form-control bg-light border-0" name="email" required /></div>
                        <div class="mb-3"><label class="form-label small text-muted">Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light border-0" id="inputPassword" name="password" required readonly />
                                <button type="button" class="btn btn-outline-secondary border-0 bg-light" id="generatePasswordBtn"><i class="bi bi-magic"></i></button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3"><label class="form-label small text-muted">Telefone</label><input type="text" class="form-control bg-light border-0" name="phone" required /></div>
                            <div class="col-6 mb-3"><label class="form-label small text-muted">Cargo</label>
                                <select class="form-select bg-light border-0" name="role" required>
                                    <option value="2">Condutor</option>
                                    <option value="1">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-grid"><button type="submit" class="btn btn-primary py-2 fw-bold">Criar Conta</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-warning">Editar Utilizador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="editar_utilizador.php" method="POST">
                        <input type="hidden" id="editUserId" name="id">
                        <div class="mb-3"><label class="form-label small text-muted">Nome</label><input type="text" class="form-control bg-light border-0" id="editUserName" name="name" required></div>
                        <div class="mb-3"><label class="form-label small text-muted">Email</label><input type="email" class="form-control bg-light border-0" id="editUserEmail" name="email" required></div>
                        <div class="row">
                             <div class="col-6 mb-3"><label class="form-label small text-muted">Telefone</label><input type="text" class="form-control bg-light border-0" id="editUserPhone" name="phone" required></div>
                             <div class="col-6 mb-3"><label class="form-label small text-muted">Cargo</label>
                                <select class="form-select bg-light border-0" id="editUserRole" name="role" required>
                                    <option value="1">Admin</option>
                                    <option value="2">Condutor</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4"><label class="form-label small text-muted">Nova Password (Opcional)</label><input type="password" class="form-control bg-light border-0" name="password" placeholder="Manter atual"></div>
                        <div class="d-grid"><button type="submit" class="btn btn-warning text-white py-2 fw-bold">Guardar Alterações</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body text-center p-4">
                    <div class="text-danger mb-3"><i class="bi bi-exclamation-circle-fill" style="font-size: 3rem;"></i></div>
                    <h5 class="fw-bold mb-2">Eliminar Conta?</h5>
                    <p class="text-muted mb-4">Vai apagar <strong id="deleteUserName" class="text-dark"></strong> permanentemente.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                        <a href="#" id="confirmDeleteBtn" class="btn btn-danger px-4 fw-bold">Apagar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <script>
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
        document.getElementById("generatePasswordBtn").addEventListener("click", function() {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
            let pass = ""; for (let i = 0; i < 12; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
            document.getElementById("inputPassword").value = pass;
        });
    </script>
  </body>
</html>