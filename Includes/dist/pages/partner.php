<?php
session_start();
require __DIR__ . '/../../../auth/dbconfig.php';

// Verificação de Segurança
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 3) {
    header("Location: ../../../index.php");
    exit();
}

$partner_id = $_SESSION['user_id'];

// Lógica da Foto
$defaultPhoto = "../assets/img/user2-160x160.jpg"; 
$userPhoto = $defaultPhoto;
if (isset($_SESSION['profile_photo_path']) && !empty($_SESSION['profile_photo_path'])) {
    $userPhoto = "../../../" . $_SESSION['profile_photo_path'];
}

// Estatísticas
$stats = ['total' => 0, 'pendentes' => 0, 'confirmadas' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_pedido = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status_pedido = 'aprovado' THEN 1 ELSE 0 END) as confirmadas
        FROM Services WHERE partner_id = ?
    ");
    $stmt->execute([$partner_id]);
    $res = $stmt->fetch();
    if($res) $stats = $res;
} catch (PDOException $e) { }
?>

<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>Portal Parceiro | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        /* --- DESIGN SYSTEM --- */
        :root {
            --font-primary: 'Inter', sans-serif;
            --font-display: 'Poppins', sans-serif;
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --primary-accent: #4f46e5;
            --primary-hover: #4338ca;
            --border-color: #e5e7eb; /* Borda suave para Light Mode */
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
            --border-color: #334155; /* Borda ESCURA para Dark Mode (Corrige as linhas brancas) */
        }

        body {
            font-family: var(--font-primary);
            background-color: var(--bg-body);
            color: var(--text-main);
            padding-bottom: 90px; /* Espaço extra para o menu mobile */
        }

        /* --- HEADER --- */
        .app-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            height: 70px;
        }
        [data-bs-theme="dark"] .app-header { background: rgba(30, 41, 59, 0.95); }
        
        .navbar-brand img { height: 35px; margin-right: 10px; }
        .navbar-brand span { font-family: var(--font-display); font-weight: 700; letter-spacing: -0.5px; }

        /* --- STATS CARDS --- */
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 100%;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        .stat-value { font-family: var(--font-display); font-size: 1.6rem; font-weight: 700; margin-bottom: 0; line-height: 1.2; }
        .stat-label { font-size: 0.75rem; opacity: 0.7; font-weight: 500; }
        
        /* Cores Stats */
        .stat-blue { color: #3b82f6; } .stat-blue .stat-icon { color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .stat-orange { color: #f97316; } .stat-orange .stat-icon { color: #f97316; background: rgba(249, 115, 22, 0.1); }
        .stat-green { color: #10b981; } .stat-green .stat-icon { color: #10b981; background: rgba(16, 185, 129, 0.1); }
        
        .stat-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; margin-bottom: 8px; font-size: 1rem; }

        /* --- BOTÕES TIPO ABAS (CORRIGIDOS) --- */
        .nav-pills {
            gap: 10px; 
            overflow-x: auto; 
            padding-bottom: 5px; 
            flex-wrap: nowrap;
            -webkit-overflow-scrolling: touch;
        }
        .nav-pills::-webkit-scrollbar { display: none; }
        
        .nav-pills .nav-item { flex: 0 0 auto; }
        
        .nav-pills .nav-link {
            color: var(--text-muted);
            background: var(--bg-card);
            border: 1px solid var(--border-color); /* Borda subtil */
            border-radius: 50px; /* Redondos */
            padding: 8px 20px;
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
            transition: all 0.2s;
        }
        
        .nav-pills .nav-link.active {
            background-color: var(--primary-accent);
            color: #fff;
            border-color: var(--primary-accent);
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); /* Sombra suave no ativo */
        }

        /* --- TABELA E MOBILE CARDS (SUPER OTIMIZADO) --- */
        .card-table { background: transparent; border-radius: var(--radius-md); overflow: hidden; }
        
        /* Desktop */
        @media (min-width: 768px) {
            .card-table { background: var(--bg-card); border: 1px solid var(--border-color); }
            .table thead th {
                background-color: transparent;
                border-bottom: 2px solid var(--border-color);
                color: var(--text-muted);
                font-size: 0.8rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .table td { border-bottom: 1px solid var(--border-color); padding: 1rem; }
        }

        /* Mobile (Correção das linhas estranhas) */
        @media (max-width: 767.98px) {
            #tabelaPartner thead { display: none; }
            
            #tabelaPartner tbody tr {
                display: flex;
                flex-direction: column;
                background-color: var(--bg-card);
                border: 1px solid var(--border-color); /* Borda externa do card */
                border-radius: 16px;
                margin-bottom: 12px;
                padding: 16px;
                position: relative;
                box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            }

            #tabelaPartner tbody td {
                display: block;
                border: none !important; /* Remove as linhas internas chatas */
                padding: 2px 0;
                width: 100%;
            }

            /* Separador subtil apenas entre cabeçalho do card e corpo */
            #tabelaPartner tbody td:nth-child(2) {
                border-bottom: 1px solid var(--border-color) !important;
                padding-bottom: 10px;
                margin-bottom: 10px;
            }

            /* Estilos específicos Mobile */
            /* Data */
            #tabelaPartner tbody td:nth-child(1) { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 4px; }
            /* Nome */
            #tabelaPartner tbody td:nth-child(2) { font-size: 1.1rem; font-weight: 700; color: var(--text-main); }
            /* Ícones */
            #tabelaPartner tbody td:nth-child(3):before { content: "\F32D"; font-family: "bootstrap-icons"; margin-right: 8px; color: var(--primary-accent); }
            #tabelaPartner tbody td:nth-child(4):before { content: "\F3E8"; font-family: "bootstrap-icons"; margin-right: 8px; color: var(--text-muted); }
            #tabelaPartner tbody td:nth-child(5):before { content: "\F4D8"; font-family: "bootstrap-icons"; margin-right: 8px; color: var(--text-muted); }
            
            #tabelaPartner tbody td:nth-child(3), 
            #tabelaPartner tbody td:nth-child(4), 
            #tabelaPartner tbody td:nth-child(5) {
                font-size: 0.9rem; color: var(--text-main); display: flex; align-items: center; margin-bottom: 4px;
            }

            /* Badge Estado */
            #tabelaPartner tbody td:nth-child(6) {
                position: absolute; top: 16px; right: 16px; width: auto !important; padding: 0;
            }

            /* Ajuste da mensagem vazia */
            .dataTables_empty { text-align: center; padding: 20px; color: var(--text-muted); background: transparent !important; }
        }

        /* --- BOTÃO FLUTUANTE --- */
        .fab-main {
            position: fixed; bottom: 25px; left: 50%; transform: translateX(-50%);
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--primary-accent), var(--primary-hover));
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 2rem;
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.5); z-index: 1050; border: 4px solid var(--bg-body);
        }
        
        /* --- BOTTOM NAV --- */
        .bottom-navbar {
            background-color: var(--bg-card);
            border-top: 1px solid var(--border-color);
            z-index: 1040;
            height: 70px;
            padding-bottom: env(safe-area-inset-bottom);
        }
        .nav-item-bottom { color: var(--text-muted); font-size: 0.7rem; font-weight: 500; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; }
        .nav-item-bottom i { font-size: 1.5rem; margin-bottom: 2px; }
        .nav-item-bottom.active { color: var(--primary-accent); }
    </style>
