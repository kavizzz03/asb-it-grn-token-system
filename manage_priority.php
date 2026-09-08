<?php
require_once 'db.php';

date_default_timezone_set('Asia/Colombo');

try {
    $companies = $pdo->query("SELECT company_id, company_name FROM companies WHERE status='active' ORDER BY company_name ASC")->fetchAll();
    $branches  = $pdo->query("SELECT branch_id, branch_name FROM branches WHERE status='active' ORDER BY branch_name ASC")->fetchAll();
    $floors    = $pdo->query("SELECT floor_id, floor_name FROM floors ORDER BY floor_id ASC")->fetchAll();
    $suppliers = $pdo->query("SELECT supplier_id, supplier_name FROM suppliers WHERE status='active' OR status IS NULL ORDER BY supplier_name ASC")->fetchAll();
} catch (Exception $e) {
    die("<div style='color:#ef4444; background:#0b0f19; padding:24px; font-weight:bold; font-family:sans-serif; text-align:center;'>Database Connection Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Group IT | Priority Queue Management</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    <style>
        .cyber-grid {
            background-image: radial-gradient(rgba(239, 68, 68, 0.08) 1px, transparent 0);
            background-size: 24px 24px;
        }

        .glass-panel {
            background: rgba(13, 17, 28, 0.88);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(239, 68, 68, 0.18);
        }

        .glass-card {
            background: rgba(20, 26, 40, 0.65);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #090d18; }
        ::-webkit-scrollbar-thumb { background: #283046; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #ef4444; }
        
        select:disabled {
            opacity: 0.8;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-[#05070d] text-slate-100 min-h-screen flex flex-col justify-between antialiased cyber-grid relative selection:bg-red-500/30" 
      x-data="priorityManager()">

    <div class="fixed top-[-10%] left-1/4 w-[500px] h-[500px] bg-red-600/10 rounded-full blur-[120px] pointer-events-none -z-10 animate-pulse-glow"></div>
    <div class="fixed bottom-[-10%] right-1/4 w-[500px] h-[500px] bg-rose-700/10 rounded-full blur-[120px] pointer-events-none -z-10 animate-pulse-glow" style="animation-delay: 1.5s"></div>

    <!-- Header -->
    <header class="bg-[#090d18]/90 backdrop-blur-xl border-b border-red-500/20 px-4 sm:px-6 py-3 flex-shrink-0 z-30 shadow-2xl sticky top-0">
        <div class="max-w-[1680px] mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3.5 w-full md:w-auto justify-between md:justify-start">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-red-600 via-rose-600 to-red-500 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-red-600/30 tracking-wider flex-shrink-0">
                        ASB
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="font-extrabold text-base sm:text-lg text-white tracking-tight">ASB Group Of Companies</h1>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-red-500/15 text-red-400 border border-red-500/30 flex items-center gap-1">
                                <i class="fa-solid fa-arrows-up-down"></i> PRIORITY MANAGEMENT
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium">Priority Queue Management &amp; Status View</p>
                    </div>
                </div>
                <div class="hidden lg:flex items-center gap-2 bg-[#05070d]/90 px-3 py-1.5 rounded-xl border border-red-500/20 text-xs font-mono">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-slate-400">Live Status: <strong class="text-emerald-300">View-Only Mode</strong></span>
                </div>
            </div>

            <div class="flex items-center gap-2.5 w-full md:w-auto justify-end flex-wrap">
                <a href="dashboard.php" 
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold border border-slate-800 bg-[#0c101c] hover:bg-slate-800 hover:border-slate-700 text-slate-300 hover:text-white transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                    <i class="fa-solid fa-desktop text-red-400"></i> Return Dashboard
                </a>
                <a href="received.php" 
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold border border-red-500/30 bg-red-500/10 hover:bg-red-500/20 hover:border-red-500/50 text-red-300 hover:text-white transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                    <i class="fa-solid fa-boxes-packing text-red-400"></i> Ingestion Hub
                </a>
                <a href="tv_display.php" target="_blank" 
                   class="px-3.5 py-1.5 rounded-xl text-xs font-black bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white transition-all flex items-center gap-1.5 shadow-lg shadow-red-600/30 active:scale-95">
                    <i class="fa-solid fa-tv"></i> Live TV Board
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[1680px] w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-5 flex-grow">
        
        <!-- KPI Summary -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
            <div @click="switchTab('all_pending')" 
                 class="glass-card rounded-2xl p-4 cursor-pointer transition border border-red-500/20 hover:border-red-500/50 hover:bg-[#0f1422] shadow-lg flex items-center justify-between"
                 :class="activeTab === 'all_pending' ? 'ring-2 ring-red-500/50 bg-[#121829]' : ''">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Active Queue</span>
                    <h3 class="text-2xl font-black font-mono text-white" x-text="getTabCount('all_pending')">0</h3>
                    <p class="text-[10px] text-slate-500 font-medium">Pending + Process</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-400 text-lg">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>

            <div @click="switchTab('pending')" 
                 class="glass-card rounded-2xl p-4 cursor-pointer transition border border-amber-500/20 hover:border-amber-500/50 hover:bg-[#0f1422] shadow-lg flex items-center justify-between"
                 :class="activeTab === 'pending' ? 'ring-2 ring-amber-500/50 bg-[#121829]' : ''">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-400/80 block mb-1">Waiting</span>
                    <h3 class="text-2xl font-black font-mono text-amber-400" x-text="getTabCount('pending')">0</h3>
                    <p class="text-[10px] text-slate-500 font-medium">Status: pending</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 text-lg">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>

            <div @click="switchTab('process')" 
                 class="glass-card rounded-2xl p-4 cursor-pointer transition border border-sky-500/20 hover:border-sky-500/50 hover:bg-[#0f1422] shadow-lg flex items-center justify-between"
                 :class="activeTab === 'process' ? 'ring-2 ring-sky-500/50 bg-[#121829]' : ''">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-sky-400/80 block mb-1">In Process</span>
                    <h3 class="text-2xl font-black font-mono text-sky-400" x-text="getTabCount('process')">0</h3>
                    <p class="text-[10px] text-slate-500 font-medium">Status: process</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/30 flex items-center justify-center text-sky-400 text-lg">
                    <i class="fa-solid fa-spinner" :class="getTabCount('process') > 0 ? 'animate-spin' : ''"></i>
                </div>
            </div>

            <div @click="switchTab('complete')" 
                 class="glass-card rounded-2xl p-4 cursor-pointer transition border border-emerald-500/20 hover:border-emerald-500/50 hover:bg-[#0f1422] shadow-lg flex items-center justify-between"
                 :class="activeTab === 'complete' ? 'ring-2 ring-emerald-500/50 bg-[#121829]' : ''">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-400/80 block mb-1">Completed</span>
                    <h3 class="text-2xl font-black font-mono text-emerald-400" x-text="getTabCount('complete')">0</h3>
                    <p class="text-[10px] text-slate-500 font-medium">View only</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-lg">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>

            <div @click="switchTab('received')" 
                 class="glass-card rounded-2xl p-4 cursor-pointer transition border border-purple-500/20 hover:border-purple-500/50 hover:bg-[#0f1422] shadow-lg flex items-center justify-between"
                 :class="activeTab === 'received' ? 'ring-2 ring-purple-500/50 bg-[#121829]' : ''">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-purple-400/80 block mb-1">Received</span>
                    <h3 class="text-2xl font-black font-mono text-purple-400" x-text="getTabCount('received')">0</h3>
                    <p class="text-[10px] text-slate-500 font-medium">View only</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-400 text-lg">
                    <i class="fa-solid fa-box"></i>
                </div>
            </div>

            <div @click="switchTab('top_priority')" 
                 class="glass-card rounded-2xl p-4 cursor-pointer transition border border-rose-500/20 hover:border-rose-500/50 hover:bg-[#0f1422] shadow-lg flex items-center justify-between"
                 :class="activeTab === 'top_priority' ? 'ring-2 ring-rose-500/50 bg-[#121829]' : ''">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-400/80 block mb-1">Top Priority</span>
                    <h3 class="text-2xl font-black font-mono text-rose-400" x-text="getTabCount('top_priority')">0</h3>
                    <p class="text-[10px] text-slate-500 font-medium">Rank #1 active</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-400 text-lg">
                    <i class="fa-solid fa-fire text-rose-500"></i>
                </div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="glass-panel rounded-3xl p-5 shadow-2xl space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
                <div class="lg:col-span-4 relative" @click.away="suggestions = []">
                    <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-300 mb-1">
                        <i class="fa-solid fa-magnifying-glass text-red-400 mr-1"></i> Search Records
                    </label>
                    <div class="relative">
                        <input type="text" 
                               x-model="searchQuery" 
                               @input="fetchSuggestions(); loadPendingTokens();" 
                               placeholder="Search Vendor, Token ID, Invoice, Order, Company..."
                               class="w-full bg-[#090d18] border border-[#283046] focus:border-red-500 focus:ring-2 focus:ring-red-500/20 rounded-xl pl-9 pr-9 py-2.5 text-xs font-bold text-white placeholder-slate-600 focus:outline-none transition-all shadow-inner font-mono">
                        <i class="fa-solid fa-barcode absolute left-3.5 top-3 text-red-400/80 text-sm"></i>
                        <button x-show="searchQuery" 
                                @click="searchQuery = ''; fetchSuggestions(); loadPendingTokens();" 
                                class="absolute right-3 top-3 text-xs text-slate-500 hover:text-white">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div x-show="suggestions.length > 0" 
                         class="absolute z-50 left-0 right-0 mt-1 bg-[#090d18] border border-red-500/30 rounded-2xl shadow-2xl max-h-64 overflow-y-auto divide-y divide-slate-800/80 p-1.5"
                         style="display: none;">
                        <template x-for="item in suggestions" :key="item.token_id">
                            <div @click="selectInvoice(item)" class="p-2.5 hover:bg-red-500/15 rounded-xl cursor-pointer transition flex justify-between items-center text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="bg-[#05070d] text-red-400 px-2 py-0.5 rounded font-mono text-[10px] font-bold border border-red-500/30" x-text="item.token_id"></span>
                                    <span class="font-bold text-white font-mono" x-text="item.invoice_no"></span>
                                    <span class="text-rose-300 text-[11px] font-semibold" x-text="item.supplier_name"></span>
                                </div>
                                <div class="flex items-center gap-2.5 font-mono text-[11px]">
                                    <span class="text-slate-500" x-text="item.token_date"></span>
                                    <span class="bg-red-500/20 text-red-300 px-2 py-0.5 rounded-full font-bold" x-text="'Rank #' + (item.priority_no || '—')"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="lg:col-span-8 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-300 mb-1">Company</label>
                        <select x-model="filterCompany" @change="loadPendingTokens()" class="w-full bg-[#090d18] border border-[#283046] focus:border-red-500 focus:ring-2 focus:ring-red-500/20 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-200 focus:outline-none transition">
                            <option value="">All Companies</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?php echo $c['company_id']; ?>"><?php echo htmlspecialchars($c['company_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-300 mb-1">Branch</label>
                        <select x-model="filterBranch" @change="loadPendingTokens()" class="w-full bg-[#090d18] border border-[#283046] focus:border-red-500 focus:ring-2 focus:ring-red-500/20 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-200 focus:outline-none transition">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-300 mb-1">Floor</label>
                        <select x-model="filterFloor" @change="loadPendingTokens()" class="w-full bg-[#090d18] border border-[#283046] focus:border-red-500 focus:ring-2 focus:ring-red-500/20 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-200 focus:outline-none transition">
                            <option value="">All Floors</option>
                            <?php foreach ($floors as $f): ?>
                                <option value="<?php echo $f['floor_id']; ?>"><?php echo htmlspecialchars($f['floor_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-300 mb-1">Supplier</label>
                        <select x-model="filterVendor" @change="loadPendingTokens()" class="w-full bg-[#090d18] border border-[#283046] focus:border-red-500 focus:ring-2 focus:ring-red-500/20 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-200 focus:outline-none transition">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo $s['supplier_id']; ?>"><?php echo htmlspecialchars($s['supplier_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div x-show="toastMessage" 
             x-transition 
             class="glass-panel border-emerald-500/40 text-emerald-300 px-5 py-3 rounded-2xl text-xs font-bold flex items-center justify-between shadow-xl"
             style="display: none;">
            <span class="flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-emerald-400 text-base"></i> 
                <span x-text="toastMessage"></span>
            </span>
            <button @click="toastMessage = ''" class="text-emerald-400 hover:text-white text-base">&times;</button>
        </div>

        <!-- Main Table -->
        <div class="glass-panel rounded-3xl border border-red-500/20 shadow-2xl overflow-hidden flex flex-col">
            
            <!-- Tabs -->
            <div class="px-5 py-4 bg-[#090d18]/95 border-b border-red-500/20 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <button @click="switchTab('all_pending')" 
                            class="px-3.5 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 cursor-pointer active:scale-95 shadow-md"
                            :class="activeTab === 'all_pending' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-red-600/30 ring-2 ring-red-400/40' : 'bg-[#0c101c] text-slate-400 hover:text-white border border-slate-800'">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>Active Queue</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                              :class="activeTab === 'all_pending' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400'"
                              x-text="getTabCount('all_pending')"></span>
                    </button>
                    <button @click="switchTab('pending')" 
                            class="px-3.5 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 cursor-pointer active:scale-95 shadow-md"
                            :class="activeTab === 'pending' ? 'bg-gradient-to-r from-amber-600 to-amber-500 text-white shadow-amber-600/30 ring-2 ring-amber-400/40' : 'bg-[#0c101c] text-slate-400 hover:text-white border border-slate-800'">
                        <i class="fa-solid fa-clock"></i>
                        <span>Pending</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                              :class="activeTab === 'pending' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400'"
                              x-text="getTabCount('pending')"></span>
                    </button>
                    <button @click="switchTab('process')" 
                            class="px-3.5 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 cursor-pointer active:scale-95 shadow-md"
                            :class="activeTab === 'process' ? 'bg-gradient-to-r from-sky-600 to-blue-600 text-white shadow-sky-600/30 ring-2 ring-sky-400/40' : 'bg-[#0c101c] text-slate-400 hover:text-white border border-slate-800'">
                        <i class="fa-solid fa-spinner" :class="activeTab === 'process' ? 'animate-spin' : ''"></i>
                        <span>In Process</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                              :class="activeTab === 'process' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400'"
                              x-text="getTabCount('process')"></span>
                    </button>
                    <button @click="switchTab('complete')" 
                            class="px-3.5 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 cursor-pointer active:scale-95 shadow-md"
                            :class="activeTab === 'complete' ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-emerald-600/30 ring-2 ring-emerald-400/40' : 'bg-[#0c101c] text-slate-400 hover:text-white border border-slate-800'">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Completed</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                              :class="activeTab === 'complete' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400'"
                              x-text="getTabCount('complete')"></span>
                    </button>
                    <button @click="switchTab('received')" 
                            class="px-3.5 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 cursor-pointer active:scale-95 shadow-md"
                            :class="activeTab === 'received' ? 'bg-gradient-to-r from-purple-600 to-violet-600 text-white shadow-purple-600/30 ring-2 ring-purple-400/40' : 'bg-[#0c101c] text-slate-400 hover:text-white border border-slate-800'">
                        <i class="fa-solid fa-box"></i>
                        <span>Received</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                              :class="activeTab === 'received' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400'"
                              x-text="getTabCount('received')"></span>
                    </button>
                    <button @click="switchTab('top_priority')" 
                            class="px-3.5 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 cursor-pointer active:scale-95 shadow-md"
                            :class="activeTab === 'top_priority' ? 'bg-gradient-to-r from-rose-600 to-red-600 text-white shadow-red-600/30 ring-2 ring-red-400/40' : 'bg-[#0c101c] text-slate-400 hover:text-white border border-slate-800'">
                        <i class="fa-solid fa-fire text-red-400"></i>
                        <span>Top #1</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                              :class="activeTab === 'top_priority' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400'"
                              x-text="getTabCount('top_priority')"></span>
                    </button>
                </div>

                <div class="flex items-center gap-2.5 w-full lg:w-auto justify-between lg:justify-end">
                    <span class="text-xs font-mono font-bold px-3 py-1.5 bg-[#05070d] rounded-xl border border-red-500/20 text-red-400" 
                          x-text="filteredTokens.length + ' Records'"></span>
                    <button @click="loadPendingTokens()" 
                            class="bg-[#0c101c] hover:bg-slate-800 text-slate-300 hover:text-white text-xs font-extrabold px-3.5 py-1.5 rounded-xl border border-slate-800 transition flex items-center gap-1.5 shadow-sm active:scale-95 cursor-pointer">
                        <i class="fa-solid fa-rotate-right" :class="loading ? 'animate-spin text-red-400' : ''"></i> 
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#080b15] text-slate-400 text-[11px] uppercase font-black border-b border-red-500/15 tracking-wider font-mono">
                            <th class="py-3.5 px-4 text-center w-16">Rank</th>
                            <th class="py-3.5 px-4 w-28">Token ID</th>
                            <th class="py-3.5 px-4">Invoice No</th>
                            <th class="py-3.5 px-4">Order Ref</th>
                            <th class="py-3.5 px-4">Vendor / Supplier</th>
                            <th class="py-3.5 px-4">Company / Branch / Floor</th>
                            <th class="py-3.5 px-4">Token Date</th>
                            <th class="py-3.5 px-4 text-center min-w-[170px]">Status (View Only)</th>
                            <th class="py-3.5 px-4">Created / Processed By</th>
                            <th class="py-3.5 px-4 text-center w-52">Priority Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-xs">
                        <template x-for="(item, index) in filteredTokens" :key="item.token_id">
                            <tr class="hover:bg-slate-800/40 transition-colors" :class="item.token_id === highlightedTokenId ? 'bg-red-500/10 border-l-4 border-red-500' : ''">
                                
                                <!-- Rank -->
                                <td class="py-3 px-4 text-center">
                                    <template x-if="item.status !== 'complete' && item.status !== 'received'">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl font-black font-mono text-xs shadow-md"
                                            :class="{
                                                'bg-gradient-to-tr from-red-600 to-rose-600 text-white shadow-red-600/30 ring-2 ring-red-400/40 animate-pulse': item.priority_no == 1,
                                                'bg-gradient-to-tr from-amber-600 to-amber-500 text-white shadow-amber-600/30': item.priority_no == 2,
                                                'bg-gradient-to-tr from-sky-600 to-blue-600 text-white shadow-sky-600/30': item.priority_no == 3,
                                                'bg-[#090d18] text-slate-300 border border-slate-700': item.priority_no > 3 || !item.priority_no
                                            }"
                                            x-text="item.priority_no ? '#' + item.priority_no : '—'">
                                        </span>
                                    </template>
                                    <template x-if="item.status === 'complete' || item.status === 'received'">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl font-black font-mono text-xs bg-[#090d18] text-slate-500 border border-slate-700">
                                            —
                                        </span>
                                    </template>
                                </td>

                                <!-- Token ID -->
                                <td class="py-3 px-4">
                                    <span class="font-mono font-bold text-red-400 bg-red-500/10 px-2 py-1 rounded-lg border border-red-500/30 text-[11px]" x-text="item.token_id"></span>
                                </td>

                                <!-- Invoice -->
                                <td class="py-3 px-4 font-mono font-black text-white text-sm" x-text="item.invoice_no"></td>
                                
                                <!-- Order -->
                                <td class="py-3 px-4 font-mono text-slate-400" x-text="item.order_no || '—'"></td>
                                
                                <!-- Supplier -->
                                <td class="py-3 px-4">
                                    <div class="font-bold text-rose-300 text-xs" x-text="item.supplier_name || 'N/A'"></div>
                                    <div class="text-[10px] text-slate-500 font-mono" x-show="item.contact_number" x-text="'Tel: ' + item.contact_number"></div>
                                </td>

                                <!-- Company/Branch/Floor -->
                                <td class="py-3 px-4 text-slate-400">
                                    <div class="text-white font-semibold text-xs" x-text="item.company_name || 'ASB'"></div>
                                    <div class="text-[11px] text-slate-400">
                                        <span x-text="item.branch_name"></span> 
                                        <span class="text-slate-600 mx-1">/</span>
                                        <span class="font-mono" x-text="item.floor_name"></span>
                                    </div>
                                </td>

                                <!-- Date -->
                                <td class="py-3 px-4">
                                    <span class="font-mono text-slate-300 bg-[#090d18] px-2 py-1 rounded border border-slate-800 text-[11px] flex items-center gap-1.5 w-fit">
                                        <i class="fa-regular fa-calendar text-red-400/70 text-[10px]"></i>
                                        <span x-text="item.token_date"></span>
                                    </span>
                                </td>

                                <!-- Status (View Only - Disabled) -->
                                <td class="py-3 px-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <select :value="item.status || 'pending'" 
                                                disabled
                                                class="bg-[#090d18] text-xs font-black rounded-xl px-2.5 py-1.5 border focus:outline-none cursor-not-allowed shadow-sm uppercase font-mono opacity-80"
                                                :class="{
                                                    'text-amber-400 border-amber-500/40 bg-amber-500/10': item.status === 'pending' || !item.status,
                                                    'text-sky-400 border-sky-500/40 bg-sky-500/10': item.status === 'process',
                                                    'text-emerald-400 border-emerald-500/40 bg-emerald-500/10': item.status === 'complete',
                                                    'text-purple-400 border-purple-500/40 bg-purple-500/10': item.status === 'received'
                                                }">
                                            <option value="pending" class="bg-[#0c101c] text-amber-400">⏳ Pending</option>
                                            <option value="process" class="bg-[#0c101c] text-sky-400">⚙️ In Process</option>
                                            <option value="complete" class="bg-[#0c101c] text-emerald-400">✅ Complete</option>
                                            <option value="received" class="bg-[#0c101c] text-purple-400">📥 Received</option>
                                        </select>
                                        <span class="text-[10px] text-slate-500 font-mono" title="Status is view-only">🔒</span>
                                    </div>
                                </td>

                                <!-- Created/Processed By -->
                                <td class="py-3 px-4 font-mono text-[11px] text-slate-400">
                                    <div><span class="text-slate-500">By:</span> <span class="text-slate-300" x-text="item.created_by || 'Kiosk'"></span></div>
                                    <div x-show="item.processed_by"><span class="text-slate-500">QC:</span> <span class="text-rose-300" x-text="item.processed_by"></span></div>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4 text-center">
                                    <template x-if="item.status !== 'complete' && item.status !== 'received'">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button @click="updatePriority(item.token_id, 1, item.status)" 
                                                    :disabled="item.priority_no == 1"
                                                    class="bg-red-500/15 hover:bg-red-600 disabled:opacity-30 text-red-400 hover:text-white px-2 py-1 rounded-lg text-[11px] font-bold border border-red-500/30 transition active:scale-95 cursor-pointer">
                                                Top #1
                                            </button>
                                            <button @click="openPriorityModal(item)"
                                                    class="bg-slate-800 hover:bg-red-600/30 text-slate-300 hover:text-white px-2.5 py-1 rounded-lg text-[11px] font-bold border border-slate-700 hover:border-red-500/40 transition active:scale-95 cursor-pointer">
                                                Edit Rank
                                            </button>
                                            <button @click="updatePriority(item.token_id, getActiveCount(), item.status)" 
                                                    :disabled="item.priority_no == getActiveCount()"
                                                    class="bg-slate-900 hover:bg-slate-800 disabled:opacity-30 text-slate-400 hover:text-white px-2 py-1 rounded-lg text-[11px] font-bold border border-slate-800 transition active:scale-95 cursor-pointer">
                                                Bottom
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="item.status === 'complete' || item.status === 'received'">
                                        <span class="text-xs text-slate-500 font-mono italic">No actions available</span>
                                    </template>
                                </td>

                            </tr>
                        </template>

                        <!-- Empty State -->
                        <template x-if="filteredTokens.length === 0 && !loading">
                            <tr>
                                <td colspan="10" class="py-16 text-center text-slate-500 font-semibold">
                                    <div class="w-16 h-16 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-600 text-2xl mx-auto mb-3 shadow-inner">
                                        <i class="fa-solid fa-inbox"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-slate-300">No Records Found</h4>
                                    <p class="text-xs text-slate-500 mt-1">There are no records in "<span x-text="activeTab"></span>" matching your filters.</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Priority Modal -->
        <div x-show="modalOpen" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4"
             style="display: none;">
            
            <div @click.away="modalOpen = false" 
                 class="glass-panel border-2 border-red-500/40 rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4 animate-modal-pop">
                
                <div class="flex justify-between items-center border-b border-red-500/20 pb-3">
                    <h3 class="font-black text-sm text-white flex items-center gap-2">
                        <i class="fa-solid fa-sliders text-red-400"></i> Update Priority Rank
                    </h3>
                    <button @click="modalOpen = false" class="text-slate-400 hover:text-white text-lg">&times;</button>
                </div>

                <div class="space-y-3.5" x-show="selectedItem">
                    <div class="bg-[#080b15] p-3.5 rounded-2xl border border-red-500/20 text-xs space-y-1.5 font-mono shadow-inner">
                        <div class="flex justify-between"><span class="text-slate-400 font-sans">Token ID:</span><span class="font-bold text-red-400" x-text="selectedItem?.token_id"></span></div>
                        <div class="flex justify-between"><span class="text-slate-400 font-sans">Company:</span><span class="font-bold text-white" x-text="selectedItem?.company_name || 'ASB'"></span></div>
                        <div class="flex justify-between"><span class="text-slate-400 font-sans">Vendor:</span><span class="font-bold text-rose-300" x-text="selectedItem?.supplier_name"></span></div>
                        <div class="flex justify-between"><span class="text-slate-400 font-sans">Invoice:</span><span class="font-bold text-white" x-text="selectedItem?.invoice_no"></span></div>
                        <div class="flex justify-between"><span class="text-slate-400 font-sans">Date:</span><span class="font-bold text-slate-300" x-text="selectedItem?.token_date"></span></div>
                        <div class="flex justify-between"><span class="text-slate-400 font-sans">Status:</span>
                            <span class="font-bold" :class="{
                                'text-amber-400': selectedItem?.status === 'pending' || !selectedItem?.status,
                                'text-sky-400': selectedItem?.status === 'process',
                                'text-emerald-400': selectedItem?.status === 'complete',
                                'text-purple-400': selectedItem?.status === 'received'
                            }" x-text="selectedItem?.status || 'pending'"></span>
                        </div>
                        <div class="flex justify-between"><span class="text-slate-400 font-sans">Current Rank:</span><span class="font-bold text-amber-400" x-text="selectedItem?.priority_no ? '#' + selectedItem?.priority_no : 'Unassigned'"></span></div>
                    </div>

                    <div class="bg-[#0c101c] p-2.5 rounded-xl border border-slate-800 text-xs text-slate-400">
                        <i class="fa-solid fa-info-circle text-slate-500 mr-1"></i> 
                        Status cannot be changed. Only priority rank can be adjusted.
                    </div>

                    <div>
                        <label class="block text-xs font-extrabold uppercase text-slate-300 mb-1.5">Assign Target Rank</label>
                        <div class="grid grid-cols-3 gap-2 mb-3">
                            <button @click="priorityTypeMode = 'top'; targetPriority = 1;"
                                :class="priorityTypeMode === 'top' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/30 ring-2 ring-red-400/40' : 'bg-slate-900 text-slate-400 border border-slate-800'"
                                class="py-2 rounded-xl text-xs font-bold transition cursor-pointer">
                                Top #1
                            </button>
                            <button @click="priorityTypeMode = 'custom'"
                                :class="priorityTypeMode === 'custom' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-red-600/30 ring-2 ring-red-400/40' : 'bg-slate-900 text-slate-400 border border-slate-800'"
                                class="py-2 rounded-xl text-xs font-bold transition cursor-pointer">
                                Specific
                            </button>
                            <button @click="priorityTypeMode = 'bottom'; targetPriority = getActiveCount();"
                                :class="priorityTypeMode === 'bottom' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-red-600/30 ring-2 ring-red-400/40' : 'bg-slate-900 text-slate-400 border border-slate-800'"
                                class="py-2 rounded-xl text-xs font-bold transition cursor-pointer">
                                Bottom
                            </button>
                        </div>

                        <div x-show="priorityTypeMode === 'custom'">
                            <select x-model="targetPriority" class="w-full bg-[#090d18] border border-[#283046] rounded-xl p-2.5 text-xs font-bold text-red-400 focus:outline-none font-mono">
                                <template x-for="p in Math.max(getActiveCount(), 1)" :key="p">
                                    <option :value="p" x-text="'Rank #' + p" :selected="p == selectedItem?.priority_no"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2.5 pt-3 border-t border-red-500/20">
                        <button @click="modalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white bg-slate-900 border border-slate-800 cursor-pointer">Cancel</button>
                        <button @click="confirmPriorityChange()" class="bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white px-5 py-2 rounded-xl text-xs font-black transition shadow-lg shadow-red-600/30 cursor-pointer">Save Priority</button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-[#090d18]/90 backdrop-blur-md border-t border-red-500/20 py-3 text-center text-xs text-slate-400">
        <p>&copy; <?php echo date("Y"); ?> <strong class="text-slate-200">ASB Group of Companies</strong> &bull; Priority Queue Management | Designed &amp; Developed by <span class="text-red-400 font-bold">Vexel IT</span> by <span class="text-white font-extrabold">Kavizz</span></p>
    </footer>

    <!-- Alpine.js Controller -->
    <script>
        function priorityManager() {
            return {
                tokens: [],
                suggestions: [],
                searchQuery: '',
                filterCompany: '',
                filterBranch: '',
                filterFloor: '',
                filterVendor: '',
                activeTab: 'all_pending',
                loading: false,
                toastMessage: '',
                highlightedTokenId: null,

                modalOpen: false,
                selectedItem: null,
                priorityTypeMode: 'custom',
                targetPriority: 1,
                targetOperator: 'QC Supervisor',

                init() {
                    this.loadPendingTokens();
                    setInterval(() => {
                        if (!this.modalOpen) {
                            this.loadPendingTokens(false);
                        }
                    }, 20000);
                },

                get filteredTokens() {
                    let list = this.tokens;
                    if (this.activeTab === 'all_pending') {
                        list = list.filter(t => t.status === 'pending' || t.status === 'process' || !t.status);
                    } else if (this.activeTab === 'pending') {
                        list = list.filter(t => t.status === 'pending' || !t.status);
                    } else if (this.activeTab === 'process') {
                        list = list.filter(t => t.status === 'process');
                    } else if (this.activeTab === 'complete') {
                        list = list.filter(t => t.status === 'complete');
                    } else if (this.activeTab === 'received') {
                        list = list.filter(t => t.status === 'received');
                    } else if (this.activeTab === 'top_priority') {
                        list = list.filter(t => t.priority_no == 1 && t.status !== 'complete' && t.status !== 'received');
                    }
                    return list;
                },

                getActiveCount() {
                    return this.tokens.filter(t => t.status !== 'complete' && t.status !== 'received').length;
                },

                getTabCount(tabName) {
                    if (tabName === 'all_pending') {
                        return this.tokens.filter(t => t.status === 'pending' || t.status === 'process' || !t.status).length;
                    } else if (tabName === 'pending') {
                        return this.tokens.filter(t => t.status === 'pending' || !t.status).length;
                    } else if (tabName === 'process') {
                        return this.tokens.filter(t => t.status === 'process').length;
                    } else if (tabName === 'complete') {
                        return this.tokens.filter(t => t.status === 'complete').length;
                    } else if (tabName === 'received') {
                        return this.tokens.filter(t => t.status === 'received').length;
                    } else if (tabName === 'top_priority') {
                        return this.tokens.filter(t => t.priority_no == 1 && t.status !== 'complete' && t.status !== 'received').length;
                    }
                    return this.tokens.length;
                },

                switchTab(tab) {
                    this.activeTab = tab;
                },

                async loadPendingTokens(showSpinner = true) {
                    if (showSpinner) this.loading = true;
                    try {
                        const params = new URLSearchParams({
                            company_id: this.filterCompany,
                            branch_id: this.filterBranch,
                            floor_id: this.filterFloor,
                            supplier_id: this.filterVendor,
                            q: this.searchQuery
                        });
                        const response = await fetch(`api_get_pending_tokens.php?${params.toString()}`);
                        const data = await response.json();
                        if (data.success && Array.isArray(data.results)) {
                            this.tokens = data.results;
                        }
                    } catch (err) {
                        console.error('Error fetching tokens:', err);
                    } finally {
                        if (showSpinner) this.loading = false;
                    }
                },

                async fetchSuggestions() {
                    if (this.searchQuery.trim().length < 1) {
                        this.suggestions = [];
                        return;
                    }
                    try {
                        const response = await fetch(`api_get_pending_tokens.php?q=${encodeURIComponent(this.searchQuery)}`);
                        const data = await response.json();
                        if (data.success && Array.isArray(data.results)) {
                            this.suggestions = data.results.slice(0, 8);
                        }
                    } catch (err) {
                        console.error('Suggestion search error:', err);
                    }
                },

                selectInvoice(item) {
                    this.highlightedTokenId = item.token_id;
                    this.searchQuery = item.invoice_no;
                    this.suggestions = [];
                    this.loadPendingTokens();
                },

                openPriorityModal(item) {
                    this.selectedItem = item;
                    this.targetPriority = item.priority_no || 1;
                    this.priorityTypeMode = 'custom';
                    this.modalOpen = true;
                },

                confirmPriorityChange() {
                    if (this.selectedItem) {
                        this.updatePriority(
                            this.selectedItem.token_id, 
                            this.targetPriority, 
                            this.selectedItem.status,
                            this.targetOperator
                        );
                        this.modalOpen = false;
                    }
                },

                async updatePriority(tokenId, newPriority, status = null, updatedBy = 'QC Supervisor') {
                    try {
                        const response = await fetch('api_update_priority.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                token_id: tokenId, 
                                new_priority: parseInt(newPriority),
                                status: status,
                                updated_by: updatedBy
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.toastMessage = data.message || `Token ${tokenId} priority updated to Rank #${newPriority}`;
                            this.highlightedTokenId = tokenId;
                            await this.loadPendingTokens(false);
                            // Refresh after 2 seconds to clear highlight
                            setTimeout(() => { this.highlightedTokenId = null; }, 3000);
                        } else {
                            alert(data.error || 'Failed to update priority rank');
                        }
                    } catch (err) {
                        console.error('Failed to update priority:', err);
                        alert('Network error. Please try again.');
                    }
                }
            }
        }
    </script>
</body>
</html>