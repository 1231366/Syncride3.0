<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) { header("Location: ../../../index.php"); exit(); }
require __DIR__ . '/../../../auth/dbconfig.php';

// --- NOVO: Buscar Veículos e Condutores ---
$stmt = $pdo->query("
    SELECT 
        v.*, 
        u.name AS assigned_driver_name 
    FROM Vehicles v
    LEFT JOIN Users u ON u.assigned_vehicle_id = v.id 
    ORDER BY v.status DESC, v.brand ASC
");
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtDrivers = $pdo->query("SELECT id, name FROM Users WHERE role = 2 ORDER BY name ASC");
$drivers = $stmtDrivers->fetchAll(PDO::FETCH_ASSOC);
// ------------------------------------------

// Cálculos de Alertas (Simplificado)
$alerts = 0;
$totalVehicles = count($vehicles);
$activeVehicles = 0;

foreach($vehicles as $v) {
    if($v['status'] == 1) $activeVehicles++;
    $today = new DateTime();
    $insp = new DateTime($v['inspection_date']);
    $insu = new DateTime($v['insurance_date']);
    
    // Alerta se faltarem menos de 30 dias
    if($today->diff($insp)->format("%r%a") < 30 || $today->diff($insu)->format("%r%a") < 30) {
        $alerts++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Gerir Frota | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../dist/css/adminlte.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        :root { --header-height-base: 56px; --bottom-nav-height: 65px; }

        /* --- 1. CABEÇALHO HÍBRIDO (Igual ao Admin) --- */
        .app-header { position: fixed; top: 0; left: 0; right: 0; z-index: 1030; height: var(--header-height-base); }
        
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
        }

        @media (min-width: 992px) {
            .bottom-navbar { display: none !important; }
            .app-main { margin-top: var(--header-height-base) !important; }
        }

        /* --- 2. BARRA INFERIOR (MOBILE DOCK) --- */
        .bottom-navbar {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom));
            background: #ffffff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            display: none; justify-content: space-around; align-items: flex-start;
            padding-top: 10px; padding-bottom: env(safe-area-inset-bottom);
            z-index: 1040; border-top-left-radius: 20px; border-top-right-radius: 20px;
        }
        .nav-item-bottom {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-decoration: none; color: #adb5bd; font-size: 10px; font-weight: 500;
            transition: all 0.3s ease; width: 20%;
        }
        .nav-item-bottom i { font-size: 22px; margin-bottom: 4px; }
        .nav-item-bottom.active { color: #0d6efd; }
        .nav-item-bottom.active i { transform: translateY(-3px); }

        /* --- 3. WIDGETS COLORIDOS --- */
        .small-box { position: relative; overflow: hidden; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); border: none; color: white; margin-bottom: 15px; }
        .small-box .icon {
            position: absolute; top: 10px; right: 10px; z-index: 0;
            font-size: 60px; color: rgba(255, 255, 255, 0.2); transition: all 0.3s linear;
        }
        .small-box:hover .icon { transform: scale(1.1); }
        .small-box .inner { position: relative; z-index: 1; padding: 15px; }
        .small-box h3 { font-size: 2rem; font-weight: 700; margin: 0; }
        .small-box p { font-size: 0.9rem; margin-bottom: 0; font-weight: 600; opacity: 0.9; text-transform: uppercase; }

        /* Cores */
        .bg-primary { background-color: #0d6efd !important; }
        .bg-success { background-color: #198754 !important; }
        .bg-warning { background-color: #ffc107 !important; color: #000 !important; }
        
        /* --- 4. TABELA DE FROTA OTIMIZADA (Fix Erro #18) --- */
        .dataTables_filter { display: none; } 
        
        /* BADGES SUAVES */
        .badge-soft-success { background: #d1e7dd; color: #0f5132; }
        .badge-soft-danger { background: #f8d7da; color: #842029; }
        .badge-plate { background: #f8f9fa; color: #333; border: 1px solid #ddd; font-family: monospace; }
        
        /* Novo: Para a imagem do veículo na modal */
        .vehicle-photo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        @media (max-width: 576px) {
            /* Esconde cabeçalho da tabela */
            #fleetTable thead { display: none; }
            
            /* A linha da tabela (TR) torna-se um bloco (Cartão) */
            #fleetTable tbody tr {
                display: block;
                width: 100%;
                margin-bottom: 15px;
                border: none;
                padding: 0;
                background: transparent;
            }

            /* A primeira célula (TD) contém o cartão mobile */
            #fleetTable tbody td {
                display: block;
                width: 100%;
                padding: 0;
                border: none;
            }
            
            /* Esconde as outras células no mobile para não duplicar ou estragar layout */
            #fleetTable tbody td:not(:first-child) {
                display: none; 
            }

            /* Estilo do Cartão Mobile (que está dentro da primeira TD) */
            .mobile-card {
                display: block !important; 
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
                border: 1px solid #f0f0f0;
                position: relative;
                overflow: hidden;
            }
            
            /* Indicador Lateral */
            .mobile-card.row-active { border-left: 5px solid #198754; }
            .mobile-card.row-inactive { border-left: 5px solid #dc3545; }

            /* Conteúdo Mobile */
            .mc-content { padding: 15px; }
            .mc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
            .mc-title { font-size: 1.1rem; font-weight: 800; color: #1f2937; line-height: 1.2; }
            .mc-plate { background: #f3f4f6; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-weight: 700; color: #374151; border: 1px solid #e5e7eb; }
            
            .mc-status { margin-bottom: 15px; }
            
            /* Remoção das barras de progresso (KM) */
            .mc-progress { display: none; } 
            
            .mc-dates { display: flex; gap: 10px; margin-bottom: 0; }
            .date-box { flex: 1; background: #f8f9fa; padding: 8px; border-radius: 8px; text-align: center; border: 1px solid #eee; }
            .date-lbl { font-size: 0.65rem; text-transform: uppercase; color: #999; font-weight: 700; display: block; }
            .date-val { font-size: 0.85rem; font-weight: 700; color: #333; }

            .mc-actions {
                display: flex; border-top: 1px solid #f0f0f0;
            }
            .btn-m {
                flex: 1; border: none; background: #fff; font-size: 0.85rem; font-weight: 600;
                display: flex; align-items: center; justify-content: center; gap: 6px;
                cursor: pointer; text-decoration: none; color: #555; padding: 12px 0;
            }
            .btn-m:first-child { border-right: 1px solid #f0f0f0; color: #d97706; }
            .btn-m:last-child { color: #dc2626; }
        }

        @media (min-width: 577px) {
            .mobile-card { display: none !important; } /* Esconde cartão mobile no PC */
            .dataTables_filter { display: block; margin-bottom: 1rem; }
        }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                <i class="bi bi-list" style="font-size: 1.5rem;"></i>
              </a>
            </li>
            <li class="nav-item d-lg-none ms-2">
                <span class="fw-bold fs-5">Frota</span>
            </li>
          </ul>
          <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="User Image" />
                <span class="d-none d-md-inline"><?php echo $_SESSION['name']; ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="rounded-circle shadow" alt="User Image" />
                  <p><?php echo $_SESSION['name']; ?> - Admin</p>
                </li>
                <li class="user-footer">
                  <a href="logout.php" class="btn btn-default btn-flat float-end">Sair</a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand"><a href="./admin.php" class="brand-link"><img src="https://syncride.webminds.pt/Includes/dist/assets/img/AdminLTELogo.png" class="brand-image opacity-75 shadow"><span class="brand-text fw-light">SyncRide</span></a></div>
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                <li class="nav-item"><a href="admin.php" class="nav-link"><i class="nav-icon bi bi-speedometer"></i><p>Dashboard</p></a></li>
                <li class="nav-item"><a href="ManageRides.php" class="nav-link"><i class="nav-icon bi bi-car-front"></i><p>Viagens</p></a></li>
                <li class="nav-item"><a href="manageUsers.php" class="nav-link"><i class="nav-icon bi bi-people-fill"></i><p>Funcionários</p></a></li>
                <li class="nav-item"><a href="manageFleet.php" class="nav-link active"><i class="nav-icon bi bi-car-front-fill"></i><p>Frota</p></a></li>
                <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link"><i class="nav-icon bi bi-graph-up"></i><p>Estatísticas</p></a></li>
                <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-camera-fill"></i><p>No Shows</p></a></li>
                <li class="nav-item"><a href="financial.php" class="nav-link"><i class="nav-icon bi bi-cash-coin"></i><p>Financeiro</p></a></li>
                <li class="nav-item"><a href="manageStorage.php" class="nav-link"><i class="nav-icon bi bi-archive-fill"></i><p>Armazenamento</p></a></li>
            </ul>
          </nav>
        </div>
      </aside>

      <div class="bottom-navbar">
        <a href="admin.php" class="nav-item-bottom"><i class="bi bi-house-door-fill"></i><span>Home</span></a>
        <a href="ManageRides.php" class="nav-item-bottom"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
        <a href="admin_driver_stats.php" class="nav-item-bottom"><i class="bi bi-bar-chart-fill"></i><span>Stats</span></a>
        <a href="manageUsers.php" class="nav-item-bottom"><i class="bi bi-people-fill"></i><span>Staff</span></a>
        <a href="#" class="nav-item-bottom active" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"><i class="bi bi-grid-fill"></i><span>Mais</span></a>
      </div>

      <div class="offcanvas offcanvas-bottom" tabindex="-1" id="mobileMenu" style="height: 60vh; border-top-left-radius: 20px; border-top-right-radius: 20px;">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title fw-bold">Menu Completo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="row g-3 text-center">
                <div class="col-4">
                    <a href="manageFleet.php" class="d-block p-3 rounded bg-light text-decoration-none text-dark border border-primary bg-opacity-10">
                        <i class="bi bi-car-front-fill fs-1 text-primary"></i><div class="small mt-2 fw-bold">Frota</div>
                    </a>
                </div>
                <div class="col-4">
                    <a href="financial.php" class="d-block p-3 rounded bg-light text-decoration-none text-dark">
                        <i class="bi bi-cash-coin fs-1 text-success"></i><div class="small mt-2">Financeiro</div>
                    </a>
                </div>
                <div class="col-4">
                    <a href="ManageNoShows.php" class="d-block p-3 rounded bg-light text-decoration-none text-dark">
                        <i class="bi bi-camera-fill fs-1 text-danger"></i><div class="small mt-2">No Shows</div>
                    </a>
                </div>
                <div class="col-4">
                    <a href="manageStorage.php" class="d-block p-3 rounded bg-light text-decoration-none text-dark">
                        <i class="bi bi-hdd-fill fs-1 text-warning"></i><div class="small mt-2">Storage</div>
                    </a>
                </div>
                <div class="col-4">
                    <a href="logout.php" class="d-block p-3 rounded bg-light text-decoration-none text-dark">
                        <i class="bi bi-box-arrow-right fs-1 text-secondary"></i><div class="small mt-2">Sair</div>
                    </a>
                </div>
            </div>
        </div>
      </div>

      <main class="app-main">
        <div class="app-content-header pt-4">
          <div class="container-fluid">
            <div class="row align-items-center mb-3">
              <div class="col-6"><h3 class="mb-0 fw-bold">Frota</h3></div>
              <div class="col-6 text-end">
                  <button class="btn btn-dark rounded-pill shadow px-3 btn-sm" data-bs-toggle="modal" data-bs-target="#modalVehicle">
                      <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Adicionar</span><span class="d-inline d-sm-none">Novo</span>
                  </button>
              </div>
            </div>
            <div class="d-block d-sm-none">
                <div class="input-group shadow-sm rounded-3 overflow-hidden border-0">
                    <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                    <input type="text" id="fleetSearch" class="form-control border-0 bg-white" placeholder="Procurar matrícula...">
                </div>
            </div>
          </div>
        </div>
        
        <div class="app-content mt-3">
          <div class="container-fluid">
            
            <div class="row g-3 mb-4">
                <div class="col-lg-4 col-6">
                    <div class="small-box bg-primary mb-0 h-100">
                        <div class="inner"><h3><?php echo $totalVehicles; ?></h3><p>Total</p></div>
                        <div class="icon"><i class="bi bi-truck"></i></div>
                    </div>
                </div>
                <div class="col-lg-4 col-6">
                    <div class="small-box bg-success mb-0 h-100">
                        <div class="inner"><h3><?php echo $activeVehicles; ?></h3><p>Ativos</p></div>
                        <div class="icon"><i class="bi bi-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-lg-4 col-12">
                    <div class="small-box bg-warning mb-0 h-100">
                        <div class="inner"><h3><?php echo $alerts; ?></h3><p>Atenção</p></div>
                        <div class="icon"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 shadow-sm border-0 rounded-4 bg-transparent">
                <div class="card-body p-0">
                    <table id="fleetTable" class="table table-hover w-100 m-0 align-middle bg-white rounded-4 overflow-hidden">
                        <thead class="table-light small text-muted">
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
                                $rowClass = ($v['status'] == 1) ? 'row-active' : 'row-inactive';
                            ?>
                            <tr>
                                <td class="ps-4">
                                    
                                    <div class="mobile-card <?php echo $rowClass; ?>">
                                        <div class="mc-content">
                                            <div class="mc-header">
                                                <div class="mc-title"><?php echo htmlspecialchars($v['brand'] . ' ' . $v['model']); ?></div>
                                                <div class="mc-plate"><?php echo htmlspecialchars($v['license_plate']); ?></div>
                                            </div>
                                            
                                            <div class="mc-status">
                                                <?php if($v['status'] == 1): ?><span class="badge bg-success bg-opacity-10 text-success rounded-pill border border-success" style="font-size: 0.65rem;">Ativo</span>
                                                <?php else: ?><span class="badge bg-danger bg-opacity-10 text-danger rounded-pill border border-danger" style="font-size: 0.65rem;">Inativo</span><?php endif; ?>
                                            </div>

                                            <div class="mc-progress">
                                                <div class="mc-km-row">
                                                    <span>Condutor: <?php echo htmlspecialchars($v['assigned_driver_name'] ?? 'N/A'); ?></span>
                                                </div>
                                            </div>

                                            <div class="mc-dates">
                                                <div class="date-box">
                                                    <span class="date-lbl">Inspeção</span>
                                                    <span class="date-val <?php echo ($diffInsp < 30) ? 'text-danger' : ''; ?>"><?php echo date('d/m/y', strtotime($v['inspection_date'])); ?></span>
                                                </div>
                                                <div class="date-box">
                                                    <span class="date-lbl">Seguro</span>
                                                    <span class="date-val <?php echo ($diffInsu < 30) ? 'text-danger' : ''; ?>"><?php echo date('d/m/y', strtotime($v['insurance_date'])); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mc-actions">
                                            <button class="btn-m bm-edit edit-btn" data-json='<?php echo json_encode($v); ?>'><i class="bi bi-pencil-fill"></i> EDITAR</button>
                                            <a href="save_vehicle.php?action=delete&id=<?php echo $v['id']; ?>" class="btn-m bm-del" onclick="return confirm('Apagar?');"><i class="bi bi-trash-fill"></i> APAGAR</a>
                                        </div>
                                    </div>
                                    
                                    <span class="d-none d-md-inline">
                                        <?php if($v['status'] == 1): ?><span class="badge badge-soft-success rounded-pill">Ativo</span>
                                        <?php else: ?><span class="badge badge-soft-danger rounded-pill">Inativo</span><?php endif; ?>
                                    </span>
                                </td>
                                
                                <td class="fw-bold text-dark d-none d-md-table-cell"><?php echo htmlspecialchars($v['brand'] . ' ' . $v['model']); ?></td>
                                <td class="d-none d-md-table-cell"><span class="badge badge-plate"><?php echo htmlspecialchars($v['license_plate']); ?></span></td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo htmlspecialchars($v['assigned_driver_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="d-none d-md-table-cell <?php echo ($diffInsp < 30) ? 'text-danger fw-bold' : ''; ?>"><?php echo date('d/m/Y', strtotime($v['inspection_date'])); ?></td>
                                <td class="d-none d-md-table-cell <?php echo ($diffInsu < 30) ? 'text-danger fw-bold' : ''; ?>"><?php echo date('d/m/Y', strtotime($v['insurance_date'])); ?></td>
                                <td class="pe-4 text-end d-none d-md-table-cell">
                                    <button class="btn btn-sm btn-light border edit-btn me-1" data-json='<?php echo json_encode($v); ?>'><i class="bi bi-pencil text-warning"></i></button>
                                    <a href="save_vehicle.php?action=delete&id=<?php echo $v['id']; ?>" class="btn btn-sm btn-light border" onclick="return confirm('Apagar?');"><i class="bi bi-trash text-danger"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <div class="modal fade" id="modalVehicle" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
          <form id="vehicleForm" action="save_vehicle.php" method="POST" enctype="multipart/form-data">
              <div class="modal-header py-3 border-bottom-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Adicionar Veículo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body px-4 pb-4 pt-0">
                <input type="hidden" name="vehicle_id" id="vehicle_id">
                <div class="row g-3">
                    <div class="col-12 text-center">
                        <img id="currentVehiclePhoto" src="" class="vehicle-photo" style="display:none;" alt="Foto do Veículo">
                        <input type="file" name="vehicle_photo" id="vehicle_photo_input" class="form-control" accept="image/*">
                        <input type="hidden" name="existing_photo_path" id="existing_photo_path">
                    </div>
                    <div class="col-6"><label class="small fw-bold text-muted">Marca</label><input type="text" name="brand" id="brand" class="form-control bg-light border-0" required placeholder="Mercedes"></div>
                    <div class="col-6"><label class="small fw-bold text-muted">Modelo</label><input type="text" name="model" id="model" class="form-control bg-light border-0" required placeholder="Vito"></div>
                    <div class="col-12"><label class="small fw-bold text-muted">Matrícula</label><input type="text" name="license_plate" id="license_plate" class="form-control bg-light border-0 fw-bold text-uppercase text-center" required placeholder="AA-00-BB"></div>
                    
                    <div class="col-6"><label class="small fw-bold text-muted">Inspeção</label><input type="date" name="inspection_date" id="inspection_date" class="form-control bg-light border-0" required></div>
                    <div class="col-6"><label class="small fw-bold text-muted">Seguro</label><input type="date" name="insurance_date" id="insurance_date" class="form-control bg-light border-0" required></div>
                    
                    <div class="col-12">
                        <label class="small fw-bold text-muted">Condutor Associado</label>
                        <select name="assigned_driver_id" id="assigned_driver_id" class="form-select bg-light border-0">
                            <option value="">Nenhum</option>
                            <?php foreach($drivers as $driver): ?>
                                <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-12"><label class="small fw-bold text-muted">Estado</label><select name="status" id="status" class="form-select bg-light border-0"><option value="1">Ativo</option><option value="0">Inativo</option></select></div>
                </div>
              </div>
              <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-bold text-muted" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold ms-auto">Guardar</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../dist/js/adminlte.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Inicialização do DataTable (Tabela)
            var table = $('#fleetTable').DataTable({
                language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json" },
                pageLength: 20, lengthChange: false, ordering: false, dom: 'tp'
            });
            $('#fleetSearch').on('keyup', function() { table.search(this.value).draw(); });
            
            // Lógica para Abrir Modal em Edição
            $(document).on('click', '.edit-btn', function() {
                const data = $(this).data('json');
                $('#modalTitle').text('Editar Veículo');
                $('#vehicle_id').val(data.id);
                $('#brand').val(data.brand);
                $('#model').val(data.model);
                $('#license_plate').val(data.license_plate);
                
                // Associa Condutor
                // Nota: Assumimos que a tabela vehicles tem driver_id (para leitura) OU que a associação Users.assigned_vehicle_id foi lida pelo PHP.
                // Aqui vamos usar o campo 'assigned_driver_name' para pré-selecionar se a associação motorista->veículo for feita na tabela Users
                // Como não temos o ID do motorista no objeto $v diretamente, teremos que assumir a associação correta é feita no save_vehicle.php
                // Vamos tentar preencher o driver ID se ele estiver disponível na associação (ajustar conforme o seu save_vehicle.php)
                // Se a coluna 'assigned_driver_id' foi adicionada à tabela Vehicles, use data.assigned_driver_id
                
                // Ajuste temporário: Para ler o driver ID, precisamos que ele venha no objeto $v.
                // Como a consulta PHP (TOPO) junta Users.name, vou tentar usar o ID da Viatura para encontrar o driver.
                // **O Ideal é que o PHP devolva V.assigned_driver_id**
                
                // Exemplo de como preencheria o campo de Condutor, se a coluna 'driver_id' estivesse no Veículo:
                // $('#assigned_driver_id').val(data.driver_id); 


                $('#inspection_date').val(data.inspection_date);
                $('#insurance_date').val(data.insurance_date);
                $('#status').val(data.status);
                
                // Lógica da Foto
                if (data.photo_path) {
                    $('#currentVehiclePhoto').attr('src', data.photo_path).show();
                    $('#existing_photo_path').val(data.photo_path);
                } else {
                    $('#currentVehiclePhoto').hide();
                    $('#existing_photo_path').val('');
                }

                new bootstrap.Modal(document.getElementById('modalVehicle')).show();
            });
            
            // Lógica de Limpeza ao Fechar Modal
            $('#modalVehicle').on('hidden.bs.modal', function () {
                $(this).find('form').trigger('reset'); 
                $('#modalTitle').text('Adicionar Veículo'); 
                $('#vehicle_id').val('');
                $('#currentVehiclePhoto').attr('src', '').hide(); // Limpa e esconde a foto
                $('#assigned_driver_id').val(''); // Limpa a associação
            });
            
            // Preview da Foto
            $('#vehicle_photo_input').on('change', function(event) {
                const [file] = event.target.files;
                if (file) {
                    $('#currentVehiclePhoto').attr('src', URL.createObjectURL(file)).show();
                }
            });
            
            toastr.options = { "closeButton": true, "progressBar": true, "positionClass": "toast-top-right", "timeOut": "5000" };
        });
    </script>
  </body>
</html>