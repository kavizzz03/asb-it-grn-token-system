<?php 
require_once 'db.php'; 

date_default_timezone_set('Asia/Colombo');

// Fetch dropdown options
try {
    $branches = $pdo->query("SELECT branch_id, branch_name FROM branches WHERE status='active' ORDER BY branch_name ASC")->fetchAll();
    $floors   = $pdo->query("SELECT floor_id, floor_name FROM floors ORDER BY floor_id ASC")->fetchAll();
} catch (Exception $e) {
    die("<div style='color:#f87171; background:#0b0f19; padding:24px; font-weight:bold; font-family:sans-serif; text-align:center;'>Database Connection Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Tab navigation setup ('complete' = Ready to receive, 'received' = Already logged)
$activeTab = $_GET['tab'] ?? 'complete';
$where = ["t.status = :tab_status"]; 
$params = ['tab_status' => $activeTab];

if (!empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $where[] = "(t.token_id LIKE :search OR t.invoice_no LIKE :search OR t.order_no LIKE :search OR s.supplier_name LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($_GET['token_date'])) {
    $where[] = "t.token_date = :token_date";
    $params['token_date'] = $_GET['token_date'];
}

if (!empty($_GET['branch_id'])) {
    $where[] = "t.branch_id = :branch_id";
    $params['branch_id'] = $_GET['branch_id'];
}

if (!empty($_GET['floor_id'])) {
    $where[] = "t.floor_id = :floor_id";
    $params['floor_id'] = $_GET['floor_id'];
}

$whereSql = implode(' AND ', $where);

// Limit to the last 10 received tokens when on the received tab
$limitSql = ($activeTab === 'received') ? "LIMIT 10" : "";

try {
    $stmt = $pdo->prepare("
        SELECT t.*, b.branch_name, f.floor_name, s.supplier_name 
        FROM tokens t
        LEFT JOIN branches b ON t.branch_id = b.branch_id
        LEFT JOIN floors f ON t.floor_id = f.floor_id
        LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id
        WHERE $whereSql
        ORDER BY t.updated_at DESC, t.created_at DESC
        $limitSql
    ");
    $stmt->execute($params);
    $tokens = $stmt->fetchAll();
} catch (Exception $e) {
    $tokens = [];
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Group | Token Ingestion & Receiving Center</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    },
                    colors: {
                        crimson: {
                            400: '#f87171',
                            500: '#ef4444',
                            600: '#dc2626',
                            700: '#b91c1c',
                            950: '#450a0a'
                        }
                    },
                    animation: {
                        'pulse-glow': 'pulseGlow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'modal-pop': 'modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards'
                    },
                    keyframes: {
                        pulseGlow: {
                            '0%, 100%': { opacity: '0.2', transform: 'scale(1)' },
                            '50%': { opacity: '0.45', transform: 'scale(1.05)' }
                        },
                        modalPop: {
                            '0%': { opacity: '0', transform: 'scale(0.94) translateY(12px)' },
                            '100%': { opacity: '1', transform: 'scale(1) translateY(0)' }
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    <style>
        .cyber-grid {
            background-image: radial-gradient(rgba(239, 68, 68, 0.08) 1px, transparent 0);
            background-size: 24px 24px;
        }

        .glass-panel {
            background: rgba(13, 17, 28, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(239, 68, 68, 0.18);
        }

        .glass-card {
            background: rgba(20, 26, 40, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        /* Hero Image Container with Glass Overlay */
        .hero-banner {
            background: linear-gradient(135deg, rgba(9, 13, 24, 0.95) 0%, rgba(225, 29, 72, 0.25) 50%, rgba(9, 13, 24, 0.95) 100%),
                        url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1600&q=80');
            background-size: cover;
            background-position: center;
        }

        /* Custom Scrollbar */
        .custom-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: #090d18;
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background: #283046;
            border-radius: 9999px;
        }
        .custom-scroll::-webkit-scrollbar-thumb:hover {
            background: #ef4444;
        }
    </style>
</head>

<body class="bg-[#05070d] text-slate-100 min-h-screen flex flex-col font-sans cyber-grid relative selection:bg-red-500/30" 
      x-data="receiverApp()"
      @keydown.escape.window="closeModal()">

    <!-- Ambient Glowing Nebulas -->
    <div class="fixed top-[-10%] left-1/4 w-[500px] h-[500px] bg-red-600/10 rounded-full blur-[120px] pointer-events-none -z-10 animate-pulse-glow"></div>
    <div class="fixed bottom-[-10%] right-1/4 w-[500px] h-[500px] bg-rose-700/10 rounded-full blur-[120px] pointer-events-none -z-10 animate-pulse-glow" style="animation-delay: 1.5s"></div>

    <!-- 1. Header Toolbar -->
    <header class="bg-[#090d18]/90 backdrop-blur-xl border-b border-red-500/20 px-4 sm:px-6 py-3 flex-shrink-0 z-30 shadow-2xl sticky top-0">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
            
            <!-- Brand & Station Identity -->
            <div class="flex items-center gap-3.5 w-full md:w-auto justify-between md:justify-start">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-red-600 via-rose-600 to-red-500 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-red-600/30 tracking-wider flex-shrink-0">
                        ASB
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="font-extrabold text-base sm:text-lg text-white tracking-tight">ASB Group Of Companies</h1>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-red-500/15 text-red-400 border border-red-500/30">
                                IT RECEIVING CENTER
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium">Step-by-Step Stock Ingestion & Verification Workstation</p>
                    </div>
                </div>

                <!-- Live Clock (Mobile View) -->
                <div class="md:hidden text-xs font-mono text-red-400 font-bold bg-[#05070d] px-2.5 py-1 rounded-lg border border-red-500/20">
                    <i class="fa-regular fa-clock mr-1"></i> <?php echo date("H:i"); ?>
                </div>
            </div>

            <!-- Header Action Controls & External Navigation Links -->
            <div class="flex items-center gap-2.5 w-full md:w-auto justify-end flex-wrap">
                
                <!-- Navigation Button: Back to Kiosk -->
                <a href="index.php" 
                   title="Go to Return Token Kiosk"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold border border-red-500/30 bg-red-500/10 hover:bg-red-500/20 hover:border-red-500/50 text-red-300 hover:text-white transition-all flex items-center gap-1.5 shadow-sm active:scale-95 cursor-pointer">
                    <i class="fa-solid fa-plus-circle text-red-400"></i>
                    <span>Generate Token</span>
                </a>

                <!-- Navigation Button: Back to Dashboard -->
                <a href="dashboard.php" 
                   title="Return to Dashboard"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold border border-slate-800 bg-[#0c101c] hover:bg-slate-800 hover:border-slate-700 text-slate-300 hover:text-white transition-all flex items-center gap-1.5 shadow-sm active:scale-95 cursor-pointer">
                    <i class="fa-solid fa-arrow-left text-red-400"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Live Digital Date Badge -->
                <div class="hidden sm:flex items-center gap-2 bg-[#05070d]/90 px-3.5 py-1.5 rounded-xl border border-red-500/20 font-mono text-xs text-right shadow-inner">
                    <i class="fa-regular fa-calendar-check text-red-400"></i>
                    <span class="text-slate-200 font-bold"><?php echo date("M j, Y"); ?></span>
                </div>
            </div>

        </div>
    </header>

    <!-- 2. Main Content Area -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-5 w-full flex-grow space-y-5">

        <!-- Visual Hero Ingestion Card Banner with Logistics Image -->
        <div class="hero-banner rounded-3xl p-5 sm:p-6 border border-red-500/20 shadow-2xl flex flex-col md:flex-row items-center justify-between gap-4 relative overflow-hidden">
            <div class="z-10 space-y-1 text-center md:text-left">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-500/20 border border-red-500/40 text-red-300 text-xs font-bold mb-1">
                    <i class="fa-solid fa-barcode text-red-400"></i>
                    <span>Warehouse Ingestion Hub</span>
                </div>
                <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">Stock Return Verification &amp; Ingestion</h2>
                <p class="text-xs text-slate-300 max-w-xl">Scan barcodes, verify vendor items with warehouse inventory, and authorize returns seamlessly with real-time audit trail logs.</p>
            </div>

            <!-- Quick Stats Badges -->
            <div class="flex items-center gap-3 z-10">
                <div class="glass-card px-4 py-2.5 rounded-2xl border border-white/10 text-center font-mono shadow-lg">
                    <span class="text-[10px] text-slate-400 block uppercase font-sans">Queue Status</span>
                    <strong class="text-emerald-400 text-sm flex items-center gap-1.5 justify-center">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Active
                    </strong>
                </div>
                <div class="glass-card px-4 py-2.5 rounded-2xl border border-white/10 text-center font-mono shadow-lg">
                    <span class="text-[10px] text-slate-400 block uppercase font-sans">Active Tab</span>
                    <strong class="text-red-400 text-sm uppercase"><?php echo htmlspecialchars($activeTab); ?></strong>
                </div>
            </div>
        </div>

        <!-- Dynamic Feedback Alerts -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'received_success'): ?>
                <div class="glass-panel border-emerald-500/40 text-emerald-300 p-4 rounded-2xl flex items-center gap-3.5 text-sm shadow-xl animate-modal-pop">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-xl flex-shrink-0">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <strong class="text-white text-base">Ingestion Successful!</strong> Token <strong class="text-emerald-300 font-mono">#<?php echo htmlspecialchars($_GET['token'] ?? ''); ?></strong> has been verified, marked as <span class="uppercase font-bold underline">Received</span>, and saved into the Ingested records.
                    </div>
                </div>
            <?php elseif ($_GET['msg'] === 'already_received'): ?>
                <div class="glass-panel border-amber-500/40 text-amber-300 p-4 rounded-2xl flex items-center gap-3.5 text-sm shadow-xl">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400 text-xl flex-shrink-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <strong class="text-white">Notice:</strong> Token <strong class="text-amber-300 font-mono">#<?php echo htmlspecialchars($_GET['token'] ?? ''); ?></strong> is already marked as received in the system.
                    </div>
                </div>
            <?php elseif ($_GET['msg'] === 'not_complete'): ?>
                <div class="glass-panel border-red-500/40 text-rose-300 p-4 rounded-2xl flex items-center gap-3.5 text-sm shadow-xl">
                    <div class="w-10 h-10 rounded-xl bg-red-500/20 border border-red-500/30 flex items-center justify-center text-red-400 text-xl flex-shrink-0">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div>
                        <strong class="text-white">Action Denied:</strong> Token <strong class="text-rose-300 font-mono">#<?php echo htmlspecialchars($_GET['token'] ?? ''); ?></strong> cannot be received (Current Status: <strong class="text-white uppercase"><?php echo htmlspecialchars($_GET['status'] ?? ''); ?></strong>).
                    </div>
                </div>
            <?php elseif ($_GET['msg'] === 'not_found'): ?>
                <div class="glass-panel border-red-500/40 text-rose-300 p-4 rounded-2xl flex items-center gap-3.5 text-sm shadow-xl">
                    <div class="w-10 h-10 rounded-xl bg-red-500/20 border border-red-500/30 flex items-center justify-center text-red-400 text-xl flex-shrink-0">
                        <i class="fa-solid fa-circle-exclamation"></i>
                    </div>
                    <div>
                        <strong class="text-white">Error:</strong> Token ID was not found in the database.
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- STEP 1: Search & Filter Toolbar Card -->
        <section class="glass-panel rounded-3xl p-5 shadow-2xl space-y-4">
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-3.5 border-b border-red-500/15 gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-xl bg-gradient-to-tr from-red-600 to-rose-600 text-white font-extrabold text-xs flex items-center justify-center shadow-md shadow-red-500/30">
                        1
                    </span>
                    <div>
                        <h2 class="text-sm sm:text-base font-bold text-white tracking-wide uppercase">Step 1: Search & Filter Ingestion Queue</h2>
                        <p class="text-xs text-slate-400">
                            <?php if ($activeTab === 'received'): ?>
                                Showing the <strong>Latest 10 Received Tokens</strong> logged in the system
                            <?php else: ?>
                                Filter pending stocks by keyword, date, facility branch, or floor
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Navigation Tabs with Active Badges -->
                <div class="flex items-center gap-1.5 bg-[#070a14] p-1.5 rounded-2xl border border-red-500/20 self-stretch sm:self-auto justify-center">
                    <a href="received.php?tab=complete" 
                       class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 <?php echo $activeTab === 'complete' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/30' : 'text-slate-400 hover:text-slate-200'; ?>">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Ready (Pending)</span>
                    </a>
                    <a href="received.php?tab=received" 
                       class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 <?php echo $activeTab === 'received' ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-md shadow-emerald-600/30' : 'text-slate-400 hover:text-slate-200'; ?>">
                        <i class="fa-solid fa-boxes-packing"></i>
                        <span>Ingested (Last 10 Received)</span>
                    </a>
                </div>
            </div>

            <!-- Filter Inputs Form -->
            <form method="GET" action="received.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">

                <!-- Search Query Field -->
                <div class="lg:col-span-4">
                    <label class="block text-xs font-extrabold text-slate-300 uppercase mb-1">Keywords / Scan Barcode</label>
                    <div class="relative">
                        <i class="fa-solid fa-barcode absolute left-3.5 top-3 text-red-400/80 text-sm"></i>
                        <input type="text" 
                               name="search" 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                               placeholder="Token ID, Invoice #, Order #, Supplier..." 
                               class="w-full pl-9 pr-3 py-2 bg-[#090d18] border border-[#283046] rounded-xl text-xs font-bold text-white placeholder:text-slate-600 focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 uppercase transition font-mono">
                    </div>
                </div>

                <!-- Token Date Field -->
                <div class="lg:col-span-2">
                    <label class="block text-xs font-extrabold text-slate-300 uppercase mb-1">Token Date</label>
                    <input type="date" 
                           name="token_date" 
                           value="<?php echo htmlspecialchars($_GET['token_date'] ?? ''); ?>" 
                           class="w-full px-3 py-2 bg-[#090d18] border border-[#283046] rounded-xl text-xs font-bold text-slate-200 focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 font-mono transition">
                </div>

                <!-- Branch Dropdown -->
                <div class="lg:col-span-3">
                    <label class="block text-xs font-extrabold text-slate-300 uppercase mb-1">Branch</label>
                    <select name="branch_id" class="w-full px-3 py-2 bg-[#090d18] border border-[#283046] rounded-xl text-xs font-bold text-slate-200 focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?php echo $br['branch_id']; ?>" <?php echo (($_GET['branch_id'] ?? '') == $br['branch_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($br['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Floor Dropdown -->
                <div class="lg:col-span-3">
                    <label class="block text-xs font-extrabold text-slate-300 uppercase mb-1">Floor Level</label>
                    <select name="floor_id" class="w-full px-3 py-2 bg-[#090d18] border border-[#283046] rounded-xl text-xs font-bold text-slate-200 focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition">
                        <option value="">All Floors</option>
                        <?php foreach ($floors as $fl): ?>
                            <option value="<?php echo $fl['floor_id']; ?>" <?php echo (($_GET['floor_id'] ?? '') == $fl['floor_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fl['floor_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Action Filter Buttons -->
                <div class="lg:col-span-12 flex justify-end gap-2.5 pt-1">
                    <a href="received.php?tab=<?php echo $activeTab; ?>" 
                       class="px-4 py-2 bg-[#0c101c] hover:bg-slate-800 text-slate-400 hover:text-white rounded-xl text-xs font-bold border border-slate-800 transition flex items-center gap-1.5 active:scale-95 cursor-pointer">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>Reset Filters</span>
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white rounded-xl text-xs font-black shadow-lg shadow-red-600/30 transition flex items-center gap-2 active:scale-95 cursor-pointer">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Filter Tokens (<?php echo count($tokens); ?>)</span>
                    </button>
                </div>

            </form>

        </section>

        <!-- STEP 2: Token Records Selection Grid -->
        <section class="glass-panel rounded-3xl p-5 shadow-2xl space-y-4">
            
            <div class="flex items-center justify-between pb-3.5 border-b border-red-500/15">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-xl bg-gradient-to-tr from-red-600 to-rose-600 text-white font-extrabold text-xs flex items-center justify-center shadow-md shadow-red-500/30">
                        2
                    </span>
                    <div>
                        <h2 class="text-sm sm:text-base font-bold text-white tracking-wide uppercase">Step 2: Token Manifest Queue</h2>
                        <p class="text-xs text-slate-400">
                            <?php if ($activeTab === 'received'): ?>
                                Displaying the last 10 received stock records
                            <?php else: ?>
                                Click any pending token to inspect and launch the ingestion verification window
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <span class="text-xs font-mono font-bold px-3 py-1 bg-[#070a14] rounded-xl border border-red-500/20 text-red-400">
                    <?php if ($activeTab === 'received'): ?>
                        <?php echo count($tokens); ?> / 10 Latest Received
                    <?php else: ?>
                        <?php echo count($tokens); ?> Records Found
                    <?php endif; ?>
                </span>
            </div>

            <!-- Token Grid -->
            <?php if (empty($tokens)): ?>
                <div class="text-center py-16 glass-card rounded-2xl border border-slate-800/80">
                    <img src="https://images.unsplash.com/photo-1553413077-190dd305871c?auto=format&fit=crop&w=400&q=80" 
                         alt="Empty Inventory" 
                         class="w-24 h-24 rounded-2xl object-cover mx-auto mb-3 opacity-60 border border-slate-700 shadow-lg">
                    <h3 class="text-base font-bold text-slate-300">No Token Records Found</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">There are no token records matching your current filter parameters in the <?php echo htmlspecialchars($activeTab); ?> tab.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($tokens as $row): ?>
                        <div class="glass-card rounded-2xl p-4 flex flex-col justify-between space-y-3.5 transition-all duration-200 hover:border-red-500/50 hover:shadow-lg hover:shadow-red-500/10 group">
                            
                            <!-- Card Header -->
                            <div>
                                <div class="flex justify-between items-start border-b border-slate-800 pb-2.5 mb-3">
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Token ID</span>
                                        <span class="text-lg font-black text-red-400 font-mono tracking-wider">#<?php echo htmlspecialchars($row['token_id']); ?></span>
                                    </div>
                                    <?php if ($row['status'] === 'received'): ?>
                                        <span class="px-2.5 py-1 bg-emerald-950/80 border border-emerald-500/40 text-emerald-400 text-[10px] font-black rounded-full flex items-center gap-1.5 shadow-sm">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> RECEIVED
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 bg-red-950/80 border border-red-500/40 text-red-300 text-[10px] font-black rounded-full flex items-center gap-1.5 shadow-sm">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span> READY
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Key Meta Rows -->
                                <div class="space-y-2 text-xs font-mono">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400 text-[11px] font-sans">Invoice Ref:</span>
                                        <span class="font-black text-white bg-slate-950 px-2 py-0.5 rounded border border-slate-800 text-xs"><?php echo htmlspecialchars($row['invoice_no']); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400 text-[11px] font-sans">Supplier:</span>
                                        <span class="text-rose-300 font-bold truncate max-w-[170px] text-right" title="<?php echo htmlspecialchars($row['supplier_name'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($row['supplier_name'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400 text-[11px] font-sans">Facility:</span>
                                        <span class="text-slate-300 font-semibold truncate max-w-[170px] text-right">
                                            <?php echo htmlspecialchars($row['branch_name'] ?? '-'); ?> (<?php echo htmlspecialchars($row['floor_name'] ?? '-'); ?>)
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400 text-[11px] font-sans">Date:</span>
                                        <span class="text-slate-400"><?php echo htmlspecialchars($row['token_date']); ?></span>
                                    </div>
                                    <?php if (!empty($row['processed_by'])): ?>
                                        <div class="flex justify-between items-center pt-1.5 border-t border-slate-800/80 text-emerald-400">
                                            <span class="text-[11px] font-sans text-slate-400">Received By:</span>
                                            <span class="font-bold flex items-center gap-1">
                                                <i class="fa-solid fa-user-check text-[10px]"></i> <?php echo htmlspecialchars($row['processed_by']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Card Action Button -->
                            <div class="pt-2 border-t border-slate-800/80">
                                <?php if ($row['status'] === 'received'): ?>
                                    <button disabled class="w-full py-2.5 bg-slate-950 border border-slate-800 text-slate-500 rounded-xl text-xs font-bold cursor-not-allowed flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-lock text-xs"></i>
                                        <span>Already Processed</span>
                                    </button>
                                <?php else: ?>
                                    <button @click="openProcessWindow(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                                            type="button"
                                            class="w-full py-2.5 bg-gradient-to-r from-red-600 via-rose-600 to-red-700 hover:from-red-500 hover:to-rose-500 text-white rounded-xl text-xs font-black transition-all shadow-md shadow-red-600/20 flex items-center justify-center gap-2 cursor-pointer active:scale-95">
                                        <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                                        <span>Open Ingestion Window</span>
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </section>

    </main>

    <!-- 3. STEP 3: DEDICATED INGESTION PROCESS WINDOW (MODAL DIALOG) -->
    <div x-show="isModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md overflow-y-auto"
         style="display: none;">
        
        <!-- Modal Backdrop Closer -->
        <div class="fixed inset-0" @click="closeModal()"></div>

        <!-- Modal Container Window -->
        <div class="relative w-full max-w-xl glass-panel rounded-3xl border-2 border-red-500/40 p-6 sm:p-7 shadow-2xl shadow-red-600/20 z-10 animate-modal-pop"
             @click.stop>
            
            <!-- Window Title Bar with Logistics Mini Thumbnail -->
            <div class="flex items-center justify-between pb-4 border-b border-red-500/20">
                <div class="flex items-center gap-3">
                    <img src="https://images.unsplash.com/photo-1578575437130-527eed3abbec?auto=format&fit=crop&w=120&q=80" 
                         alt="Logistics Scan" 
                         class="w-10 h-10 rounded-xl object-cover border border-red-500/30 shadow-md">
                    <div>
                        <h3 class="text-lg font-black text-white tracking-tight">Step 3: Verification & Ingestion Window</h3>
                        <p class="text-xs text-slate-400">Review stock payload and confirm receiver identity</p>
                    </div>
                </div>

                <!-- Window Close Button -->
                <button type="button" 
                        @click="closeModal()" 
                        class="w-8 h-8 rounded-xl bg-slate-900 border border-slate-700 hover:border-red-500 text-slate-400 hover:text-white flex items-center justify-center transition active:scale-95 cursor-pointer">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Modal Content & Form Body -->
            <template x-if="selectedToken">
                <form action="update_status.php" method="POST" @submit="submitProcess()" class="space-y-4 pt-4">
                    <input type="hidden" name="token_id" :value="selectedToken.token_id">

                    <!-- Token Telemetry Summary Strip -->
                    <div class="bg-[#080b15] border border-red-500/20 rounded-2xl p-4 space-y-2.5 font-mono text-xs shadow-inner">
                        <div class="flex items-center justify-between pb-2 border-b border-red-500/15">
                            <span class="text-slate-400 text-xs font-sans font-bold">Selected Token:</span>
                            <span class="font-black text-red-400 text-base" x-text="'#' + selectedToken.token_id"></span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-slate-200">
                            <div>
                                <span class="text-slate-500 text-[10px] block font-sans uppercase">Invoice Number:</span>
                                <strong class="text-white text-sm" x-text="selectedToken.invoice_no"></strong>
                            </div>
                            <div>
                                <span class="text-slate-500 text-[10px] block font-sans uppercase">Order Ref:</span>
                                <strong class="text-slate-300" x-text="selectedToken.order_no || 'N/A'"></strong>
                            </div>
                            <div class="col-span-2">
                                <span class="text-slate-500 text-[10px] block font-sans uppercase">Supplier / Vendor:</span>
                                <strong class="text-rose-400 font-bold truncate block" x-text="selectedToken.supplier_name || 'N/A'"></strong>
                            </div>
                            <div>
                                <span class="text-slate-500 text-[10px] block font-sans uppercase">Facility:</span>
                                <strong class="text-slate-300" x-text="(selectedToken.branch_name || '-') + ' (' + (selectedToken.floor_name || '-') + ')'"></strong>
                            </div>
                            <div>
                                <span class="text-slate-500 text-[10px] block font-sans uppercase">Current Status:</span>
                                <span class="inline-flex items-center gap-1 font-extrabold text-emerald-400 uppercase text-xs">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <span x-text="selectedToken.status"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Receiver ID Input -->
                    <div>
                        <label for="processed_by" class="block text-xs font-extrabold text-slate-300 uppercase mb-1">
                            Received By (Staff Name / ID) <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <i class="fa-solid fa-id-badge text-red-400"></i>
                            </div>
                            <input type="text" 
                                   name="processed_by" 
                                   id="processed_by" 
                                   x-ref="receiverInput"
                                   required 
                                   placeholder="e.g. David / IT-5012"
                                   class="w-full pl-10 pr-3.5 py-3 bg-[#090d18] border-2 border-[#283046] rounded-xl text-sm font-bold text-white placeholder:text-slate-600 focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition shadow-inner">
                        </div>
                    </div>

                    <!-- Modal Action Buttons -->
                    <div class="flex items-center gap-3 pt-3 border-t border-red-500/15">
                        <button type="button" 
                                @click="closeModal()" 
                                class="w-1/3 py-3 rounded-xl border border-slate-700 bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white font-bold text-xs transition active:scale-95 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" 
                                :disabled="submitting"
                                class="w-2/3 py-3 rounded-xl bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-sm transition-all shadow-xl shadow-emerald-600/30 flex items-center justify-center gap-2 active:scale-95 cursor-pointer disabled:opacity-50">
                            <template x-if="!submitting">
                                <span class="flex items-center gap-2">
                                    <i class="fa-solid fa-check-double text-base"></i>
                                    <span>CONFIRM &amp; LOG RECEIPT</span>
                                </span>
                            </template>
                            <template x-if="submitting">
                                <span class="flex items-center gap-2">
                                    <i class="fa-solid fa-circle-notch animate-spin text-base"></i>
                                    <span>PROCESSING INGESTION...</span>
                                </span>
                            </template>
                        </button>
                    </div>

                </form>
            </template>

        </div>

    </div>

    <!-- 4. Ultra-Slim Global Footer -->
    <footer class="bg-[#090d18]/90 backdrop-blur-md border-t border-red-500/20 px-6 py-2.5 flex-shrink-0 text-xs text-slate-400 mt-auto">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
            <p>&copy; <?php echo date("Y"); ?> <strong class="text-slate-200">ASB Group of Companies</strong> &bull; IT Department Stock Ingestion</p>
            <p>Designed &amp; Developed by <span class="text-red-400 font-bold">Vexel IT</span> by <span class="text-white font-extrabold">Kavizz</span></p>
        </div>
    </footer>

    <!-- Alpine.js Receiver Controller -->
    <script>
        function receiverApp() {
            return {
                isModalOpen: false,
                selectedToken: null,
                submitting: false,
                audioCtx: null,

                playTone(freq, type, duration) {
                    try {
                        if (!this.audioCtx) {
                            this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                        }
                        const osc = this.audioCtx.createOscillator();
                        const gain = this.audioCtx.createGain();
                        osc.type = type || 'sine';
                        osc.frequency.setValueAtTime(freq, this.audioCtx.currentTime);
                        gain.gain.setValueAtTime(0.05, this.audioCtx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.0001, this.audioCtx.currentTime + duration);
                        osc.connect(gain);
                        gain.connect(this.audioCtx.destination);
                        osc.start();
                        osc.stop(this.audioCtx.currentTime + duration);
                    } catch (e) {}
                },

                openProcessWindow(token) {
                    this.selectedToken = token;
                    this.isModalOpen = true;
                    this.playTone(600, 'sine', 0.05);
                    this.$nextTick(() => {
                        const input = document.getElementById('processed_by');
                        if (input) input.focus();
                    });
                },

                closeModal() {
                    this.isModalOpen = false;
                    this.selectedToken = null;
                    this.submitting = false;
                    this.playTone(320, 'sine', 0.04);
                },

                submitProcess() {
                    this.submitting = true;
                    this.playTone(523.25, 'triangle', 0.08);
                    setTimeout(() => this.playTone(659.25, 'triangle', 0.08), 70);
                }
            }
        }
    </script>
</body>
</html>