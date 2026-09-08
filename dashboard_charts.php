<?php
require_once 'db.php';
session_start();

date_default_timezone_set('Asia/Colombo');

// Get date range from request
$date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Validate dates
$date_from = date('Y-m-d', strtotime($date_from));
$date_to = date('Y-m-d', strtotime($date_to));

// Ensure date_from is not after date_to
if ($date_from > $date_to) {
    $date_from = date('Y-m-d', strtotime('-7 days'));
    $date_to = date('Y-m-d');
}

// Calculate date range in days for display
$dateRangeDays = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1;

// Fetch data with date range filter - OPTIMIZED for large datasets
try {
    // 1. Get daily stats with date range - Using indexed date column
    $dailyStats = $pdo->prepare("
        SELECT 
            DATE(token_date) as date,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' OR status IS NULL THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'process' THEN 1 ELSE 0 END) as process,
            SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as complete,
            SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received
        FROM tokens 
        WHERE token_date BETWEEN :date_from AND :date_to
        GROUP BY DATE(token_date)
        ORDER BY DATE(token_date) ASC
    ");
    $dailyStats->execute([':date_from' => $date_from, ':date_to' => $date_to]);
    $dailyStats = $dailyStats->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get status distribution - Using index for performance
    $statusDistribution = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM tokens 
        WHERE token_date BETWEEN :date_from AND :date_to
        GROUP BY status
    ");
    $statusDistribution->execute([':date_from' => $date_from, ':date_to' => $date_to]);
    $statusDistribution = $statusDistribution->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get top suppliers - Using index for performance
    $topSuppliers = $pdo->prepare("
        SELECT 
            COALESCE(s.supplier_name, 'Unknown') as supplier_name,
            COUNT(*) as count
        FROM tokens t
        LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id
        WHERE t.token_date BETWEEN :date_from AND :date_to
        GROUP BY t.supplier_id
        ORDER BY count DESC
        LIMIT 10
    ");
    $topSuppliers->execute([':date_from' => $date_from, ':date_to' => $date_to]);
    $topSuppliers = $topSuppliers->fetchAll(PDO::FETCH_ASSOC);

    // 4. Get top companies
    $topCompanies = $pdo->prepare("
        SELECT 
            COALESCE(c.company_name, 'Unknown') as company_name,
            COUNT(*) as count
        FROM tokens t
        LEFT JOIN companies c ON t.company_id = c.company_id
        WHERE t.token_date BETWEEN :date_from AND :date_to
        GROUP BY t.company_id
        ORDER BY count DESC
        LIMIT 10
    ");
    $topCompanies->execute([':date_from' => $date_from, ':date_to' => $date_to]);
    $topCompanies = $topCompanies->fetchAll(PDO::FETCH_ASSOC);

    // 5. Get floor distribution
    $floorDistribution = $pdo->prepare("
        SELECT 
            COALESCE(f.floor_name, 'Unknown') as floor_name,
            COUNT(*) as count
        FROM tokens t
        LEFT JOIN floors f ON t.floor_id = f.floor_id
        WHERE t.token_date BETWEEN :date_from AND :date_to
        GROUP BY t.floor_id
        ORDER BY count DESC
    ");
    $floorDistribution->execute([':date_from' => $date_from, ':date_to' => $date_to]);
    $floorDistribution = $floorDistribution->fetchAll(PDO::FETCH_ASSOC);

    // 6. Get hourly distribution - Using created_at index
    $hourlyDistribution = $pdo->prepare("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as count
        FROM tokens 
        WHERE DATE(created_at) BETWEEN :date_from AND :date_to
        GROUP BY HOUR(created_at)
        ORDER BY hour ASC
    ");
    $hourlyDistribution->execute([':date_from' => $date_from, ':date_to' => $date_to]);
    $hourlyDistribution = $hourlyDistribution->fetchAll(PDO::FETCH_ASSOC);

    // 7. Get totals for summary cards
    $totals = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('pending', 'process') THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'pending' OR status IS NULL THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'process' THEN 1 ELSE 0 END) as process_count,
            SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as complete_count,
            SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_count
        FROM tokens 
        WHERE token_date BETWEEN :date_from AND :date_to
    ");
    $totals->execute([':date_from' => $date_from, ':date_to' => $date_to]);
    $totals = $totals->fetch(PDO::FETCH_ASSOC);

    $totalLastXDays = $totals['total'] ?? 0;
    $activeCount = $totals['active'] ?? 0;
    $pendingCount = $totals['pending_count'] ?? 0;
    $processCount = $totals['process_count'] ?? 0;
    $completeCount = $totals['complete_count'] ?? 0;
    $receivedCount = $totals['received_count'] ?? 0;

    // Calculate daily average
    $daysInRange = max(1, $dateRangeDays);
    $dailyAverage = round($totalLastXDays / $daysInRange, 1);

    // Get peak day
    $peakDay = 0;
    if (!empty($dailyStats)) {
        $peakDay = max(array_column($dailyStats, 'total'));
    }

    // Get quick stats for different periods
    $quickStats = [];
    $periods = [
        'Today' => ['start' => date('Y-m-d'), 'end' => date('Y-m-d')],
        'Yesterday' => ['start' => date('Y-m-d', strtotime('-1 day')), 'end' => date('Y-m-d', strtotime('-1 day'))],
        'Last 7 Days' => ['start' => date('Y-m-d', strtotime('-7 days')), 'end' => date('Y-m-d')],
        'Last 30 Days' => ['start' => date('Y-m-d', strtotime('-30 days')), 'end' => date('Y-m-d')],
        'This Month' => ['start' => date('Y-m-01'), 'end' => date('Y-m-d')],
        'Last Month' => ['start' => date('Y-m-01', strtotime('-1 month')), 'end' => date('Y-m-t', strtotime('-1 month'))],
    ];

    foreach ($periods as $key => $period) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM tokens 
            WHERE token_date BETWEEN :start AND :end
        ");
        $stmt->execute([':start' => $period['start'], ':end' => $period['end']]);
        $quickStats[$key] = $stmt->fetchColumn();
    }

} catch (Exception $e) {
    die("<div style='color:#ef4444; background:#0b0f19; padding:24px; font-weight:bold; font-family:sans-serif; text-align:center;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Prepare data for charts
$dates = array_column($dailyStats, 'date');
$pendingData = array_column($dailyStats, 'pending');
$processData = array_column($dailyStats, 'process');
$completeData = array_column($dailyStats, 'complete');
$receivedData = array_column($dailyStats, 'received');

// Status distribution for pie chart
$statusLabels = [];
$statusCounts = [];
$statusColors = [
    'pending' => '#fbbf24',
    'process' => '#60a5fa',
    'complete' => '#34d399',
    'received' => '#a78bfa'
];
foreach ($statusDistribution as $row) {
    $statusLabels[] = ucfirst($row['status'] ?? 'Pending');
    $statusCounts[] = $row['count'];
}

// Check if data is large (> 1M records)
$isLargeDataset = $totalLastXDays > 1000000;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Group IT - Analytics Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        
        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }
        
        .chart-container-pie {
            position: relative;
            height: 280px;
            width: 100%;
            max-width: 320px;
            margin: 0 auto;
        }
        
        .stat-number {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 900;
        }
        
        .stat-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.4);
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
        
        .quick-stat-box {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 8px 12px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .quick-stat-box:hover {
            background: rgba(14, 165, 233, 0.05);
            border-color: rgba(14, 165, 233, 0.2);
        }
        
        .large-dataset-warning {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.3);
            padding: 8px 16px;
            border-radius: 10px;
            color: #fbbf24;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        @media print {
            .no-print { display: none !important; }
            .glass-card { border: 1px solid #e2e8f0 !important; background: white !important; }
            body { background: white !important; color: #0f172a !important; }
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

    <!-- Header -->
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
                        <span class="text-asb-400 font-bold">Analytics Dashboard</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="dashboard.php" class="bg-slate-800/50 hover:bg-slate-700/50 text-slate-300 hover:text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-slate-700/50 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Dashboard
            </a>
            <a href="token_search.php" class="bg-slate-800/50 hover:bg-slate-700/50 text-slate-300 hover:text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-slate-700/50 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-search"></i> Search
            </a>
            <button onclick="window.print()" class="bg-gradient-to-r from-asb-600 to-asb-400 hover:from-asb-500 hover:to-asb-300 text-white px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-lg shadow-asb-500/20">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow p-6 max-w-7xl mx-auto relative z-10">
        
        <!-- Page Title -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-asb-600 to-asb-400 flex items-center justify-center text-white text-lg shadow-lg shadow-asb-500/20">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-white tracking-tight">Analytics Dashboard</h2>
                    <p class="text-sm text-slate-400">Comprehensive overview of token activity with date filtering</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm text-slate-400 no-print">
                <i class="fa-regular fa-clock"></i>
                <span id="live-time">Loading...</span>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="glass-card rounded-2xl p-4 mb-6 no-print">
            <form method="GET" action="" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-calendar-day mr-1"></i> Date From
                    </label>
                    <input type="date" 
                           name="date_from" 
                           value="<?php echo $date_from; ?>"
                           class="bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2 text-sm text-white focus:outline-none transition-all w-40">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-calendar-day mr-1"></i> Date To
                    </label>
                    <input type="date" 
                           name="date_to" 
                           value="<?php echo $date_to; ?>"
                           class="bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2 text-sm text-white focus:outline-none transition-all w-40">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <i class="fa-regular fa-clock mr-1"></i> Quick Select
                    </label>
                    <select onchange="this.value && window.location.href='?date_from='+this.value.split('|')[0]+'&date_to='+this.value.split('|')[1]" 
                            class="bg-slate-900/50 border border-slate-700/50 focus:border-asb-500 rounded-xl px-3 py-2 text-sm text-white focus:outline-none transition-all w-40">
                        <option value="">Custom Range</option>
                        <option value="<?php echo date('Y-m-d'); ?>|<?php echo date('Y-m-d'); ?>">Today</option>
                        <option value="<?php echo date('Y-m-d', strtotime('-1 day')); ?>|<?php echo date('Y-m-d', strtotime('-1 day')); ?>">Yesterday</option>
                        <option value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>|<?php echo date('Y-m-d'); ?>" <?php echo ($date_from == date('Y-m-d', strtotime('-7 days')) && $date_to == date('Y-m-d')) ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>|<?php echo date('Y-m-d'); ?>" <?php echo ($date_from == date('Y-m-d', strtotime('-30 days')) && $date_to == date('Y-m-d')) ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="<?php echo date('Y-m-01'); ?>|<?php echo date('Y-m-d'); ?>" <?php echo ($date_from == date('Y-m-01') && $date_to == date('Y-m-d')) ? 'selected' : ''; ?>>This Month</option>
                        <option value="<?php echo date('Y-m-01', strtotime('-1 month')); ?>|<?php echo date('Y-m-t', strtotime('-1 month')); ?>">Last Month</option>
                        <option value="<?php echo date('Y-m-d', strtotime('-90 days')); ?>|<?php echo date('Y-m-d'); ?>">Last 90 Days</option>
                        <option value="<?php echo date('Y-m-d', strtotime('-365 days')); ?>|<?php echo date('Y-m-d'); ?>">Last Year</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="bg-gradient-to-r from-asb-600 to-asb-400 hover:from-asb-500 hover:to-asb-300 text-white font-extrabold px-6 py-2 rounded-xl text-sm shadow-lg shadow-asb-500/20 transition-all transform hover:scale-[1.02] active:scale-95">
                        <i class="fa-solid fa-filter mr-2"></i> Apply Filter
                    </button>
                    <a href="dashboard_charts.php" class="inline-block bg-slate-800/50 hover:bg-slate-700/50 text-slate-300 hover:text-white font-bold px-4 py-2 rounded-xl text-sm border border-slate-700/50 transition-all ml-2">
                        <i class="fa-solid fa-rotate-right mr-1"></i> Reset
                    </a>
                </div>
            </form>
            
            <!-- Date Range Info -->
            <div class="flex items-center gap-4 mt-3 pt-3 border-t border-white/5 text-xs text-slate-400">
                <span><i class="fa-regular fa-calendar mr-1"></i> Range: <strong class="text-white"><?php echo date('d M Y', strtotime($date_from)); ?></strong> to <strong class="text-white"><?php echo date('d M Y', strtotime($date_to)); ?></strong></span>
                <span class="w-px h-4 bg-slate-700"></span>
                <span><i class="fa-regular fa-clock mr-1"></i> <strong class="text-white"><?php echo $dateRangeDays; ?></strong> days</span>
                <span class="w-px h-4 bg-slate-700"></span>
                <span><i class="fa-regular fa-layer-group mr-1"></i> <strong class="text-white"><?php echo number_format($totalLastXDays); ?></strong> total records</span>
                <?php if ($isLargeDataset): ?>
                <span class="large-dataset-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i> Large Dataset (<?php echo number_format($totalLastXDays); ?> records) - Performance Optimized
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-6 no-print">
            <?php foreach ($quickStats as $label => $count): ?>
            <div class="quick-stat-box">
                <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?php echo $label; ?></div>
                <div class="text-lg font-black text-white stat-number"><?php echo number_format($count); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="glass-card rounded-2xl p-4 stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Tokens</p>
                        <p class="text-2xl font-black text-white stat-number"><?php echo number_format($totalLastXDays); ?></p>
                        <p class="text-xs text-slate-500">Selected date range</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-asb-500/20 border border-asb-500/30 flex items-center justify-center text-asb-400 text-xl">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>
            </div>
            <div class="glass-card rounded-2xl p-4 stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Daily Average</p>
                        <p class="text-2xl font-black text-white stat-number"><?php echo number_format($dailyAverage, 1); ?></p>
                        <p class="text-xs text-slate-500">Per day</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-xl">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
            </div>
            <div class="glass-card rounded-2xl p-4 stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Peak Day</p>
                        <p class="text-2xl font-black text-white stat-number"><?php echo number_format($peakDay); ?></p>
                        <p class="text-xs text-slate-500">Highest volume</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400 text-xl">
                        <i class="fa-solid fa-fire"></i>
                    </div>
                </div>
            </div>
            <div class="glass-card rounded-2xl p-4 stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Active Status</p>
                        <p class="text-2xl font-black text-white stat-number"><?php echo number_format($activeCount); ?></p>
                        <p class="text-xs text-slate-500">Pending + Process</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-rose-500/20 border border-rose-500/30 flex items-center justify-center text-rose-400 text-xl">
                        <i class="fa-solid fa-spinner"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Bar Chart - Daily Trend -->
            <div class="glass-card rounded-2xl p-6 lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-regular fa-chart-bar text-asb-400"></i> Daily Token Trend
                    </h3>
                    <span class="text-xs text-slate-500"><?php echo $dateRangeDays; ?> days</span>
                </div>
                <div class="chart-container">
                    <canvas id="dailyTrendChart"></canvas>
                </div>
            </div>

            <!-- Pie Chart - Status Distribution -->
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-regular fa-circle-pie text-asb-400"></i> Status Distribution
                    </h3>
                    <span class="text-xs text-slate-500">Total: <?php echo number_format($totalLastXDays); ?></span>
                </div>
                <div class="chart-container-pie">
                    <canvas id="statusPieChart"></canvas>
                </div>
                <!-- Status Legend Details -->
                <div class="grid grid-cols-2 gap-1 mt-3 text-xs">
                    <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Pending: <?php echo number_format($pendingCount); ?></div>
                    <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-sky-400"></span> Process: <?php echo number_format($processCount); ?></div>
                    <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Complete: <?php echo number_format($completeCount); ?></div>
                    <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-purple-400"></span> Received: <?php echo number_format($receivedCount); ?></div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Bar Chart - Top Suppliers -->
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-regular fa-truck text-asb-400"></i> Top Suppliers
                    </h3>
                    <span class="text-xs text-slate-500">Top 10</span>
                </div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="topSuppliersChart"></canvas>
                </div>
            </div>

            <!-- Bar Chart - Top Companies -->
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-regular fa-building text-asb-400"></i> Top Companies
                    </h3>
                    <span class="text-xs text-slate-500">Top 10</span>
                </div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="topCompaniesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 3 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Bar Chart - Floor Distribution -->
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-regular fa-layer-group text-asb-400"></i> Floor Distribution
                    </h3>
                    <span class="text-xs text-slate-500">All floors</span>
                </div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="floorDistributionChart"></canvas>
                </div>
            </div>

            <!-- Bar Chart - Hourly Distribution -->
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-regular fa-clock text-asb-400"></i> Hourly Activity
                    </h3>
                    <span class="text-xs text-slate-500">24 hours</span>
                </div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="hourlyDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Summary Table -->
        <div class="glass-card rounded-2xl p-6 mt-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-black text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-regular fa-table text-asb-400"></i> Daily Summary
                </h3>
                <span class="text-xs text-slate-500"><?php echo count($dailyStats); ?> days</span>
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 bg-slate-900/95">
                        <tr class="text-slate-400 text-xs font-black uppercase tracking-widest border-b border-white/5">
                            <th class="py-2 px-3">Date</th>
                            <th class="py-2 px-3 text-center">Total</th>
                            <th class="py-2 px-3 text-center">Pending</th>
                            <th class="py-2 px-3 text-center">Process</th>
                            <th class="py-2 px-3 text-center">Complete</th>
                            <th class="py-2 px-3 text-center">Received</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach (array_reverse($dailyStats) as $row): ?>
                        <tr>
                            <td class="py-2 px-3 font-mono text-sm text-white"><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                            <td class="py-2 px-3 text-center font-bold text-white"><?php echo number_format($row['total']); ?></td>
                            <td class="py-2 px-3 text-center text-amber-400"><?php echo number_format($row['pending']); ?></td>
                            <td class="py-2 px-3 text-center text-sky-400"><?php echo number_format($row['process']); ?></td>
                            <td class="py-2 px-3 text-center text-emerald-400"><?php echo number_format($row['complete']); ?></td>
                            <td class="py-2 px-3 text-center text-purple-400"><?php echo number_format($row['received']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-slate-900/80 border-t-2 border-white/10">
                        <tr>
                            <td class="py-2 px-3 font-bold text-white">Total</td>
                            <td class="py-2 px-3 text-center font-bold text-white"><?php echo number_format($totalLastXDays); ?></td>
                            <td class="py-2 px-3 text-center font-bold text-amber-400"><?php echo number_format($pendingCount); ?></td>
                            <td class="py-2 px-3 text-center font-bold text-sky-400"><?php echo number_format($processCount); ?></td>
                            <td class="py-2 px-3 text-center font-bold text-emerald-400"><?php echo number_format($completeCount); ?></td>
                            <td class="py-2 px-3 text-center font-bold text-purple-400"><?php echo number_format($receivedCount); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-900/90 border-t border-white/5 py-2 px-6 text-xs text-slate-400 flex items-center justify-between relative z-10 flex-shrink-0 no-print">
        <div class="flex items-center gap-2">
            <span class="text-slate-500">Developed by</span>
            <span class="font-extrabold text-slate-200 bg-slate-800/50 px-2 py-0.5 rounded border border-slate-700/50">Vexel IT by Kavizz</span>
        </div>
        <div class="flex items-center gap-4">
            <span><i class="fa-regular fa-building text-asb-400 mr-1"></i> ASB Group IT</span>
            <span class="text-slate-600">|</span>
            <span class="text-slate-500">v2.0</span>
            <?php if ($isLargeDataset): ?>
            <span class="text-amber-400 text-[10px]"><i class="fa-solid fa-bolt"></i> Optimized</span>
            <?php endif; ?>
        </div>
    </footer>

    <script>
        // Live Clock
        function updateClock() {
            const now = new Date();
            document.getElementById('live-time').textContent = now.toLocaleTimeString();
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Chart Colors
        const colors = {
            pending: '#fbbf24',
            process: '#60a5fa',
            complete: '#34d399',
            received: '#a78bfa'
        };

        // Chart data
        const dates = <?php echo json_encode($dates); ?>;
        const pendingData = <?php echo json_encode($pendingData); ?>;
        const processData = <?php echo json_encode($processData); ?>;
        const completeData = <?php echo json_encode($completeData); ?>;
        const receivedData = <?php echo json_encode($receivedData); ?>;

        // 1. Daily Trend Chart
        const dailyCtx = document.getElementById('dailyTrendChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Pending',
                        data: pendingData,
                        backgroundColor: 'rgba(251, 191, 36, 0.7)',
                        borderColor: '#fbbf24',
                        borderWidth: 1
                    },
                    {
                        label: 'Process',
                        data: processData,
                        backgroundColor: 'rgba(96, 165, 250, 0.7)',
                        borderColor: '#60a5fa',
                        borderWidth: 1
                    },
                    {
                        label: 'Complete',
                        data: completeData,
                        backgroundColor: 'rgba(52, 211, 153, 0.7)',
                        borderColor: '#34d399',
                        borderWidth: 1
                    },
                    {
                        label: 'Received',
                        data: receivedData,
                        backgroundColor: 'rgba(167, 139, 250, 0.7)',
                        borderColor: '#a78bfa',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#94a3b8',
                            font: { size: 10, weight: 'bold' },
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 10 }, stepSize: 1 }
                    }
                }
            }
        });

        // 2. Status Pie Chart
        const pieCtx = document.getElementById('statusPieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($statusLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($statusCounts); ?>,
                    backgroundColor: [
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(96, 165, 250, 0.8)',
                        'rgba(52, 211, 153, 0.8)',
                        'rgba(167, 139, 250, 0.8)'
                    ],
                    borderColor: '#0f172a',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            font: { size: 10, weight: 'bold' },
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                }
            }
        });

        // 3. Top Suppliers Chart
        const supplierCtx = document.getElementById('topSuppliersChart').getContext('2d');
        new Chart(supplierCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($topSuppliers, 'supplier_name')); ?>,
                datasets: [{
                    label: 'Tokens',
                    data: <?php echo json_encode(array_column($topSuppliers, 'count')); ?>,
                    backgroundColor: 'rgba(14, 165, 233, 0.7)',
                    borderColor: '#0ea5e9',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 10 }, stepSize: 1 }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 9 } }
                    }
                }
            }
        });

        // 4. Top Companies Chart
        const companyCtx = document.getElementById('topCompaniesChart').getContext('2d');
        new Chart(companyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($topCompanies, 'company_name')); ?>,
                datasets: [{
                    label: 'Tokens',
                    data: <?php echo json_encode(array_column($topCompanies, 'count')); ?>,
                    backgroundColor: 'rgba(52, 211, 153, 0.7)',
                    borderColor: '#34d399',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 10 }, stepSize: 1 }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 9 } }
                    }
                }
            }
        });

        // 5. Floor Distribution Chart
        const floorCtx = document.getElementById('floorDistributionChart').getContext('2d');
        new Chart(floorCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($floorDistribution, 'floor_name')); ?>,
                datasets: [{
                    label: 'Tokens',
                    data: <?php echo json_encode(array_column($floorDistribution, 'count')); ?>,
                    backgroundColor: 'rgba(167, 139, 250, 0.7)',
                    borderColor: '#a78bfa',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 10 }, stepSize: 1 }
                    }
                }
            }
        });

        // 6. Hourly Distribution Chart
        const hourlyCtx = document.getElementById('hourlyDistributionChart').getContext('2d');
        const hourLabels = <?php echo json_encode(array_column($hourlyDistribution, 'hour')); ?>;
        const hourData = <?php echo json_encode(array_column($hourlyDistribution, 'count')); ?>;
        
        const fullHourData = Array(24).fill(0);
        for (let i = 0; i < hourLabels.length; i++) {
            fullHourData[hourLabels[i]] = hourData[i];
        }
        
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Tokens',
                    data: fullHourData,
                    backgroundColor: 'rgba(251, 191, 36, 0.7)',
                    borderColor: '#fbbf24',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 8 }, maxTicksLimit: 12 }
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 10 }, stepSize: 1 }
                    }
                }
            }
        });
    </script>
</body>
</html>