<?php
require_once 'db.php';
session_start();

date_default_timezone_set('Asia/Colombo');

// Fetch filter data
try {
    $companies = $pdo->query("SELECT company_id, company_name FROM companies WHERE status = 'active' ORDER BY company_name ASC")->fetchAll();
    $branches = $pdo->query("SELECT branch_id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name ASC")->fetchAll();
    $floors = $pdo->query("SELECT floor_id, floor_name FROM floors ORDER BY floor_name ASC")->fetchAll();
    $suppliers = $pdo->query("SELECT supplier_id, supplier_name FROM suppliers WHERE status = 'active' OR status IS NULL ORDER BY supplier_name ASC")->fetchAll();
    $statuses = ['pending', 'process', 'complete', 'received'];
} catch (Exception $e) {
    die("<div style='color:#ef4444; background:#0b0f19; padding:24px; font-weight:bold; font-family:sans-serif; text-align:center;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Handle search
$searchResults = [];
$searchPerformed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['search'])) {
    $searchPerformed = true;
    
    try {
        $company_id = isset($_POST['company_id']) && $_POST['company_id'] !== '' ? intval($_POST['company_id']) : null;
        $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? intval($_POST['branch_id']) : null;
        $floor_id = isset($_POST['floor_id']) && $_POST['floor_id'] !== '' ? intval($_POST['floor_id']) : null;
        $supplier_id = isset($_POST['supplier_id']) && $_POST['supplier_id'] !== '' ? intval($_POST['supplier_id']) : null;
        $status = isset($_POST['status']) && $_POST['status'] !== '' ? $_POST['status'] : null;
        $search_term = isset($_POST['search_term']) ? trim($_POST['search_term']) : '';
        $date_from = isset($_POST['date_from']) && $_POST['date_from'] !== '' ? $_POST['date_from'] : null;
        $date_to = isset($_POST['date_to']) && $_POST['date_to'] !== '' ? $_POST['date_to'] : null;
        
        $sql = "
            SELECT 
                t.token_id,
                t.invoice_no,
                t.order_no,
                t.priority_no,
                t.status,
                t.created_by,
                t.processed_by,
                t.token_date,
                t.created_at,
                t.updated_at,
                c.company_name,
                c.company_id,
                b.branch_name,
                b.branch_id,
                f.floor_name,
                f.floor_id,
                s.supplier_name,
                s.supplier_id,
                s.contact_number,
                s.email,
                s.address,
                DATEDIFF(CURDATE(), t.token_date) as days_old
            FROM tokens t
            LEFT JOIN companies c ON t.company_id = c.company_id
            LEFT JOIN branches b ON t.branch_id = b.branch_id
            LEFT JOIN floors f ON t.floor_id = f.floor_id
            LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($company_id) {
            $sql .= " AND t.company_id = :company_id";
            $params[':company_id'] = $company_id;
        }
        
        if ($branch_id) {
            $sql .= " AND t.branch_id = :branch_id";
            $params[':branch_id'] = $branch_id;
        }
        
        if ($floor_id) {
            $sql .= " AND t.floor_id = :floor_id";
            $params[':floor_id'] = $floor_id;
        }
        
        if ($supplier_id) {
            $sql .= " AND t.supplier_id = :supplier_id";
            $params[':supplier_id'] = $supplier_id;
        }
        
        if ($status) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $status;
        }
        
        if (!empty($search_term)) {
            $sql .= " AND (t.token_id LIKE :search OR t.invoice_no LIKE :search OR t.order_no LIKE :search OR s.supplier_name LIKE :search OR c.company_name LIKE :search)";
            $params[':search'] = '%' . $search_term . '%';
        }
        
        if ($date_from) {
            $sql .= " AND t.token_date >= :date_from";
            $params[':date_from'] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND t.token_date <= :date_to";
            $params[':date_to'] = $date_to;
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $searchError = "Search Error: " . $e->getMessage();
    }
}

// Get selected token details with logs
$selectedToken = null;
$tokenLogs = [];