</head>

<body class="layout-top-nav">
    <div class="app-wrapper">
        
        <nav class="app-header navbar navbar-expand fixed-top">
            <div class="container-fluid">
                <a href="#" class="navbar-brand d-flex align-items-center">
                    <img src="../../../assets/images/icons/Syncride.png" alt="Logo">
                    <span class="text-main">SyncRide</span>
                </a>

                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-2">
                        <button class="btn btn-link text-muted p-0 border-0" id="theme-toggle">
                            <i class="bi bi-moon-stars-fill fs-5" id="theme-icon"></i>
                        </button>
                    </li>
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                            <img src="<?php echo $userPhoto; ?>" class="rounded-circle shadow-sm border" style="width: 36px; height: 36px; object-fit: cover;">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 overflow-hidden mt-2">
                            <li class="user-header bg-primary text-white p-4 text-center">
                                <img src="<?php echo $userPhoto; ?>" class="rounded-circle shadow mb-2 border border-2 border-white" style="width: 70px; height: 70px; object-fit: cover;">
                                <p class="mb-0 fw-bold"><?php echo $_SESSION['name']; ?></p>
                                <small class="opacity-75">Parceiro</small>
                            </li>
                            <li class="user-footer p-3 bg-card">
                                <a href="logout.php" class="btn btn-danger btn-sm rounded-pill w-100 fw-bold">Sair</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="app-main pt-5 mt-4"> 
            <div class="app-content">
                <div class="container-xl">
                    
                    <div class="d-flex justify-content-between align-items-end mb-4 px-1">
                        <div>
                            <h3 class="fw-bold mb-0 text-main">Olá, <?php echo explode(' ', $_SESSION['name'])[0]; ?></h3>
                            <p class="text-muted mb-0 small">Bem-vindo ao portal.</p>
                        </div>
                        <button class="btn btn-primary rounded-pill d-none d-md-block shadow-sm px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalNovoPedido">
                            <i class="bi bi-plus-lg me-1"></i> Nova Reserva
                        </button>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-4">
                            <div class="stat-card stat-blue">
                                <div class="stat-icon"><i class="bi bi-folder2-open"></i></div>
                                <div class="stat-value"><?= $stats['total'] ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card stat-orange">
                                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                                <div class="stat-value"><?= $stats['pendentes'] ?></div>
                                <div class="stat-label">Pendentes</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card stat-green">
                                <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
                                <div class="stat-value"><?= $stats['confirmadas'] ?></div>
                                <div class="stat-label">Aceites</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <ul class="nav nav-pills" id="partner-tabs">
                            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#pendente" onclick="reloadTable('pendente')">Pendentes</a></li>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#aprovado" onclick="reloadTable('aprovado')">Confirmadas</a></li>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#rejeitado" onclick="reloadTable('rejeitado')">Rejeitadas</a></li>
                        </ul>
                    </div>

                    <div class="card-table">
                        <table id="tabelaPartner" class="table mb-0 w-100">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 15%;">Data</th>
                                    <th style="width: 20%;">Passageiro</th>
                                    <th style="width: 15%;">Voo</th>
                                    <th style="width: 25%;">Rota</th>
                                    <th style="width: 10%;">Pax</th>
                                    <th class="text-center" style="width: 15%;">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <button class="fab-main d-md-none" data-bs-toggle="modal" data-bs-target="#modalNovoPedido">
        <i class="bi bi-plus-lg"></i>
    </button>

    <div class="bottom-navbar position-fixed bottom-0 w-100 d-md-none d-flex justify-content-around align-items-center shadow-lg">
        <a href="#" class="nav-item-bottom active">
            <i class="bi bi-house-door-fill"></i><span>Home</span>
        </a>
        <div style="width: 50px;"></div> <a href="logout.php" class="nav-item-bottom">
            <i class="bi bi-person-fill"></i><span>Perfil</span>
        </a>
    </div>

    <div class="modal fade" id="modalNovoPedido" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-main ps-2">Nova Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formNovoPedido">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Data</label>
                                <input type="date" class="form-control bg-light border-0 py-2" name="date" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Hora</label>
                                <input type="time" class="form-control bg-light border-0 py-2" name="time" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Nome Passageiro</label>
                                <input type="text" class="form-control bg-light border-0 py-2" name="client_name" placeholder="Ex: João Silva" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Adultos</label>
                                <input type="number" class="form-control bg-light border-0 py-2" name="pax_adt" value="1" min="1" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Crianças</label>
                                <input type="number" class="form-control bg-light border-0 py-2" name="pax_chd" value="0" min="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Recolha (Pickup)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 text-success"><i class="bi bi-geo-alt-fill"></i></span>
                                    <input type="text" class="form-control bg-light border-0 py-2" name="pickup" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Destino (Dropoff)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 text-danger"><i class="bi bi-pin-map-fill"></i></span>
                                    <input type="text" class="form-control bg-light border-0 py-2" name="dropoff" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Nº Voo (Opcional)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-airplane-fill"></i></span>
                                    <input type="text" class="form-control bg-light border-0 py-2" name="flight" placeholder="Ex: TP123">
                                </div>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">Enviar Pedido</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- Dark Mode ---
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const htmlElement = document.documentElement;
        const savedTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-bs-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const newTheme = htmlElement.getAttribute('data-bs-theme') === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5';
        }

        // --- DataTables ---
        let table;
        let currentStatus = 'pendente';

        $(document).ready(function() {
            table = $('#tabelaPartner').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json",
                    "emptyTable": "Sem reservas neste estado",
                    "search": "", "searchPlaceholder": "Pesquisar passageiro..."
                },
                "ajax": {
                    "url": "api_fetch_partner_rides.php",
                    "data": function(d) { d.status = currentStatus; }
                },
                "columns": [
                    { data: 'data_hora', className: "ps-4" },
                    { data: 'cliente', className: "fw-bold" },
                    { data: 'voo' },
                    { data: 'rota' },
                    { data: 'pax' },
                    { data: 'status', className: "text-center" }
                ],
                "order": [[ 0, "desc" ]],
                "dom": '<"mb-3 px-2"f>rt<"d-flex justify-content-center mt-3"p>',
                "pageLength": 10,
                "autoWidth": false
            });

            // Submit Form
            $('#formNovoPedido').on('submit', function(e) {
                e.preventDefault();
                const btn = $(this).find('button[type="submit"]');
                const originalText = btn.html();
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> A enviar...');

                const formData = new FormData(this);

                fetch('api_create_ride_partner.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if(res.success) {
                        toastr.success('Reserva criada com sucesso!');
                        $('#modalNovoPedido').modal('hide');
                        $('#formNovoPedido')[0].reset();
                        table.ajax.reload();
                    } else {
                        toastr.error(res.message || 'Erro ao criar.');
                    }
                })
                .catch(() => toastr.error('Erro de conexão.'))
                .finally(() => btn.prop('disabled', false).html(originalText));
            });
        });

        function reloadTable(status) {
            currentStatus = status;
            table.ajax.reload();
        }
    </script>
</body>
</html>