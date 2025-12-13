<?php
session_start();
// Verificar login Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../../../index.php");
    exit();
}
require __DIR__ . '/../../../auth/dbconfig.php';

// Filtro de Mês (Default: Mês Atual)
$mesFiltro = $_GET['month'] ?? date('Y-m');
$ano = date('Y', strtotime($mesFiltro));
$mes = date('m', strtotime($mesFiltro));

// 1. DADOS FINANCEIROS
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE YEAR(serviceDate) = ? AND MONTH(serviceDate) = ?");
$stmt->execute([$ano, $mes]);
$totalViagens = $stmt->fetchColumn();
$receitaEstimada = $totalViagens * 15; 

$stmt = $pdo->prepare("SELECT SUM(amount) FROM Expenses WHERE YEAR(date) = ? AND MONTH(date) = ?");
$stmt->execute([$ano, $mes]);
$totalDespesas = $stmt->fetchColumn() ?: 0;

$lucroLiquido = $receitaEstimada - $totalDespesas;

// 2. DADOS PARA O GRÁFICO
$stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM Expenses WHERE YEAR(date) = ? AND MONTH(date) = ? GROUP BY category");
$stmt->execute([$ano, $mes]);
$chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$catLabels = [];
$catValues = [];
foreach($chartData as $d) {
    $catLabels[] = $d['category'];
    $catValues[] = (float)$d['total'];
}