if (isset($_GET['token_id']) && !empty($_GET['token_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                t.token_id,
                t.invoice_no,
                t.order_no,
                t.priority_no,
                t.status,
                t.created_by,
                t.processed_by,
                t.token_date,
                t.created_at,
                t.updated_at,
                c.company_name,
                c.company_id,
                b.branch_name,
                b.branch_id,
                f.floor_name,
                f.floor_id,
                s.supplier_name,
                s.supplier_id,
                s.contact_number,
                s.land_number,
                s.fax_number,
                s.contact_person,
                s.whatsapp,
                s.email,
                s.address,
                DATEDIFF(CURDATE(), t.token_date) as days_old
            FROM tokens t
            LEFT JOIN companies c ON t.company_id = c.company_id
            LEFT JOIN branches b ON t.branch_id = b.branch_id
            LEFT JOIN floors f ON t.floor_id = f.floor_id
            LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id
            WHERE t.token_id = :token_id
        ");
        $stmt->execute([':token_id' => $_GET['token_id']]);
        $selectedToken = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($selectedToken) {
            $logStmt = $pdo->prepare("
                SELECT 
                    log_id,
                    token_id,
                    user_id,
                    action_type,
                    old_value,
                    new_value,
                    note,
                    created_at
                FROM token_logs
                WHERE token_id = :token_id
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $logStmt->execute([':token_id' => $_GET['token_id']]);
            $tokenLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        // Handle error silently
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Group IT - Token Search & Filter</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        asb: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                            950: '#082f49',
                        }
                    },
                    animation: {
                        'pulse-slow': 'pulse 8s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'fade-in': 'fadeIn 0.8s ease-out',
                    },
                    keyframes: {
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.5); }
        ::-webkit-scrollbar-thumb { background: rgba(14, 165, 233, 0.4); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(14, 165, 233, 0.8); }
        
        .glass-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .bg-pattern {
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(14, 165, 233, 0.06) 0%, transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(14, 165, 233, 0.03) 0%, transparent 50%);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-pending { background: rgba(251, 191, 36, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
        .status-process { background: rgba(96, 165, 250, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); }
        .status-complete { background: rgba(52, 211, 153, 0.2); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
        .status-received { background: rgba(167, 139, 250, 0.2); color: #a78bfa; border: 1px solid rgba(167, 139, 250, 0.3); }
        
        .result-row {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        .result-row:hover {
            background: rgba(14, 165, 233, 0.05);
            transform: scale(1.002);
        }
        
        .detail-label {
            color: #94a3b8;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .detail-value {
            color: #f1f5f9;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .shimmer-text {
            background: linear-gradient(90deg, #38bdf8, #0ea5e9, #7dd3fc, #38bdf8);
            background-size: 200% auto;
            animation: shimmer 3s linear infinite;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        
        select, input {
            transition: all 0.3s ease;
        }
        select:focus, input:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        
        .print-button {
            transition: all 0.3s ease;
        }
        .print-button:hover {
            transform: scale(1.05);
        }
        
        /* ============================================
                   PRINT STYLES - A4 OPTIMIZED
                ============================================ */
        @media print {
            /* Hide all non-print elements */
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            
            /* Page setup - A4 */
            @page {
                size: A4 portrait;
                margin: 12mm 10mm 12mm 10mm;
            }
            
            /* Reset body for print */
            body { 
                background: white !important; 
                color: #1e293b !important;
                font-size: 9pt !important;
                line-height: 1.3 !important;
                padding: 0 !important;
                margin: 0 !important;
                min-height: auto !important;
                overflow: visible !important;
            }
            
            /* Container adjustments */
            main {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            /* Glass cards become plain white */
            .glass-card { 
                background: white !important; 
                border: 1px solid #e2e8f0 !important;
                box-shadow: none !important;
                backdrop-filter: none !important;
                border-radius: 4px !important;
                padding: 8px 10px !important;
                margin-bottom: 6px !important;
            }
            
            /* Text colors for print */
            .detail-label { color: #64748b !important; font-size: 7pt !important; }
            .detail-value { color: #0f172a !important; font-size: 8pt !important; }
            .text-white { color: #0f172a !important; }
            .text-slate-400 { color: #64748b !important; }
            .text-slate-300 { color: #334155 !important; }
            .text-slate-500 { color: #64748b !important; }
            .text-asb-300 { color: #0284c7 !important; }
            .text-rose-300 { color: #be123c !important; }
            .text-amber-400 { color: #d97706 !important; }
            .text-emerald-400 { color: #059669 !important; }
            .text-red-400 { color: #dc2626 !important; }
            .text-purple-400 { color: #7c3aed !important; }
            
            /* Status badges for print */
            .status-badge { 
                border: 1px solid #e2e8f0 !important;
                background: #f1f5f9 !important;
                color: #0f172a !important;
                font-size: 6pt !important;
                padding: 1px 6px !important;
            }
            .status-pending { border-color: #fbbf24 !important; background: #fef3c7 !important; }
            .status-process { border-color: #60a5fa !important; background: #dbeafe !important; }
            .status-complete { border-color: #34d399 !important; background: #d1fae5 !important; }
            .status-received { border-color: #a78bfa !important; background: #ede9fe !important; }
            
            /* Table styles */
            table { 
                border-collapse: collapse; 
                width: 100%; 
                font-size: 7pt !important;
            }
            th { 
                background: #f1f5f9 !important; 
                color: #0f172a !important; 
                border-bottom: 2px solid #e2e8f0 !important;
                padding: 4px 6px !important;
                font-size: 6pt !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                font-weight: 800 !important;
                text-align: left !important;
            }
            td { 
                border-bottom: 1px solid #e2e8f0 !important; 
                color: #0f172a !important;
                padding: 3px 6px !important;
                font-size: 7pt !important;
            }
            .result-row:hover { background: none !important; transform: none !important; }
            
            /* Grid for details */
            .grid { display: block !important; }
            .grid > div { 
                display: inline-block !important; 
                width: 33% !important;
                vertical-align: top !important;
                padding-right: 10px !important;
            }
            .space-y-3 > * { margin-bottom: 3px !important; }
            .gap-6 { gap: 0 !important; }
            
            /* Borders */
            .border-white\/5 { border-color: #e2e8f0 !important; }
            .border-slate-700\/50 { border-color: #e2e8f0 !important; }
            .divide-slate-800\/60 > * { border-color: #e2e8f0 !important; }
            
            /* Backgrounds */
            .bg-slate-900\/80 { background: #f8fafc !important; }
            .bg-slate-950\/80 { background: #f8fafc !important; }
            .bg-slate-800\/50 { background: #f1f5f9 !important; }
            .bg-slate-950 { background: white !important; }
            .bg-slate-900\/50 { background: #f8fafc !important; }
            
            /* Print Header */
            .print-header { 
                display: flex !important; 
                justify-content: space-between !important;
                align-items: center !important;
                padding-bottom: 6px !important;
                border-bottom: 3px double #0ea5e9 !important;
                margin-bottom: 10px !important;
            }
            .print-header h1 {
                font-size: 14pt !important;
                font-weight: 900 !important;
                color: #0f172a !important;
                margin: 0 !important;
            }
            .print-header .subtitle {
                font-size: 8pt !important;
                color: #64748b !important;
                margin: 0 !important;
            }
            .print-header .right {
                text-align: right !important;
            }
            .print-header .token-id {
                font-size: 10pt !important;
                font-weight: 700 !important;
                color: #0f172a !important;
                margin: 0 !important;
            }
            .print-header .date {
                font-size: 7pt !important;
                color: #94a3b8 !important;
                margin: 0 !important;
            }
            
            /* Print Footer */
            .print-footer {
                display: block !important;
                margin-top: 15px !important;
                padding-top: 6px !important;
                border-top: 2px solid #e2e8f0 !important;
                text-align: center !important;
                font-size: 7pt !important;
                color: #94a3b8 !important;
            }
            .print-footer p {
                margin: 1px 0 !important;
            }
            
            /* Hide scrollbars */
            .overflow-x-auto { overflow: visible !important; }
            .overflow-y-auto { overflow: visible !important; }
            .max-h-32 { max-height: none !important; }
            .flex-grow { flex-grow: 0 !important; }
            
            /* Spacing */
            .mt-6 { margin-top: 8px !important; }
            .mb-4 { margin-bottom: 6px !important; }
            .mb-6 { margin-bottom: 8px !important; }
            .p-6 { padding: 8px 10px !important; }
            .py-3 { padding-top: 4px !important; padding-bottom: 4px !important; }
            .px-4 { padding-left: 6px !important; padding-right: 6px !important; }
            
            /* Status badge inline for print */
            .status-badge {
                display: inline-block !important;
                white-space: nowrap !important;
            }
            
            /* Logs table compact */
            .token-logs-table th,
            .token-logs-table td {
                padding: 2px 4px !important;
                font-size: 6.5pt !important;
            }
            
            /* Ensure A4 fit */
            .print-wrapper {
                max-width: 100% !important;
            }
            
            /* Hide background images */
            .fixed.inset-0 { display: none !important; }
            
            /* Detail sections inline */
            .detail-section {
                display: inline-block !important;
                width: 33% !important;
                vertical-align: top !important;
                padding-right: 8px !important;
            }
            
            .detail-section h4 {
                font-size: 7pt !important;
                font-weight: 800 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                color: #0ea5e9 !important;
                border-bottom: 1px solid #e2e8f0 !important;
                padding-bottom: 2px !important;
                margin-bottom: 4px !important;
            }
        }
        
        .print-only { display: none; }
        .print-header { display: none; }
        .print-footer { display: none; }
        
        /* A4 size indicator for screen */
        .a4-indicator {
            display: none;
        }
    </style>
</head>
<body class="relative bg-slate-950 text-slate-100 min-h-screen antialiased font-sans bg-pattern">

    <!-- Dynamic Background -->
    <div class="fixed inset-0 z-0 pointer-events-none">
        <div class="w-full h-full bg-cover bg-center" 
             style="background-image: url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1920&q=80'); opacity: 0.06;">
        </div>
        <div class="absolute inset-0 bg-gradient-to-b from-slate-950/95 via-slate-950/90 to-slate-950/95"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/70 via-transparent to-slate-950/70"></div>
    </div>

    <!-- Header - Screen Only -->
    <header class="glass-card border-b border-white/5 px-6 py-3 flex items-center justify-between shadow-2xl relative z-10 flex-shrink-0 no-print">
        <div class="flex items-center space-x-6">
            <div class="flex items-center space-x-4">
                <div class="bg-gradient-to-br from-asb-600 to-asb-400 text-white px-3 py-1.5 rounded-xl font-black text-lg tracking-wider shadow-2xl shadow-asb-500/30 border border-asb-400/30">
                    ASB
                </div>
                <div>
                    <h1 class="text-lg font-black tracking-wide text-white uppercase">Group of Companies</h1>
                    <p class="text-xs text-slate-400 font-semibold flex items-center gap-3">
                        <span>IT Department</span>
                        <span class="w-1 h-1 rounded-full bg-slate-600"></span>
                        <span class="text-asb-400 font-bold">Token Search</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="dashboard.php" class="bg-slate-800/50 hover:bg-slate-700/50 text-slate-300 hover:text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-slate-700/50 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Dashboard
            </a>
            <a href="manage_priority.php" class="bg-slate-800/50 hover:bg-slate-700/50 text-slate-300 hover:text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-slate-700/50 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-list"></i> Priority
            </a>
            <a href="tv_display.php" target="_blank" class="bg-gradient-to-r from-asb-600 to-asb-400 hover:from-asb-500 hover:to-asb-300 text-white px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-lg shadow-asb-500/20">
                <i class="fa-solid fa-tv"></i> Live Display
            </a>
        </div>
    </header>

    <!-- Print Header -->
    <div class="print-header" style="display: none;">
        <div>
            <h1>ASB Group of Companies</h1>
            <p class="subtitle">IT Department - Token Details Report</p>
        </div>
        <div class="right">
            <p class="token-id">Token: <?php echo htmlspecialchars($selectedToken['token_id'] ?? ''); ?></p>
            <p class="date">Generated: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-grow p-6 max-w-7xl mx-auto relative z-10">
        
        <!-- Page Title - Screen Only -->
        <div class="flex items-center justify-between mb-6 no-print">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-asb-600 to-asb-400 flex items-center justify-center text-white text-lg shadow-lg shadow-asb-500/20">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-white tracking-tight">Token Search & Filter</h2>
                    <p class="text-sm text-slate-400">Search tokens by company, branch, floor, vendor, or any keyword</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm text-slate-400 no-print">
                <i class="fa-regular fa-clock"></i>
                <span id="live-time">Loading...</span>
            </div>
        </div>

        <!-- Search Form - Screen Only -->
        <div class="glass-card rounded-2xl p-6 mb-6 no-print">
            <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-search mr-1"></i> Search Term
                    </label>
                    <input type="text" 
                           name="search_term" 
                           value="<?php echo isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : ''; ?>"
                           placeholder="Token, Invoice, Order, Supplier..."
                           class="w-full bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none transition-all">
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-building mr-1"></i> Company
                    </label>
                    <select name="company_id" class="w-full bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition-all">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $c): ?>
                        <option value="<?php echo $c['company_id']; ?>" <?php echo (isset($_POST['company_id']) && $_POST['company_id'] == $c['company_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['company_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-sitemap mr-1"></i> Branch
                    </label>
                    <select name="branch_id" class="w-full bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition-all">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['branch_id']; ?>" <?php echo (isset($_POST['branch_id']) && $_POST['branch_id'] == $b['branch_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['branch_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-layer-group mr-1"></i> Floor
                    </label>
                    <select name="floor_id" class="w-full bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition-all">
                        <option value="">All Floors</option>
                        <?php foreach ($floors as $f): ?>
                        <option value="<?php echo $f['floor_id']; ?>" <?php echo (isset($_POST['floor_id']) && $_POST['floor_id'] == $f['floor_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['floor_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-truck mr-1"></i> Supplier / Vendor
                    </label>
                    <select name="supplier_id" class="w-full bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition-all">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo $s['supplier_id']; ?>" <?php echo (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $s['supplier_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['supplier_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-circle mr-1"></i> Status
                    </label>
                    <select name="status" class="w-full bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition-all">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo (isset($_POST['status']) && $_POST['status'] == $s) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($s); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-calendar-day mr-1"></i> Date From
                    </label>
                    <input type="date" 
                           name="date_from" 
                           value="<?php echo isset($_POST['date_from']) ? htmlspecialchars($_POST['date_from']) : ''; ?>"
                           class="w-full bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition-all">
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-calendar-day mr-1"></i> Date To
                    </label>
                    <input type="date" 
                           name="date_to" 
                           value="<?php echo isset($_POST['date_to']) ? htmlspecialchars($_POST['date_to']) : ''; ?>"
                           class="w-full bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition-all">
                </div>

                <div class="flex gap-2 items-end">
                    <button type="submit" name="search" value="1" class="flex-1 bg-gradient-to-r from-asb-600 to-asb-400 hover:from-asb-500 hover:to-asb-300 text-white font-extrabold py-2.5 rounded-xl text-sm shadow-lg shadow-asb-500/20 transition-all transform hover:scale-[1.02] active:scale-95">
                        <i class="fa-solid fa-search mr-2"></i> Search
                    </button>
                    <button type="reset" onclick="window.location.href='token_search.php'" class="flex-1 bg-slate-800/50 hover:bg-slate-700/50 text-slate-300 hover:text-white font-bold py-2.5 rounded-xl text-sm border border-slate-700/50 transition-all">
                        <i class="fa-solid fa-rotate-right mr-2"></i> Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <?php if ($searchPerformed): ?>
        <div class="flex items-center justify-between mb-4 no-print">
            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-400">Found <strong class="text-white text-lg"><?php echo count($searchResults); ?></strong> results</span>
                <?php if (!empty($searchResults)): ?>
                <button onclick="window.print()" class="print-button bg-slate-800/50 hover:bg-slate-700/50 text-slate-300 hover:text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-slate-700/50 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-print"></i> Print Results
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($searchResults)): ?>
            <div class="glass-card rounded-2xl p-12 text-center">
                <div class="w-20 h-20 bg-slate-800/50 rounded-full flex items-center justify-center text-4xl text-slate-600 mx-auto mb-4">
                    <i class="fa-regular fa-inbox"></i>
                </div>
                <h3 class="text-xl font-black text-white">No Results Found</h3>
                <p class="text-slate-400 text-sm mt-2">Try adjusting your search criteria or filters.</p>
            </div>
        <?php else: ?>
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-900/80 text-slate-400 text-xs font-black uppercase tracking-widest border-b border-white/5">
                                <th class="py-3 px-4">Token</th>
                                <th class="py-3 px-4">Invoice</th>
                                <th class="py-3 px-4">Supplier</th>
                                <th class="py-3 px-4">Company</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-center">Date</th>
                                <th class="py-3 px-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php foreach ($searchResults as $result): 
                                $statusClass = 'status-' . ($result['status'] ?? 'pending');
                            ?>
                            <tr class="result-row">
                                <td class="py-3 px-4">
                                    <span class="font-mono font-bold text-asb-300 text-sm"><?php echo htmlspecialchars($result['token_id']); ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-mono font-bold text-white text-sm"><?php echo htmlspecialchars($result['invoice_no']); ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-slate-200 text-sm"><?php echo htmlspecialchars($result['supplier_name'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-slate-300 text-sm"><?php echo htmlspecialchars($result['company_name'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="text-slate-400 text-sm font-mono"><?php echo date('d/m/Y', strtotime($result['token_date'])); ?></span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="?token_id=<?php echo urlencode($result['token_id']); ?>" class="bg-asb-500/20 hover:bg-asb-500/30 text-asb-400 hover:text-white px-3 py-1 rounded-lg text-xs font-bold border border-asb-500/30 transition-all inline-flex items-center gap-1.5">
                                        <i class="fa-regular fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Token Details Section with Logs -->
        <?php if ($selectedToken): ?>
        <div class="mt-6 animate-slide-up print-wrapper">
            
            <div class="flex items-center gap-3 mb-4 no-print">
                <h3 class="text-xl font-black text-white">Token Details</h3>
                <span class="text-xs bg-asb-500/20 text-asb-400 px-3 py-1 rounded-full border border-asb-500/30 font-mono">
                    <?php echo htmlspecialchars($selectedToken['token_id']); ?>
                </span>
                <button onclick="window.print()" class="print-button bg-slate-800/50 hover:bg-slate-700/50 text-slate-300 hover:text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-slate-700/50 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-print"></i> Print
                </button>
                <button onclick="window.location.href='token_search.php'" class="text-slate-400 hover:text-white text-sm">
                    <i class="fa-solid fa-xmark"></i> Close
                </button>
            </div>

            <!-- Token Details Card -->
            <div class="glass-card rounded-2xl p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Column 1: Token Information -->
                    <div class="detail-section">
                        <h4 class="text-xs font-black uppercase tracking-widest text-asb-400 border-b border-white/5 pb-2 flex items-center gap-2">
                            <i class="fa-regular fa-tag"></i> Token Information
                        </h4>
                        <div class="space-y-2 mt-2">
                            <div>
                                <div class="detail-label">Token ID</div>
                                <div class="detail-value font-mono text-asb-300"><?php echo htmlspecialchars($selectedToken['token_id']); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Invoice Number</div>
                                <div class="detail-value font-mono"><?php echo htmlspecialchars($selectedToken['invoice_no']); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Order Reference</div>
                                <div class="detail-value font-mono"><?php echo htmlspecialchars($selectedToken['order_no'] ?? '—'); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Priority Rank</div>
                                <div class="detail-value font-mono font-bold <?php echo ($selectedToken['priority_no'] == 1) ? 'text-amber-400' : 'text-white'; ?>">
                                    <?php echo $selectedToken['priority_no'] ? '#' . $selectedToken['priority_no'] : '—'; ?>
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">Status</div>
                                <span class="status-badge <?php echo 'status-' . ($selectedToken['status'] ?? 'pending'); ?>">
                                    <?php echo ucfirst($selectedToken['status'] ?? 'Pending'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Company & Location -->
                    <div class="detail-section">
                        <h4 class="text-xs font-black uppercase tracking-widest text-asb-400 border-b border-white/5 pb-2 flex items-center gap-2">
                            <i class="fa-regular fa-building"></i> Company & Location
                        </h4>
                        <div class="space-y-2 mt-2">
                            <div>
                                <div class="detail-label">Company</div>
                                <div class="detail-value"><?php echo htmlspecialchars($selectedToken['company_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Branch</div>
                                <div class="detail-value"><?php echo htmlspecialchars($selectedToken['branch_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Floor Level</div>
                                <div class="detail-value"><?php echo htmlspecialchars($selectedToken['floor_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Token Date</div>
                                <div class="detail-value font-mono"><?php echo date('d/m/Y', strtotime($selectedToken['token_date'])); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Days Old</div>
                                <div class="detail-value font-mono <?php echo ($selectedToken['days_old'] > 2) ? 'text-red-400' : 'text-emerald-400'; ?>">
                                    <?php echo $selectedToken['days_old'] . ' days'; ?>
                                    <?php if ($selectedToken['days_old'] > 2): ?>
                                    <span class="text-red-400 ml-1"><i class="fa-regular fa-triangle-exclamation"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: Supplier Details -->
                    <div class="detail-section">
                        <h4 class="text-xs font-black uppercase tracking-widest text-asb-400 border-b border-white/5 pb-2 flex items-center gap-2">
                            <i class="fa-regular fa-truck"></i> Supplier / Vendor
                        </h4>
                        <div class="space-y-2 mt-2">
                            <div>
                                <div class="detail-label">Supplier Name</div>
                                <div class="detail-value text-rose-300"><?php echo htmlspecialchars($selectedToken['supplier_name'] ?? 'N/A'); ?></div>
                            </div>
                            <?php if (!empty($selectedToken['contact_number'])): ?>
                            <div>
                                <div class="detail-label">Contact Number</div>
                                <div class="detail-value font-mono"><?php echo htmlspecialchars($selectedToken['contact_number']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($selectedToken['email'])): ?>
                            <div>
                                <div class="detail-label">Email</div>
                                <div class="detail-value text-sm"><?php echo htmlspecialchars($selectedToken['email']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($selectedToken['contact_person'])): ?>
                            <div>
                                <div class="detail-label">Contact Person</div>
                                <div class="detail-value"><?php echo htmlspecialchars($selectedToken['contact_person']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($selectedToken['address'])): ?>
                            <div>
                                <div class="detail-label">Address</div>
                                <div class="detail-value text-sm"><?php echo nl2br(htmlspecialchars(substr($selectedToken['address'], 0, 60) . (strlen($selectedToken['address']) > 60 ? '...' : ''))); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="mt-4 pt-3 border-t border-white/5 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <span class="detail-label">Created By</span>
                        <div class="detail-value text-sm"><?php echo htmlspecialchars($selectedToken['created_by'] ?? 'System Kiosk'); ?></div>
                    </div>
                    <div>
                        <span class="detail-label">Processed By</span>
                        <div class="detail-value text-sm"><?php echo htmlspecialchars($selectedToken['processed_by'] ?? '—'); ?></div>
                    </div>
                    <div>
                        <span class="detail-label">Created At</span>
                        <div class="detail-value text-sm font-mono"><?php echo date('d/m/Y H:i', strtotime($selectedToken['created_at'])); ?></div>
                    </div>
                    <div>
                        <span class="detail-label">Last Updated</span>
                        <div class="detail-value text-sm font-mono"><?php echo date('d/m/Y H:i', strtotime($selectedToken['updated_at'] ?? $selectedToken['created_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Token Logs Section -->
            <div class="mt-4">
                <div class="flex items-center gap-3 mb-3 no-print">
                    <h3 class="text-xl font-black text-white">Token History Logs</h3>
                    <span class="text-xs bg-slate-800/50 text-slate-400 px-3 py-1 rounded-full font-mono">
                        <?php echo count($tokenLogs); ?> records
                    </span>
                </div>

                <?php if (empty($tokenLogs)): ?>
                <div class="glass-card rounded-2xl p-8 text-center">
                    <div class="w-16 h-16 bg-slate-800/50 rounded-full flex items-center justify-center text-3xl text-slate-600 mx-auto mb-3">
                        <i class="fa-regular fa-clock"></i>
                    </div>
                    <h4 class="text-lg font-bold text-white">No Logs Found</h4>
                    <p class="text-slate-400 text-sm mt-1">No history logs available for this token.</p>
                </div>
                <?php else: ?>
                <div class="glass-card rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="token-logs-table w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-900/80 text-slate-400 text-xs font-black uppercase tracking-widest border-b border-white/5">
                                    <th class="py-2 px-3">#</th>
                                    <th class="py-2 px-3">Action</th>
                                    <th class="py-2 px-3">Old Value</th>
                                    <th class="py-2 px-3">New Value</th>
                                    <th class="py-2 px-3">Note</th>
                                    <th class="py-2 px-3 text-center">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <?php 
                                $logCounter = 1;
                                foreach ($tokenLogs as $log): 
                                    $actionColor = '';
                                    if (strpos($log['action_type'], 'STATUS') !== false) {
                                        $actionColor = 'text-amber-400';
                                    } elseif (strpos($log['action_type'], 'PRIORITY') !== false) {
                                        $actionColor = 'text-purple-400';
                                    } else {
                                        $actionColor = 'text-slate-400';
                                    }
                                ?>
                                <tr class="result-row">
                                    <td class="py-2 px-3">
                                        <span class="text-slate-500 text-xs font-mono"><?php echo $logCounter++; ?></span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <span class="<?php echo $actionColor; ?> font-bold text-xs">
                                            <?php echo str_replace('_', ' ', $log['action_type']); ?>
                                        </span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <span class="text-slate-400 text-xs font-mono"><?php echo htmlspecialchars(substr($log['old_value'] ?? '—', 0, 30)); ?></span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <span class="text-slate-300 text-xs font-mono"><?php echo htmlspecialchars(substr($log['new_value'] ?? '—', 0, 30)); ?></span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <span class="text-slate-400 text-xs"><?php echo htmlspecialchars(substr($log['note'] ?? '—', 0, 40)); ?></span>
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        <span class="text-slate-400 text-[10px] font-mono whitespace-nowrap"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Print Footer -->
            <div class="print-footer" style="display: none;">
                <p>This is a computer-generated document. No signature required.</p>
                <p style="font-size: 6pt; color: #94a3b8;">
                    ASB Group of Companies - IT Department | Generated on <?php echo date('d/m/Y H:i:s'); ?>
                </p>
                <p style="font-size: 6pt; color: #94a3b8;">
                    Designed &amp; Developed by Vexel IT by Kavizz
                </p>
            </div>

        </div>
        <?php endif; ?>

    </main>

    <!-- Footer - Screen Only -->
    <footer class="bg-slate-900/90 border-t border-white/5 py-2 px-6 text-xs text-slate-400 flex items-center justify-between relative z-10 flex-shrink-0 no-print">
        <div class="flex items-center gap-2">
            <span class="text-slate-500">Developed by</span>
            <span class="font-extrabold text-slate-200 bg-slate-800/50 px-2 py-0.5 rounded border border-slate-700/50">Vexel IT by Kavizz</span>
        </div>
        <div class="flex items-center gap-4">
            <span><i class="fa-regular fa-building text-asb-400 mr-1"></i> ASB Group IT</span>
            <span class="text-slate-600">|</span>
            <span class="text-slate-500">v1.0</span>
        </div>
    </footer>

    <script>
        function updateClock() {
            const now = new Date();
            document.getElementById('live-time').textContent = now.toLocaleTimeString();
        }
        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>