<?php
session_start(); 

// Verifica se o usuário está logado e tem a role de admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

// Carregar condutores para os Modais
require __DIR__ . '/../../../auth/dbconfig.php';
try {
    $stmt = $pdo->prepare("SELECT ID, name FROM Users WHERE role = 2 ORDER BY name ASC");
    $stmt->execute();
    $condutores = $stmt->fetchAll();
} catch (PDOException $e) {
    $condutores = []; 
}
?>

<!DOCTYPE html>
<html lang="pt">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>Gerir Viagens - SyncRide</title>
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

                .card-header-custom .dataTables_filter { width: 100%; margin-bottom: 0 !important; position: relative; float: none !important; text-align: left !important; }
                .card-header-custom .dataTables_filter label { width: 100%; margin: 0; }
                .card-header-custom .dataTables_filter input { width: 100% !important; margin: 0 !important; height: 40px; border-radius: 50px; border: 1px solid #f0f0f0; background-color: #f8f9fa; padding-left: 40px; font-size: 0.9rem; }
                .card-header-custom .dataTables_filter::before { content: "\F52A"; font-family: "bootstrap-icons"; position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #adb5bd; font-size: 1.1rem; z-index: 10; pointer-events: none; }
                
                .card-header-custom { padding: 10px; flex-direction: column; gap: 8px; }
                .card-header-custom .nav-pills { width: 100%; order: 0; }
                .card-header-custom .search-and-buttons { width: 100%; flex-direction: column; gap: 8px; order: 1; }
                .card-header-custom .search-and-buttons .dataTables_filter { order: 1; }
                .card-header-custom .search-and-buttons .buttons-group { order: 2; width: 100%; display: flex; gap: 8px; }
                .card-header-custom .search-and-buttons .buttons-group .dropdown, .card-header-custom .search-and-buttons .buttons-group .btn-nova-viagem { flex-grow: 1; }

                /* --- CARTÕES MOBILE --- */
                #tabelaViagens thead { display: none; }
                #tabelaViagens tbody tr { display: block; position: relative; background: #fff; border-bottom: 1px solid #eee; padding: 10px 12px; margin: 0; }
                #tabelaViagens tbody td { display: block; border: none; padding: 0; width: 100% !important; }
                #tabelaViagens tbody td:nth-child(1) { display: block !important; position: absolute; top: 10px; left: 12px; font-size: 0.7rem; color: #adb5bd; font-weight: 700; width: auto !important; }
                #tabelaViagens tbody td:nth-child(1)::before { content: "#"; }
                #tabelaViagens tbody td:nth-child(2) { margin-top: 14px; font-size: 0.95rem; font-weight: 700; color: #333; line-height: 1.2; }
                #tabelaViagens tbody td:nth-child(3) { font-size: 0.8rem; color: #666; margin-bottom: 4px; display: flex; align-items: center; }
                #tabelaViagens tbody td:nth-child(3)::before { content: "🚘 "; margin-right: 4px; font-size: 0.8rem; }
                #tabelaViagens tbody td:nth-child(4), #tabelaViagens tbody td:nth-child(5) { display: inline-block !important; width: auto !important; font-size: 0.85rem; color: #444; font-weight: 500; max-width: 42%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: bottom; }
                #tabelaViagens tbody td:nth-child(4)::after { content: " ➝ "; margin: 0 4px; color: #999; font-size: 0.8rem; }
                #tabelaViagens tbody td:nth-child(6) { display: none; } 
                #tabelaViagens tbody td:last-child { position: absolute; top: 8px; right: 8px; width: auto !important; margin: 0; padding: 0; display: flex; gap: 6px; }
                
                .nav-pills .nav-item { flex: 1; }
                .nav-pills .nav-link { width: 100%; text-align: center; padding: 6px 2px; font-size: 0.8rem; white-space: nowrap; border-radius: 8px; }
                .dataTables_info { display: none; }
                .dataTables_paginate { margin-top: 15px !important; display: flex; justify-content: center; }
                .pagination .page-link { padding: 6px 12px; font-size: 0.85rem; }
            }

            /* ================= WEB DESKTOP (>= 992px) ================= */
            @media (min-width: 992px) {
                .bottom-navbar { display: none !important; }
                .app-main { margin-top: var(--header-height-base) !important; }
                .table-borderless tbody tr { border-bottom: 1px solid #dee2e6; }
                .card-header-custom { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; padding: 1rem; }
                .btn-group-sm > .btn.rounded-circle { width: 32px; height: 32px; padding: 0; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
                .nav-pills .nav-link { margin-right: 5px; }
                .card-header-custom .search-and-buttons { display: flex; align-items: center; gap: 10px; margin-left: auto; }
                .card-header-custom .search-and-buttons .dataTables_filter { order: 1; margin: 0 !important; padding: 0; }
                .card-header-custom .search-and-buttons .dataTables_filter label { font-size: 0; }
                .card-header-custom .search-and-buttons .dataTables_filter input { width: 200px !important; margin-left: 0 !important; border-radius: .25rem; border: 1px solid #ced4da; padding: .375rem .75rem; }
                .card-header-custom .search-and-buttons .buttons-group { order: 2; display: flex; align-items: center; gap: 8px; }
                .loading-badge { margin-right: 0 !important; } 
            }

            /* --- GERAL --- */
            .bottom-navbar { position: fixed; bottom: 0; left: 0; right: 0; height: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom)); background: #ffffff; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); display: none; justify-content: space-around; align-items: flex-start; padding-top: 10px; padding-bottom: env(safe-area-inset-bottom); z-index: 1040; border-top-left-radius: 20px; border-top-right-radius: 20px; }
            .nav-item-bottom { display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: #adb5bd; font-size: 10px; font-weight: 500; width: 20%; transition: 0.2s; }
            .nav-item-bottom i { font-size: 22px; margin-bottom: 4px; transition: transform 0.2s; }
            .nav-item-bottom.active { color: #0d6efd; }
            .nav-item-bottom.active i { transform: translateY(-3px); }
            .nav-pills .nav-link { background-color: #f8f9fa; color: #495057; font-weight: 600; border: 1px solid #dee2e6; }
            .nav-pills .nav-link.active { color: #fff; background-color: #007bff; border-color: #007bff; }
            .loading-badge { display: none; }

            /* --- ESTILOS DA TIMELINE DE LOGS --- */
            .logs-timeline { border-left: 3px solid #e9ecef; margin-left: 10px; padding-left: 20px; position: relative; }
            .logs-item { position: relative; margin-bottom: 25px; }
            .logs-item:last-child { margin-bottom: 0; }
            .logs-dot {
                width: 16px; height: 16px; background: #fff; border: 3px solid #dee2e6;
                border-radius: 50%; position: absolute; left: -29px; top: 4px; z-index: 1;
            }
            .logs-item.completed .logs-dot { border-color: #198754; background: #198754; }
            .logs-item.pending .logs-dot { border-color: #adb5bd; }
            .logs-date { font-size: 0.85rem; color: #6c757d; font-weight: 500; }
            .logs-title { font-weight: 700; font-size: 1rem; margin-bottom: 2px; }
            .logs-status-text { font-size: 0.9rem; }
        </style>
    </head>
    <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    
        <div class="app-wrapper">
            
            <nav class="app-header navbar navbar-expand bg-body">
                <div class="container-fluid">
                    <ul class="navbar-nav">
                        <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list" style="font-size: 1.5rem;"></i></a></li>
                        <li class="nav-item d-lg-none ms-2"><span class="fw-bold fs-5">Viagens</span></li>
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
                            <li class="nav-item"><a href="ManageRides.php" class="nav-link active"><i class="nav-icon bi bi-box-seam-fill"></i><p>Viagens</p></a></li>
                            <li class="nav-item"><a href="manageUsers.php" class="nav-link"><i class="nav-icon bi bi-people-fill"></i><p>Funcionários</p></a></li>
                            <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link"><i class="nav-icon bi bi-graph-up"></i><p>Estatísticas</p></a></li>
                            <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-camera-fill"></i><p>No Shows</p></a></li>
                            <li class="nav-item"><a href="manageStorage.php" class="nav-link"><i class="nav-icon bi bi-archive-fill"></i><p>Armazenamento</p></a></li>
                        </ul>
                    </nav>
                </div>
            </aside>

            <div class="bottom-navbar">
                <a href="admin.php" class="nav-item-bottom"><i class="bi bi-house-door-fill"></i><span>Home</span></a>
                <a href="ManageRides.php" class="nav-item-bottom active"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
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
                            <div class="col-sm-6"><h3 class="mb-0">Gestão de Viagens</h3></div>
                            <div class="col-sm-6 d-none d-sm-block">
                                <ol class="breadcrumb float-sm-end"><li class="breadcrumb-item"><a href="admin.php">Home</a></li><li class="breadcrumb-item active">Viagens</li></ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="app-content">
                    <div class="container-fluid">
                        <div class="card shadow-sm border-0">
                            <div class="card-header-custom">
                                <ul class="nav nav-pills" id="ride-tabs">
                                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#today" data-status="today"><i class="bi bi-calendar-check-fill me-1"></i> Hoje</a></li>
                                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pending" data-status="pending"><i class="bi bi-clock-history me-1"></i> Pendentes</a></li>
                                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#assigned" data-status="assigned"><i class="bi bi-person-check-fill me-1"></i> Atribuídas</a></li>
                                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#all" data-status="all"><i class="bi bi-list-ul me-1"></i> Todas</a></li>
                                </ul>
                                
                                <div class="d-flex align-items-center gap-2 w-100-mobile mt-2 mt-lg-0 search-and-buttons">
                                    <div id="filter-container"></div>
                                    
                                    <span class="badge bg-secondary loading-badge me-2"><span class="spinner-border spinner-border-sm"></span> A carregar...</span>
                                    
                                    <div class="d-flex buttons-group">
                                        <div class="dropdown">
                                            <button class="btn btn-light border" type="button" data-bs-toggle="dropdown" title="Ordenar"><i class="bi bi-sort-down"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="#" onclick="sortRides(1, 'asc')">Data (Antiga)</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="sortRides(1, 'desc')">Data (Recente)</a></li>
                                            </ul>
                                        </div>
                                        <button class="btn btn-success btn-nova-viagem" data-bs-toggle="modal" data-bs-target="#modalCriarViagem">
                                            <i class="bi bi-plus-lg"></i> <span>Nova Viagem</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body p-0 p-md-3">
                                <div class="table-responsive">
                                    <table id="tabelaViagens" class="table table-borderless table-hover align-middle" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Data & Hora</th>
                                                <th>Condutor</th>
                                                <th>Recolha</th>
                                                <th>Entrega</th>
                                                <th>Tipo</th>
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

        <div class="modal fade" id="modalCriarViagem" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Nova Viagem</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <form action="addRide.php" method="POST">
                            <div class="row mb-3"><div class="col-6"><label>Data</label><input type="date" class="form-control" name="serviceDate" required /></div><div class="col-6"><label>Hora</label><input type="time" class="form-control" name="serviceStartTime" required /></div></div>
                            <div class="row mb-3"><div class="col-6"><label>ADT</label><input type="number" class="form-control" name="paxADT" required /></div><div class="col-6"><label>CHD</label><input type="number" class="form-control" name="paxCHD" required /></div></div>
                            <div class="mb-3"><label>Partida</label><input type="text" class="form-control" name="serviceStartPoint" required /></div>
                            <div class="mb-3"><label>Chegada</label><input type="text" class="form-control" name="serviceTargetPoint" required /></div>
                            <div class="row mb-3"><div class="col-6"><label>Condutor</label><select class="form-select" name="driver"><option value="later">Depois</option><?php foreach ($condutores as $c) echo "<option value='{$c['ID']}'>".htmlspecialchars($c['name'])."</option>"; ?></select></div><div class="col-6"><label>Tipo</label><select class="form-select" name="serviceType"><option value="1">Privado</option><option value="0">Partilhado</option></select></div></div>
                            <div class="row mb-3">
                                <div class="col-6"><label>Voo</label><input type="text" class="form-control" name="FlightNumber" /></div>
                                <div class="col-6"><label>Cliente</label><input type="text" class="form-control" name="NomeCliente" /></div>
                            </div>
                            <div class="mb-3">
                                <label>Número do Cliente</label>
                                <input type="text" class="form-control" name="ClientNumber" />
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Criar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="changeTripTypeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Tipo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form action="update_trip_type.php" method="POST">
                        <div class="modal-body">
                            <input type="hidden" id="tripId_changeType" name="tripId">
                            <div class="d-flex justify-content-around">
                                <div><input class="form-check-input" type="radio" name="tripType" id="private" value="1"><label class="form-check-label ms-2" for="private">Privado</label></div>
                                <div><input class="form-check-input" type="radio" name="tripType" id="shared" value="0"><label class="form-check-label ms-2" for="shared">Partilhado</label></div>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Guardar</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="atribuirCondutorModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Atribuir</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form action="atribuircondutor.php" method="POST">
                        <div class="modal-body">
                            <input type="hidden" id="viagemId_assign" name="viagemId">
                            <select name="condutorId" class="form-select text-center" style="font-size: 1.2rem;">
                                <?php foreach ($condutores as $c): ?><option value="<?= $c['ID'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Confirmar</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteTripModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body text-center p-4">
                        <h5 class="fw-bold text-danger mb-3">Apagar?</h5>
                        <p>Viagem <strong id="deleteTripName"></strong></p>
                        <div class="d-flex gap-2 mt-4">
                            <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Não</button>
                            <a href="#" id="confirmDeleteTripBtn" class="btn btn-danger flex-fill">Sim</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Detalhes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                    <form id="editTripForm" action="updateRide.php" method="POST">
                        <input type="hidden" name="edit_trip_id" id="editTripId">
                        <div class="row mb-2"><div class="col-6"><label>Data/Hora</label><input type="datetime-local" class="form-control" id="editDataHora" name="edit_departure_datetime" disabled></div><div class="col-6"><label>Condutor</label><input type="text" class="form-control" id="editCondutor" name="edit_driverName" disabled></div></div>
                        <div class="mb-2"><label>Recolha</label><input type="text" class="form-control" id="editRecolha" name="edit_origin" disabled></div>
                        <div class="mb-2"><label>Entrega</label><input type="text" class="form-control" id="editEntrega" name="edit_destination" disabled></div>
                        <div class="row mb-2"><div class="col-4"><label>ADT</label><input type="number" class="form-control" id="editpaxADT" name="edit_paxADT" disabled></div><div class="col-4"><label>CHD</label><input type="number" class="form-control" id="editpaxCHD" name="edit_paxCHD" disabled></div><div class="col-4"><label>Voo</label><input type="text" class="form-control" id="editflightNumber" name="edit_flightNumber" disabled></div></div>
                        <div class="row mb-2">
                            <div class="col-md-6"><label>Cliente</label><input type="text" class="form-control" id="editclientName" name="edit_clientName" disabled></div>
                            <div class="col-md-6"><label>Nº Cliente</label><input type="text" class="form-control" id="editclientNumber" name="edit_clientNumber" disabled></div>
                        </div>
                        
                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="flex-grow-1 me-2">
                                    <label class="form-label small text-muted mb-1">Tipo Atual:</label>
                                    <input type="text" class="form-control fw-bold text-center" id="editTripTypeDisplay" disabled style="background-color: #e9ecef;">
                                </div>
                                <div>
                                    <label class="form-label small text-muted mb-1 d-block">&nbsp;</label>
                                    <button type="button" id="btnChangeTypeEdit" class="btn btn-primary btn-sm" onclick="">
                                        <i class="bi bi-shuffle"></i> Mudar
                                    </button>
                                </div>
                            </div>
                        </div>

                    </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-warning w-100" id="enableEditBtn" onclick="enableEdit()">Editar Dados</button>
                        <button type="submit" class="btn btn-primary w-100" form="editTripForm" id="saveChangesBtn" style="display: none;">Guardar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalLogs" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Histórico de Estados</h5>
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
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>

        <script>
            let tabelaViagens;

            $(document).ready(function () {
                tabelaViagens = $('#tabelaViagens').DataTable({
                    "processing": true, 
                    "serverSide": false, 
                    "ajax": { "url": "load_rides_data.php?status=today", "type": "GET", "dataSrc": "data" },
                    "columns": [
                        { "data": "id" }, { "data": "data_hora" }, { "data": "condutor" }, 
                        { "data": "recolha" }, { "data": "entrega" }, { "data": "tipo" }, 
                        { 
                            "data": "acoes", 
                            "orderable": false,
                            "render": function (data, type, row) {
                                // INJEÇÃO AUTOMÁTICA DO BOTÃO DE LOGS
                                return `<div class="d-flex gap-1 justify-content-end align-items-center">
                                            ${data}
                                            <button class="btn btn-info btn-sm rounded-circle shadow-sm" onclick="viewTripLogs(${row.id})" title="Ver Logs" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;">
                                                <i class="bi bi-clock-history text-white"></i>
                                            </button>
                                        </div>`;
                            }
                        }
                    ],
                    "language": { 
                        "search": "Procurar:", 
                        "searchPlaceholder": "Procurar...", 
                        "lengthMenu": "", 
                        "info": "", 
                        "paginate": { "next": "→", "previous": "←" }, 
                        "zeroRecords": "Nada encontrado" 
                    },
                    "order": [[1, 'asc']], 
                    "pageLength": 10,
                    "dom": 'rt<"bottom"p><"clear">'
                });
                
                $('#tabelaViagens_filter').appendTo('#filter-container');
                
                tabelaViagens.on('preXhr.dt', function () { $('.loading-badge').show(); });
                tabelaViagens.on('xhr.dt', function () { 
                    $('.loading-badge').hide(); 
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                });

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
            
            function editTravel(id, dataHora, condutor, recolha, entrega, paxADT, paxCHD, flightNumber, clientName, clientNumber, serviceType) {
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

                const typeText = serviceType == 1 ? "Privado" : "Partilhado";
                document.getElementById('editTripTypeDisplay').value = typeText;

                const btnChange = document.getElementById('btnChangeTypeEdit');
                btnChange.onclick = function() {
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                    changeTripType(id, serviceType);
                };
            }

            function enableEdit() {
                document.querySelectorAll('#editTripForm input').forEach(input => { 
                    if(input.id !== 'editCondutor' && input.id !== 'editTripTypeDisplay') {
                        input.disabled = false;
                    }
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

            // FUNÇÃO PARA VER LOGS
            function viewTripLogs(id) {
                const modal = new bootstrap.Modal(document.getElementById('modalLogs'));
                const content = document.getElementById('logsContent');
                content.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></div>';
                modal.show();

                fetch(`get_ride_logs.php?id=${id}`)
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            const d = res.data;
                            const formatTime = (t) => t ? new Date(t).toLocaleString('pt-PT', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' }) : '<span class="text-muted fst-italic">Pendente</span>';
                            
                            const steps = [
                                { label: 'Iniciou Recolha', time: d.ts_start_pickup, icon: 'bi-car-front-fill' },
                                { label: 'Chegou ao Ponto', time: d.ts_arrived_pickup, icon: 'bi-geo-alt-fill' },
                                { label: 'Iniciou Viagem', time: d.ts_start_trip, icon: 'bi-play-fill' },
                                { label: 'Terminou Viagem', time: d.ts_completed, icon: 'bi-flag-fill' }
                            ];

                            let html = '<div class="logs-timeline">';
                            steps.forEach(step => {
                                const isDone = step.time !== null;
                                const statusClass = isDone ? 'completed' : 'pending';
                                
                                html += `
                                <div class="logs-item ${statusClass}">
                                    <div class="logs-dot"></div>
                                    <div class="logs-content ps-2">
                                        <div class="logs-title ${isDone ? 'text-dark' : 'text-muted'}">
                                            <i class="bi ${step.icon} me-1"></i> ${step.label}
                                        </div>
                                        <div class="logs-date">${formatTime(step.time)}</div>
                                    </div>
                                </div>`;
                            });
                            html += '</div>';
                            content.innerHTML = html;
                        } else {
                            content.innerHTML = '<p class="text-center text-danger">Erro ao carregar logs.</p>';
                        }
                    })
                    .catch(() => {
                        content.innerHTML = '<p class="text-center text-danger">Erro de conexão.</p>';
                    });
            }

            toastr.options = { "closeButton": true, "progressBar": true, "positionClass": "toast-top-right", "timeOut": "5000" };
            const success = "<?php echo isset($_GET['success']) ? $_GET['success'] : ''; ?>";
            if (success) {
                let message = '';
                if (success === "ride_created") message = "Viagem criada com sucesso!";
                if (success === "ViagemaApagada") message = "Viagem eliminada com sucesso!";
                if (success === "viagemAtribuida") message = "Viagem atribuída com sucesso!";
                if (success === "rideUpdated") message = "Viagem editada com sucesso!";
                if (success === "TypeChanged") message = "Tipo de viagem alterado com sucesso!";
                
                if (message) {
                    toastr.success(message, 'Sucesso!');
                    const url = new URL(window.location);
                    url.searchParams.delete('success');
                    window.history.replaceState({}, document.title, url.pathname);
                }
            }
        </script>
    </body>
</html>