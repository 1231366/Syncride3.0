<?php
session_start(); 

// Verifica se o usuário está logado e tem a role de admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Gerir No Shows - SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        :root { --header-height-base: 56px; --bottom-nav-height: 65px; }
        .app-header { position: fixed; top: 0; left: 0; right: 0; z-index: 1030; height: var(--header-height-base); }

        /* ================= MOBILE APP (< 992px) ================= */
        @media (max-width: 991.98px) {
            .app-header { padding-top: env(safe-area-inset-top); height: calc(var(--header-height-base) + env(safe-area-inset-top)); background-color: #ffffff !important; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            .app-sidebar, .navbar-toggler, .bi-list { display: none !important; }
            .app-main { margin-top: calc(var(--header-height-base) + env(safe-area-inset-top)) !important; padding-bottom: calc(var(--bottom-nav-height) + 20px + env(safe-area-inset-bottom)) !important; }
            .bottom-navbar { display: flex !important; }
            .modal-dialog { margin-top: calc(env(safe-area-inset-top) + 60px) !important; margin-bottom: 80px; }

            /* PESQUISA ESTÉTICA */
            .dataTables_filter { width: 100%; margin-bottom: 10px; position: relative; float: none !important; text-align: left !important; }
            .dataTables_filter label { width: 100%; margin: 0; }
            .dataTables_filter input { width: 100% !important; margin: 0 !important; height: 40px; border-radius: 50px; border: 1px solid #f0f0f0; background-color: #f8f9fa; padding-left: 40px; font-size: 0.9rem; }
            .dataTables_filter::before { content: "\F52A"; font-family: "bootstrap-icons"; position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #adb5bd; font-size: 1.1rem; z-index: 10; pointer-events: none; }
            .dataTables_length { display: none; } /* Esconde seletor de quantidade */

            /* CARTÕES NO SHOW COMPACTOS */
            #tabelaNoShows thead { display: none; }
            #tabelaNoShows tbody tr {
                display: block; position: relative; background: #fff; border-bottom: 1px solid #eee; padding: 10px 12px; margin: 0; 
            }
            #tabelaNoShows tbody td { display: block; border: none; padding: 0; width: 100% !important; }

            /* ID */
            #tabelaNoShows tbody td:nth-child(1) { position: absolute; top: 10px; left: 12px; font-size: 0.7rem; color: #adb5bd; font-weight: 700; }
            #tabelaNoShows tbody td:nth-child(1)::before { content: "#"; }

            /* Data & Hora */
            #tabelaNoShows tbody td:nth-child(2) { margin-top: 14px; font-size: 0.95rem; font-weight: 700; color: #333; }

            /* Condutor */
            #tabelaNoShows tbody td:nth-child(3) { font-size: 0.8rem; color: #666; margin-bottom: 4px; }
            #tabelaNoShows tbody td:nth-child(3)::before { content: "👮 "; margin-right: 4px; }

            /* Rota */
            #tabelaNoShows tbody td:nth-child(4) { font-size: 0.85rem; color: #444; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 85%; }
            #tabelaNoShows tbody td:nth-child(4)::before { content: "🗺️ "; margin-right: 4px; }

            /* Ações (Botão Foto) */
            #tabelaNoShows tbody td:last-child { position: absolute; top: 10px; right: 10px; width: auto !important; }
            .btn-group-sm > .btn { width: 35px; height: 35px; border-radius: 8px !important; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
            
            /* Paginação */
            .dataTables_paginate { margin-top: 15px !important; display: flex; justify-content: center; }
            .pagination .page-link { padding: 6px 12px; font-size: 0.85rem; }
        }

        /* ================= WEB DESKTOP (>= 992px) ================= */
        @media (min-width: 992px) {
            .bottom-navbar { display: none !important; }
            .app-main { margin-top: var(--header-height-base) !important; }
            .table-borderless tbody tr { border-bottom: 1px solid #dee2e6; }
            .btn-group-sm > .btn.rounded-circle { width: 32px; height: 32px; padding: 0; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
            div.dataTables_wrapper div.dataTables_filter { text-align: right; }
            div.dataTables_wrapper div.dataTables_filter input { margin-left: 0.5em; border-radius: .25rem; border: 1px solid #ced4da; padding: .375rem .75rem; }
        }

        /* --- GERAL --- */
        .bottom-navbar {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom));
            background: #ffffff; box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            display: none; justify-content: space-around; align-items: flex-start;
            padding-top: 10px; padding-bottom: env(safe-area-inset-bottom); z-index: 1040;
            border-top-left-radius: 20px; border-top-right-radius: 20px;
        }
        .nav-item-bottom { display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: #adb5bd; font-size: 10px; font-weight: 500; width: 20%; transition: 0.2s; }
        .nav-item-bottom i { font-size: 22px; margin-bottom: 4px; transition: transform 0.2s; }
        .nav-item-bottom.active { color: #0d6efd; }
        .nav-item-bottom.active i { transform: translateY(-3px); }
        
        #photoModalImage { width: 100%; height: auto; border-radius: 8px; }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  
    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list" style="font-size: 1.5rem;"></i></a></li>
            <li class="nav-item d-lg-none ms-2"><span class="fw-bold fs-5">No Shows</span></li>
            <li class="nav-item d-none d-lg-block"><a href="#" class="nav-link">Home</a></li>
          </ul>
          <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link" href="#" data-lte-toggle="fullscreen"><i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i><i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i></a></li>
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="User" />
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary"><p><?php echo $_SESSION['name']; ?> - Admin</p></li>
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
              <li class="nav-item"><a href="ManageNoShows.php" class="nav-link active"><i class="nav-icon bi bi-camera-fill"></i><p>No Shows</p></a></li>
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
              <div class="col-sm-6"><h3 class="mb-0">Gerir No Shows</h3></div>
              <div class="col-sm-6 d-none d-sm-block">
                 <ol class="breadcrumb float-sm-end"><li class="breadcrumb-item"><a href="admin.php">Home</a></li><li class="breadcrumb-item active">No Shows</li></ol>
              </div>
            </div>
          </div>
        </div>

        <div class="app-content">
          <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0 p-md-3">
                    <div class="table-responsive">
                        <table id="tabelaNoShows" class="table table-borderless table-hover align-middle" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Data & Hora</th>
                                    <th>Condutor</th>
                                    <th>Rota</th>
                                    <th class="text-center">Ações</th>
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

      <footer class="app-footer">
        <div class="float-end d-none d-sm-inline"></div>
        <strong>SyncRide All rights reserved.</strong>
      </footer>
    </div>

    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="photoModalTitle">Foto</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body text-center">
            <img src="" id="photoModalImage" alt="Comprovativo">
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>

    <script>
      let tabelaNoShows;
      let photoModal;

      $(document).ready(function () {
        photoModal = new bootstrap.Modal(document.getElementById('photoModal'));

        tabelaNoShows = $('#tabelaNoShows').DataTable({
            "processing": true, "serverSide": false, 
            "ajax": { "url": "load_noshows_data.php", "type": "GET", "dataSrc": "data" },
            "columns": [
                { "data": "id", "width": "10%" }, 
                { "data": "data_hora", "width": "20%" }, 
                { "data": "condutor", "width": "20%" }, 
                { "data": "rota", "width": "30%" }, 
                { "data": "acoes", "width": "20%", "orderable": false, "className": "text-center" }
            ],
            "language": { "search": "", "searchPlaceholder": "Procurar...", "lengthMenu": "_MENU_", "info": "", "paginate": { "next": "→", "previous": "←" }, "zeroRecords": "Nada encontrado" },
            "order": [[1, 'desc']], "pageLength": 10,
            "dom": '<"row mb-3"<"col-12"f>><"row"<"col-12"t>><"row mt-3"<"col-12"p>>'
        });
      });

      function openPhotoModal(tripId, photoPath) {
        $('#photoModalTitle').text('No Show #' + tripId);
        $('#photoModalImage').attr('src', photoPath);
        photoModal.show();
      }

      toastr.options = { "closeButton": true, "progressBar": true, "positionClass": "toast-top-right", "timeOut": "5000" };
    </script>
  </body>
</html>