// 3. LISTA DE DESPESAS
$stmt = $pdo->prepare("SELECT * FROM Expenses WHERE YEAR(date) = ? AND MONTH(date) = ? ORDER BY date DESC");
$stmt->execute([$ano, $mes]);
$listaDespesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Financeiro | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../dist/css/adminlte.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        /* Small Boxes */
        .small-box { border-radius: 12px; position: relative; display: block; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.2s ease; border: none; }
        .small-box:active { transform: scale(0.98); }
        .small-box .inner { padding: 15px 20px; }
        .small-box h3 { font-size: 1.6rem; font-weight: 800; margin: 0; white-space: nowrap; }
        .small-box p { font-size: 0.85rem; margin: 0; opacity: 0.9; font-weight: 500; }
        .small-box .icon { position: absolute; top: 12px; right: 15px; z-index: 0; font-size: 2.5rem; opacity: 0.2; }
        
        .bg-info { background: linear-gradient(135deg, #17a2b8, #117a8b) !important; color: #fff !important; }
        .bg-success { background: linear-gradient(135deg, #28a745, #1e7e34) !important; color: #fff !important; }
        .bg-danger { background: linear-gradient(135deg, #dc3545, #bd2130) !important; color: #fff !important; }

        /* Preview Modal */
        #previewImage { max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        #previewFrame { width: 100%; height: 500px; border: none; border-radius: 8px; }

        /* --- MOBILE CARD DESIGN (V4 - Soft UI & Modern) --- */
        .mobile-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            margin-bottom: 16px;
            overflow: hidden;
            border: 1px solid #f0f0f0;
            position: relative;
        }
        
        /* Indicador lateral subtil */
        .mobile-card::before {
            content: ''; position: absolute; left: 0; top: 12px; bottom: 12px; width: 4px;
            background: #dc3545; border-radius: 0 4px 4px 0;
        }

        .mobile-card-content {
            padding: 15px 15px 15px 20px; /* Padding extra à esquerda para o indicador */
        }

        /* Topo: Data e Valor */
        .mc-top {
            display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;
        }
        .mc-date { font-size: 0.8rem; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .mc-amount { font-size: 1.25rem; font-weight: 800; color: #2c3e50; line-height: 1; }
        
        /* Meio: Descrição Principal */
        .mc-main { margin-bottom: 12px; }
        .mc-cat-badge { 
            display: inline-block; font-size: 0.7rem; font-weight: 700; 
            padding: 3px 8px; border-radius: 6px; background: #f3f4f6; color: #555; 
            margin-bottom: 4px; text-transform: uppercase;
        }
        .mc-desc { font-size: 1rem; color: #333; font-weight: 500; line-height: 1.4; }

        /* Footer: Botões "Soft" (Separados e Arredondados) */
        .mc-footer {
            padding: 0 15px 15px 15px;
            display: flex;
            gap: 10px; /* Espaço entre botões */
        }
        
        .btn-soft {
            flex: 1;
            border: none;
            border-radius: 10px;
            padding: 10px 0;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-soft:active { transform: scale(0.96); }

        /* Cores Soft (Fundo pastel + Texto vivo) */
        .btn-soft-primary { background: #eef2ff; color: #4f46e5; } /* Azul suave */
        .btn-soft-warning { background: #fffbeb; color: #b45309; } /* Amarelo suave */
        .btn-soft-danger  { background: #fef2f2; color: #dc2626; } /* Vermelho suave */
        .btn-soft-disabled { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; }

    </style>
  </head>
  
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  
  <style>.notificacao { position: fixed; top: 15px; right: 15px; background-color: #10b981; color: #fff; padding: 12px 20px; border-radius: 12px; z-index: 9999; font-weight: 600; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); animation: slideIn 0.3s ease-out; } @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }</style>
  <script>
    const success = "<?php echo isset($_GET['success']) ? $_GET['success'] : ''; ?>";
    if (success) {
        const n = document.createElement("div"); n.className="notificacao"; 
        n.innerHTML = success === "deleted" ? "<i class='bi bi-trash3-fill me-2'></i> Apagado!" : "<i class='bi bi-check-circle-fill me-2'></i> Guardado!";
        document.body.appendChild(n); setTimeout(()=>n.remove(), 3000);
    }
  </script>

    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand bg-white shadow-sm border-bottom-0">
        <div class="container-fluid">
          <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list fs-4"></i></a></li></ul>
          <ul class="navbar-nav ms-auto"><li class="nav-item"><span class="nav-link fw-bold text-dark"><?php echo $_SESSION['name']; ?></span></li></ul>
        </div>
      </nav>
      
      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand"><a href="./admin.php" class="brand-link"><img src="../../dist/assets/img/AdminLTELogo.png" class="brand-image opacity-75 shadow"><span class="brand-text fw-light">SyncRide</span></a></div>
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                <li class="nav-item"><a href="admin.php" class="nav-link"><i class="nav-icon bi bi-speedometer"></i><p>Dashboard</p></a></li>
                <li class="nav-item"><a href="ManageRides.php" class="nav-link"><i class="nav-icon bi bi-box-seam-fill"></i><p>Gerir Viagens</p></a></li>
                <li class="nav-item"><a href="manageUsers.php" class="nav-link"><i class="nav-icon bi bi-people-fill"></i><p>Gerir Funcionários</p></a></li>
                <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link"><i class="nav-icon bi bi-graph-up"></i><p>Estatísticas</p></a></li>
                <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-camera-fill"></i><p>Gerir No Shows</p></a></li>
                <li class="nav-item"><a href="manageStorage.php" class="nav-link"><i class="nav-icon bi bi-box-seam-fill"></i><p>Gerir Armazenamento</p></a></li>
                <li class="nav-item"><a href="financial.php" class="nav-link active"><i class="nav-icon bi bi-cash-coin"></i><p>Financeiro</p></a></li>
            </ul>
          </nav>
        </div>
      </aside>

      <main class="app-main pb-5"> <div class="app-content-header pt-4">
          <div class="container-fluid">
            <div class="row align-items-center mb-3">
              <div class="col-6"><h4 class="mb-0 fw-bold text-dark">Financeiro</h4></div>
              <div class="col-6 text-end">
                  <button class="btn btn-dark shadow rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalExpense">
                      <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Nova Despesa</span><span class="d-inline d-sm-none">Nova</span>
                  </button>
              </div>
            </div>
            <form method="GET" class="d-inline-block w-100">
                <div class="input-group shadow-sm rounded-3 overflow-hidden border-0">
                    <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-calendar3"></i></span>
                    <input type="month" name="month" class="form-control border-0 fw-bold" style="height: 45px;" value="<?php echo $mesFiltro; ?>" onchange="this.form.submit()">
                </div>
            </form>
          </div>
        </div>
        
        <div class="app-content mt-3">
          <div class="container-fluid">
            
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4">
                    <div class="small-box bg-success mb-0 h-100">
                        <div class="inner">
                            <h3><?php echo number_format($receitaEstimada, 0, ',', '.'); ?>€</h3>
                            <p>Receita</p>
                        </div>
                        <div class="icon"><i class="bi bi-graph-up-arrow"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="small-box bg-danger mb-0 h-100">
                        <div class="inner">
                            <h3><?php echo number_format($totalDespesas, 0, ',', '.'); ?>€</h3>
                            <p>Despesas</p>
                        </div>
                        <div class="icon"><i class="bi bi-cart-dash"></i></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="small-box bg-info mb-0 h-100">
                        <div class="inner d-flex justify-content-between align-items-center h-100">
                            <div>
                                <h3><?php echo number_format($lucroLiquido, 0, ',', '.'); ?>€</h3>
                                <p>Lucro Líquido</p>
                            </div>
                            <i class="bi bi-wallet2 fs-1 opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mb-4">
                    
                    <div class="card shadow-sm border-0 d-none d-md-block rounded-4">
                        <div class="card-body p-0">
                            <table id="tabelaDespesas" class="table table-hover w-100 m-0 align-middle">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th class="ps-4 py-3">Data</th>
                                        <th>Categ.</th>
                                        <th>Descrição</th>
                                        <th class="text-end">Valor</th>
                                        <th class="text-center pe-4">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($listaDespesas as $d): ?>
                                    <tr>
                                        <td class="ps-4 py-3 fw-bold text-secondary"><?php echo date('d/m/Y', strtotime($d['date'])); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($d['category']); ?></span></td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($d['description']); ?></td>
                                        <td class="text-end text-danger fw-bolder">-<?php echo number_format($d['amount'], 2, ',', '.'); ?>€</td>
                                        <td class="text-center pe-4">
                                            <?php if($d['file_path']): $ext=strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION)); ?>
                                            <button class="btn btn-sm btn-light border me-1" onclick="openPreview('<?php echo htmlspecialchars($d['file_path']); ?>','<?php echo $ext; ?>')"><i class="bi bi-eye text-primary"></i></button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-light border edit-btn me-1" data-id="<?php echo $d['id']; ?>" data-cat="<?php echo $d['category']; ?>" data-desc="<?php echo $d['description']; ?>" data-amount="<?php echo $d['amount']; ?>" data-date="<?php echo $d['date']; ?>"><i class="bi bi-pencil text-warning"></i></button>
                                            <a href="save_expense.php?action=delete&id=<?php echo $d['id']; ?>" class="btn btn-sm btn-light border" onclick="return confirm('Apagar?');"><i class="bi bi-trash text-danger"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-block d-md-none">
                        <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                            <h6 class="text-muted small fw-bold uppercase m-0">Movimentos Recentes</h6>
                            <span class="badge bg-light text-muted border"><?php echo count($listaDespesas); ?> registos</span>
                        </div>
                        
                        <?php if(empty($listaDespesas)): ?>
                            <div class="text-center text-muted py-5 bg-white rounded-4 shadow-sm">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                Sem registos este mês.
                            </div>
                        <?php endif; ?>

                        <?php foreach($listaDespesas as $d): ?>
                        <div class="mobile-card">
                            <div class="mobile-card-content">
                                <div class="mc-top">
                                    <div class="mc-date"><?php echo date('d M Y', strtotime($d['date'])); ?></div>
                                    <div class="mc-amount text-danger">-<?php echo number_format($d['amount'], 2, ',', '.'); ?>€</div>
                                </div>
                                <div class="mc-main">
                                    <span class="mc-cat-badge"><?php echo htmlspecialchars($d['category']); ?></span>
                                    <div class="mc-desc"><?php echo htmlspecialchars($d['description']); ?></div>
                                </div>
                            </div>
                            
                            <div class="mc-footer">
                                <?php if($d['file_path']): $ext = strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION)); ?>
                                    <button class="btn-soft btn-soft-primary" onclick="openPreview('<?php echo htmlspecialchars($d['file_path']); ?>', '<?php echo $ext; ?>')">
                                        <i class="bi bi-eye-fill"></i> Ver
                                    </button>
                                <?php else: ?>
                                    <button class="btn-soft btn-soft-disabled" disabled><i class="bi bi-eye-slash"></i> Ver</button>
                                <?php endif; ?>

                                <button class="btn-soft btn-soft-warning edit-btn" 
                                        data-id="<?php echo $d['id']; ?>"
                                        data-cat="<?php echo $d['category']; ?>"
                                        data-desc="<?php echo $d['description']; ?>"
                                        data-amount="<?php echo $d['amount']; ?>"
                                        data-date="<?php echo $d['date']; ?>">
                                    <i class="bi bi-pencil-fill"></i> Editar
                                </button>

                                <a href="save_expense.php?action=delete&id=<?php echo $d['id']; ?>" 
                                   class="btn-soft btn-soft-danger"
                                   onclick="return confirm('Apagar registo?');">
                                    <i class="bi bi-trash3-fill"></i> Apagar
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-body p-3">
                            <h6 class="text-center text-muted mb-0 small uppercase fw-bold">Distribuição de Gastos</h6>
                            <div id="expenses-chart"></div>
                        </div>
                    </div>
                </div>
            </div>

          </div>
        </div>
      </main>
    </div>

    <div class="modal fade" id="previewModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
          <div class="modal-header py-3 bg-dark text-white border-0">
            <h6 class="modal-title small fw-bold"><i class="bi bi-file-earmark-image me-2"></i>Comprovativo</h6>
            <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-0 text-center bg-light position-relative">
            <img src="" id="previewImage" class="d-none img-fluid w-100" style="max-height: 70vh; object-fit: contain;">
            <iframe src="" id="previewFrame" class="d-none w-100" style="height:50vh"></iframe>
          </div>
          <div class="modal-footer py-2 bg-white border-0 justify-content-center">
            <a href="#" id="downloadBtn" class="btn btn-dark rounded-pill px-4 w-100" target="_blank" download>
                <i class="bi bi-download me-2"></i> Baixar Original
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalExpense" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
          <form action="save_expense.php" method="POST" enctype="multipart/form-data">
              <div class="modal-header py-3 border-bottom-0">
                <h5 class="modal-title fw-bold text-dark" id="modalTitle">Nova Despesa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body px-4 pb-4 pt-0">
                <input type="hidden" name="expense_id" id="expense_id">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="small fw-bold text-muted mb-1">Data</label>
                        <input type="date" name="date" id="date" class="form-control bg-light border-0" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold text-muted mb-1">Valor (€)</label>
                        <input type="number" step="0.01" name="amount" id="amount" class="form-control bg-light border-0 fw-bold" placeholder="0.00" required>
                    </div>
                    <div class="col-12">
                        <label class="small fw-bold text-muted mb-1">Categoria</label>
                        <select name="category" id="category" class="form-select bg-light border-0" required>
                            <option value="Combustível">⛽ Combustível</option>
                            <option value="Manutenção">🔧 Manutenção</option>
                            <option value="Pessoal">👔 Pessoal</option>
                            <option value="Portagens">🛣️ Portagens</option>
                            <option value="Outros">📝 Outros</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="small fw-bold text-muted mb-1">Descrição</label>
                        <input type="text" name="description" id="description" class="form-control bg-light border-0" placeholder="Ex: Gasóleo Carrinha 1" required>
                    </div>
                    <div class="col-12">
                        <label class="small fw-bold text-muted mb-1">Comprovativo (Opcional)</label>
                        <input type="file" name="proof" class="form-control bg-light border-0" accept="image/*,application/pdf" capture="environment">
                    </div>
                </div>
              </div>
              <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4 text-muted fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark rounded-pill px-5 fw-bold ms-auto">Guardar</button>
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
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"></script>

    <script>
      $(document).ready(function() {
            // DataTables (Só ativa no Desktop)
            if ($(window).width() >= 768) {
                $('#tabelaDespesas').DataTable({
                    "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json" },
                    "pageLength": 10, "lengthChange": false, "searching": true, "ordering": false, "dom": 'tp'
                });
            }

            // Gráfico Donut
            const vals = <?php echo json_encode($catValues); ?>;
            const labs = <?php echo json_encode($catLabels); ?>;
            if(vals.length > 0) {
                new ApexCharts(document.querySelector("#expenses-chart"), {
                    series: vals, labels: labs, chart: { type: 'donut', height: 250 },
                    colors: ['#dc3545', '#007bff', '#ffc107', '#28a745', '#6c757d'],
                    legend: { position: 'bottom' }, dataLabels: { enabled: false },
                    plotOptions: { pie: { donut: { size: '65%' } } }
                }).render();
            } else {
                document.querySelector("#expenses-chart").innerHTML = "<div class='text-center small py-5 text-muted'>Sem dados</div>";
            }

            // Modal Editar
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
      });

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