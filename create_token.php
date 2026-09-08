<?php 
require_once 'db.php'; 

date_default_timezone_set('Asia/Colombo');

try {
    $companies = $pdo->query("SELECT company_id, company_name FROM companies WHERE status='active' ORDER BY company_name ASC")->fetchAll();
    $branches  = $pdo->query("SELECT branch_id, branch_name FROM branches WHERE status='active' ORDER BY branch_name ASC")->fetchAll();
    $floors    = $pdo->query("SELECT floor_id, floor_name FROM floors ORDER BY floor_id ASC")->fetchAll();
} catch (Exception $e) {
    die("<div style='color:#f87171; background:#0b0f19; padding:24px; font-weight:bold; font-family:sans-serif; text-align:center;'>Database Connection Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Group | IT Department QC Token System</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
     <link rel="icon" type="image/png" href="logo.png">
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
                        },
                        ruby: {
                            500: '#e11d48',
                            600: '#be123c',
                            700: '#9f1239'
                        }
                    },
                    animation: {
                        'pulse-glow': 'pulseGlow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'shake': 'shake 0.4s cubic-bezier(.36,.07,.19,.97) both'
                    },
                    keyframes: {
                        pulseGlow: {
                            '0%, 100%': { opacity: '0.25', transform: 'scale(1)' },
                            '50%': { opacity: '0.55', transform: 'scale(1.06)' }
                        },
                        shake: {
                            '10%, 90%': { transform: 'translate3d(-1px, 0, 0)' },
                            '20%, 80%': { transform: 'translate3d(2px, 0, 0)' },
                            '30%, 50%, 70%': { transform: 'translate3d(-3px, 0, 0)' },
                            '40%, 60%': { transform: 'translate3d(3px, 0, 0)' }
                        }
                    }
                }
            }
        }
    </script>

    <!-- TomSelect CSS & JS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    <style>
        /* Cyber Matrix Grid Pattern with Crimson Hue */
        .cyber-grid {
            background-image: radial-gradient(rgba(239, 68, 68, 0.09) 1px, transparent 0);
            background-size: 24px 24px;
        }

        /* Glass Surface Effects - Dark & Red Themed */
        .glass-panel {
            background: rgba(13, 17, 28, 0.82);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(239, 68, 68, 0.18);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.85), 0 0 40px -10px rgba(225, 29, 72, 0.12);
        }

        .glass-card {
            background: rgba(22, 27, 42, 0.55);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .glass-card:focus-within {
            border-color: rgba(239, 68, 68, 0.35);
        }

        /* TomSelect Dark & Red Custom Styling */
        .ts-wrapper.single .ts-control {
            background-color: #0c101c !important;
            border: 1.5px solid #283046 !important;
            border-radius: 0.875rem !important;
            color: #f8fafc !important;
            padding: 0.65rem 1rem !important;
            font-size: 0.925rem !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.4) inset !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 46px;
            display: flex;
            align-items: center;
        }

        .ts-wrapper.single .ts-control:focus,
        .ts-wrapper.single.focus .ts-control {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important;
            background-color: #090d18 !important;
        }

        .ts-wrapper.single .ts-control input {
            color: #ffffff !important;
            font-weight: 600 !important;
            font-size: 0.925rem !important;
        }

        .ts-dropdown {
            background: #0a0d17 !important;
            border: 1.5px solid #331d24 !important;
            border-radius: 0.875rem !important;
            box-shadow: 0 25px 35px -5px rgba(0, 0, 0, 0.9), 0 10px 15px -5px rgba(225, 29, 72, 0.2) !important;
            color: #f8fafc !important;
            overflow: hidden !important;
            margin-top: 6px !important;
            z-index: 99999 !important;
            padding: 4px !important;
        }

        .ts-dropdown .option {
            padding: 9px 12px !important;
            border-radius: 0.5rem !important;
            color: #cbd5e1 !important;
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            transition: all 0.15s ease;
        }

        .ts-dropdown .option.active,
        .ts-dropdown .option:hover {
            background: linear-gradient(135deg, #e11d48, #be123c) !important;
            color: #ffffff !important;
        }

        .ts-dropdown .ts-dropdown-content {
            max-height: 220px !important;
            scrollbar-width: thin;
            scrollbar-color: #be123c #0f172a;
        }

        .ts-wrapper .item {
            color: #f8fafc !important;
            font-weight: 700 !important;
        }

        .ts-wrapper.single .ts-control::after {
            border-color: #ef4444 transparent transparent transparent !important;
        }

        /* Prevent all scrollbars */
        ::-webkit-scrollbar {
            display: none;
        }
        * {
            scrollbar-width: none;
        }
    </style>
</head>

<body class="bg-[#05070d] text-slate-100 h-screen max-h-screen overflow-hidden flex flex-col justify-between antialiased font-sans select-none cyber-grid relative"
      x-data="kioskApp()"
      x-init="initClock()">

    <!-- Ambient Glowing Red / Ruby Nebulas -->
    <div class="fixed top-[-15%] left-1/4 w-[550px] h-[550px] bg-red-600/15 rounded-full blur-[130px] pointer-events-none -z-10 animate-pulse-glow"></div>
    <div class="fixed bottom-[-15%] right-1/4 w-[550px] h-[550px] bg-rose-700/15 rounded-full blur-[130px] pointer-events-none -z-10 animate-pulse-glow" style="animation-delay: 1.5s"></div>

    <!-- 1. Header Toolbar (Zero-Scroll Compact IT Kiosk Bar with Navigation) -->
    <header class="bg-[#090d18]/90 backdrop-blur-xl border-b border-red-500/20 px-4 sm:px-6 py-2.5 flex-shrink-0 z-30 shadow-2xl">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
            
            <!-- Brand & Station Identity -->
            <div class="flex items-center gap-3">
                <div class="relative group flex-shrink-0">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-red-600 via-rose-600 to-red-500 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-red-600/30 tracking-wider">
                        ASB
                    </div>
                    <span class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-500 border-2 border-[#090d18] rounded-full"></span>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="font-extrabold text-sm sm:text-base md:text-lg text-white tracking-tight">ASB Group Of Companies</h1>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-red-500/15 text-red-400 border border-red-500/30">
                            IT DEPARTMENT
                        </span>
                        <span class="hidden lg:inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> TERMINAL #01
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 font-medium hidden sm:block">Stock Return Authorization & Thermal Ticket Dispenser System</p>
                </div>
            </div>

            <!-- Header Controls: Dashboard Link + Received Section Link + Status + Audio + Clock -->
            <div class="flex items-center gap-2 sm:gap-2.5">
                
                <!-- Navigation Button: Back to Dashboard -->
                <a href="dashboard.php" 
                   title="Return to Dashboard"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold border border-slate-800 bg-[#0c101c] hover:bg-slate-800 hover:border-slate-700 text-slate-300 hover:text-white transition-all flex items-center gap-1.5 shadow-sm active:scale-95 cursor-pointer">
                    <i class="fa-solid fa-arrow-left text-red-400"></i>
                    <span class="hidden md:inline">Dashboard</span>
                </a>

                <!-- Navigation Button: Received Section -->
                <a href="received.php" 
                   title="Go to Received Section"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold border border-red-500/30 bg-red-500/10 hover:bg-red-500/20 hover:border-red-500/50 text-red-300 hover:text-white transition-all flex items-center gap-1.5 shadow-sm active:scale-95 cursor-pointer">
                    <i class="fa-solid fa-boxes-packing text-red-400"></i>
                    <span class="hidden md:inline">Received Section</span>
                </a>

                <!-- Printer Status Badge -->
                <div class="hidden xl:flex items-center gap-1.5 bg-[#05070d]/90 px-3 py-1.5 rounded-xl border border-red-500/20 text-xs font-mono">
                    <i class="fa-solid fa-print text-red-400"></i>
                    <span class="text-slate-400">ESC/POS: <strong class="text-emerald-400">Ready</strong></span>
                </div>

                <!-- Sound FX Toggle -->
                <button @click="toggleSound()" 
                        type="button"
                        class="px-2.5 sm:px-3 py-1.5 rounded-xl text-xs font-bold border transition-all flex items-center gap-1.5 cursor-pointer active:scale-95"
                        :class="soundEnabled ? 'bg-red-500/15 text-red-400 border-red-500/30 hover:bg-red-500/25' : 'bg-[#05070d]/90 text-slate-500 border-slate-800 hover:text-slate-400'">
                    <i class="fa-solid" :class="soundEnabled ? 'fa-volume-high' : 'fa-volume-xmark'"></i>
                    <span class="hidden lg:inline" x-text="soundEnabled ? 'Audio On' : 'Muted'"></span>
                </button>

                <!-- Live Digital Clock -->
                <div class="bg-[#05070d]/90 px-3 py-1.5 rounded-xl border border-red-500/20 font-mono text-xs text-right shadow-inner flex items-center gap-1.5">
                    <span class="text-red-400 font-bold hidden sm:inline" x-text="currentDate"></span>
                    <span class="text-slate-700 hidden sm:inline">|</span>
                    <span class="text-white font-extrabold tracking-wider" x-text="currentTime"></span>
                </div>

            </div>

        </div>
    </header>

    <!-- 2. Main Center Kiosk Workstation (Zero-Scroll Full Viewport Master Card) -->
    <main class="flex-grow max-w-6xl mx-auto w-full px-4 sm:px-6 py-3.5 flex flex-col justify-center items-center overflow-hidden">
        
        <!-- MASTER KIOSK CONTAINER CARD -->
        <div class="w-full glass-panel rounded-3xl shadow-2xl p-5 sm:p-7 flex flex-col justify-between max-h-[calc(100vh-115px)] relative"
             :class="{ 'animate-shake': hasError }">
            
            <!-- Progress & Telemetry Header Bar -->
            <div class="flex flex-wrap items-center justify-between pb-3 mb-2 border-b border-red-500/20 gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-500/20 to-rose-600/10 border border-red-500/30 flex items-center justify-center text-red-400 text-base shadow-sm shadow-red-500/20">
                        <i class="fa-solid fa-microchip"></i>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-black text-white tracking-tight">Stock Return Parameters</h2>
                        <p class="text-xs text-slate-400">Step through the 6 required parameters to authorize & dispense return token</p>
                    </div>
                </div>

                <!-- Readiness Meter with Red/Emerald Glow -->
                <div class="flex items-center gap-3 bg-[#070a14] px-4 py-1.5 rounded-2xl border border-red-500/20 shadow-inner">
                    <div class="flex items-center gap-1.5">
                        <template x-for="i in 6" :key="i">
                            <span class="w-2.5 h-2.5 rounded-full transition-all duration-300"
                                  :class="completedCount() >= i ? 'bg-rose-500 shadow-sm shadow-rose-500/80 scale-110' : 'bg-slate-800'"></span>
                        </template>
                    </div>
                    <span class="text-xs font-black tracking-wider pl-1 font-mono" 
                          :class="completedCount() === 6 ? 'text-emerald-400' : 'text-red-400'" 
                          x-text="completedCount() === 6 ? 'ALL 6 READY' : completedCount() + '/6 COMPLETED'"></span>
                </div>
            </div>

            <!-- Error Banner -->
            <div x-show="errorMessage" 
                 x-transition
                 class="p-2.5 rounded-xl bg-red-600/20 border border-red-500/40 text-red-200 text-xs font-bold flex items-center gap-2 mb-2">
                <i class="fa-solid fa-circle-exclamation text-red-400 text-base flex-shrink-0"></i>
                <span x-text="errorMessage"></span>
            </div>

            <!-- Main Interactive Form -->
            <form id="tokenForm" action="generate_token.php" method="POST" @submit.prevent="submitForm()" class="space-y-3.5 my-auto">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <!-- LEFT COLUMN: Location & Vendor Routing -->
                    <div class="glass-card p-4 rounded-2xl border border-slate-800/90 space-y-3">
                        <div class="flex items-center justify-between text-xs font-extrabold text-slate-400 uppercase tracking-wider pb-1.5 border-b border-slate-800">
                            <span class="flex items-center gap-2 text-red-400">
                                <i class="fa-solid fa-map-location-dot"></i> 1. Location & Vendor Scope
                            </span>
                            <span class="text-[10px] text-red-500/80 font-mono">WAREHOUSE INGESTION</span>
                        </div>

                        <!-- Company Selection -->
                        <div>
                            <label for="company_id" class="flex items-center justify-between text-xs font-extrabold text-slate-300 uppercase mb-1">
                                <span>Company <span class="text-red-400">*</span></span>
                                <span class="text-[10px] text-rose-400 font-mono" x-show="formData.company_name" x-text="formData.company_name"></span>
                            </label>
                            <select name="company_id" id="company_id" required class="searchable-select" data-field="company_name">
                                <option value="">Select Company...</option>
                                <?php foreach ($companies as $comp): ?>
                                    <option value="<?php echo $comp['company_id']; ?>"><?php echo htmlspecialchars($comp['company_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Branch & Floor Side-by-Side -->
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Branch -->
                            <div>
                                <label for="branch_id" class="block text-xs font-extrabold text-slate-300 uppercase mb-1">
                                    Branch <span class="text-red-400">*</span>
                                </label>
                                <select name="branch_id" id="branch_id" required class="searchable-select" data-field="branch_name">
                                    <option value="">Select Branch...</option>
                                    <?php foreach ($branches as $br): ?>
                                        <option value="<?php echo $br['branch_id']; ?>"><?php echo htmlspecialchars($br['branch_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Floor Level -->
                            <div>
                                <label for="floor_id" class="block text-xs font-extrabold text-slate-300 uppercase mb-1">
                                    Floor Level <span class="text-red-400">*</span>
                                </label>
                                <select name="floor_id" id="floor_id" required class="searchable-select" data-field="floor_name">
                                    <option value="">Select Floor...</option>
                                    <?php foreach ($floors as $fl): ?>
                                        <option value="<?php echo $fl['floor_id']; ?>"><?php echo htmlspecialchars($fl['floor_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Supplier Live Search -->
                        <div>
                            <label for="supplier_id" class="flex items-center justify-between text-xs font-extrabold text-slate-300 uppercase mb-1">
                                <span>Supplier / Vendor <span class="text-red-400">*</span></span>
                                <span class="text-[10px] text-red-400 font-mono">INSTANT SEARCH</span>
                            </label>
                            <select name="supplier_id" id="supplier_id" required placeholder="Type supplier name or system ID...">
                                <option value=""></option>
                            </select>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Invoice, QC Staff & Compact Telemetry -->
                    <div class="glass-card p-4 rounded-2xl border border-slate-800/90 flex flex-col justify-between space-y-3">
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-xs font-extrabold text-slate-400 uppercase tracking-wider pb-1.5 border-b border-slate-800">
                                <span class="flex items-center gap-2 text-red-400">
                                    <i class="fa-solid fa-file-invoice"></i> 2. Invoice & QC Audit Trail
                                </span>
                                <span class="text-[10px] text-red-500/80 font-mono">IT VERIFICATION</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <!-- Invoice Number Input -->
                                <div>
                                    <label for="invoice_no" class="block text-xs font-extrabold text-slate-300 uppercase mb-1">
                                        Invoice Ref <span class="text-red-400">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                            <i class="fa-solid fa-barcode text-red-400/70"></i>
                                        </div>
                                        <input type="text" 
                                               name="invoice_no" 
                                               id="invoice_no" 
                                               required 
                                               placeholder="e.g. INV-9021"
                                               x-model="formData.invoice_no"
                                               @input="playTypeSound()"
                                               class="w-full pl-9 pr-3 py-2.5 bg-[#090d18] border-2 border-[#283046] rounded-xl text-base font-black text-red-400 placeholder:text-slate-600 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 uppercase transition font-mono shadow-inner">
                                    </div>
                                </div>

                                <!-- Operator Name / ID -->
                                <div>
                                    <label for="created_by" class="block text-xs font-extrabold text-slate-300 uppercase mb-1">
                                        Staff ID / Name <span class="text-red-400">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                            <i class="fa-solid fa-id-badge text-red-400/70"></i>
                                        </div>
                                        <input type="text" 
                                               name="created_by" 
                                               id="created_by" 
                                               required 
                                               placeholder="e.g. David / 5012"
                                               x-model="formData.created_by"
                                               @input="playTypeSound()"
                                               class="w-full pl-9 pr-3 py-2.5 bg-[#090d18] border-2 border-[#283046] rounded-xl text-base font-bold text-white placeholder:text-slate-600 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition shadow-inner">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Integrated Live Telemetry Preview Strip -->
                        <div class="bg-[#080b15] border border-red-500/20 rounded-xl p-3 space-y-1.5 font-mono text-[11px] shadow-inner">
                            <div class="flex items-center justify-between text-slate-400 pb-1 border-b border-red-500/15">
                                <span class="font-extrabold uppercase text-[10px] text-red-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-shield-halved"></i> Realtime Ingestion Telemetry
                                </span>
                                <span class="text-[10px] text-slate-500 font-sans" x-text="currentDate + ' ' + currentTime"></span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-slate-300">
                                <div class="truncate">
                                    <span class="text-slate-500">Company:</span> 
                                    <strong class="text-white" x-text="formData.company_name || '---'"></strong>
                                </div>
                                <div class="truncate">
                                    <span class="text-slate-500">Facility:</span> 
                                    <strong class="text-white" x-text="(formData.branch_name ? formData.branch_name : '---') + ' (' + (formData.floor_name ? formData.floor_name : '-') + ')'"></strong>
                                </div>
                                <div class="truncate">
                                    <span class="text-slate-500">Vendor:</span> 
                                    <strong class="text-rose-400" x-text="formData.supplier_name || '---'"></strong>
                                </div>
                                <div class="truncate">
                                    <span class="text-slate-500">Invoice:</span> 
                                    <strong class="text-emerald-400 font-bold" x-text="formData.invoice_no || '---'"></strong>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- ACTION BUTTONS: Clear + Big Glowing Crimson Dispense Trigger -->
                <div class="flex items-center gap-3 pt-1.5">
                    <!-- Reset Action Button -->
                    <button type="button" 
                            @click="resetForm()"
                            title="Clear Form"
                            class="px-4 py-3.5 rounded-2xl border-2 border-red-500/20 bg-[#090d18] hover:bg-slate-800 text-slate-400 hover:text-white font-extrabold text-xs transition-all flex items-center gap-2 cursor-pointer active:scale-95 shadow-md">
                        <i class="fa-solid fa-arrow-rotate-left"></i>
                        <span class="hidden sm:inline">Clear</span>
                    </button>

                    <!-- Big High-Tech Dispenser Button -->
                    <button type="submit" 
                            :disabled="submitting"
                            class="flex-grow py-3.5 px-6 rounded-2xl shadow-xl text-base font-black text-white transition-all duration-200 cursor-pointer disabled:opacity-50 flex items-center justify-center gap-3 active:scale-[0.99]"
                            :class="completedCount() === 6 ? 'bg-gradient-to-r from-red-600 via-rose-600 to-red-700 hover:from-red-500 hover:to-rose-500 shadow-red-600/30 ring-4 ring-rose-500/25' : 'bg-gradient-to-r from-slate-800 via-slate-700 to-slate-800 text-slate-400 border border-slate-700'">
                        
                        <template x-if="!submitting">
                            <span class="flex items-center gap-2.5 tracking-wide">
                                <i class="fa-solid fa-print text-lg" :class="completedCount() === 6 ? 'animate-bounce text-white' : 'text-slate-500'"></i>
                                <span x-text="completedCount() === 6 ? 'PRINT & DISPENSE RETURN TOKEN' : 'COMPLETE ALL 6 REQUIRED PARAMETERS (' + (6 - completedCount()) + ' REMAINING)'"></span>
                            </span>
                        </template>

                        <template x-if="submitting">
                            <span class="flex items-center gap-3 tracking-wide">
                                <i class="fa-solid fa-circle-notch animate-spin text-xl text-white"></i>
                                <span>PRINTING THERMAL TICKET...</span>
                            </span>
                        </template>
                    </button>
                </div>

            </form>

            <!-- Bottom Micro Hints -->
            <div class="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-red-500/15 font-mono">
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-bolt text-red-400"></i> Press <kbd class="px-1.5 py-0.5 bg-slate-900 border border-red-500/20 rounded text-slate-300">Enter</kbd> to Dispense</span>
                <span>ESC/POS Auto-Cutter Enabled</span>
                <span class="hidden sm:inline text-slate-600">v4.1-PRO</span>
            </div>

        </div>

    </main>

    <!-- 3. Ultra-Slim Global Footer with Vexel IT & Kavizz Credits -->
    <footer class="bg-[#090d18]/90 backdrop-blur-md border-t border-red-500/20 px-6 py-2 flex-shrink-0 text-xs text-slate-400">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <p>&copy; <?php echo date("Y"); ?> <strong class="text-slate-200">ASB Group of Companies</strong> &bull; IT Department Stock Token Ingestion</p>
            <p>Designed &amp; Developed by <span class="text-red-400 font-bold">Vexel IT</span> by <span class="text-white font-extrabold">Kavizz</span></p>
        </div>
    </footer>

    <!-- Alpine.js & TomSelect Application Controller -->
    <script>
        function kioskApp() {
            return {
                submitting: false,
                hasError: false,
                errorMessage: '',
                soundEnabled: true,
                currentDate: '<?php echo date("Y-m-d"); ?>',
                currentTime: '<?php echo date("H:i:s"); ?>',
                audioCtx: null,

                formData: {
                    company_id: '',
                    company_name: '',
                    branch_id: '',
                    branch_name: '',
                    floor_id: '',
                    floor_name: '',
                    supplier_id: '',
                    supplier_name: '',
                    invoice_no: '',
                    created_by: ''
                },

                initClock() {
                    setInterval(() => {
                        const now = new Date();
                        this.currentTime = now.toTimeString().split(' ')[0];
                    }, 1000);
                },

                completedCount() {
                    let count = 0;
                    if (this.formData.company_id) count++;
                    if (this.formData.branch_id) count++;
                    if (this.formData.floor_id) count++;
                    if (this.formData.supplier_id) count++;
                    if (this.formData.invoice_no && this.formData.invoice_no.trim()) count++;
                    if (this.formData.created_by && this.formData.created_by.trim()) count++;
                    return count;
                },

                playTone(freq, type, duration) {
                    if (!this.soundEnabled) return;
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

                playTypeSound() {
                    this.playTone(680, 'sine', 0.03);
                },

                playSuccessSound() {
                    this.playTone(523.25, 'triangle', 0.08);
                    setTimeout(() => this.playTone(659.25, 'triangle', 0.08), 70);
                    setTimeout(() => this.playTone(783.99, 'triangle', 0.14), 140);
                },

                playErrorSound() {
                    this.playTone(180, 'sawtooth', 0.12);
                    setTimeout(() => this.playTone(130, 'sawtooth', 0.15), 100);
                },

                toggleSound() {
                    this.soundEnabled = !this.soundEnabled;
                    if (this.soundEnabled) this.playTone(850, 'sine', 0.08);
                },

                submitForm() {
                    this.hasError = false;
                    this.errorMessage = '';

                    if (!this.formData.company_id || !this.formData.branch_id || !this.formData.floor_id || 
                        !this.formData.supplier_id || !this.formData.invoice_no.trim() || !this.formData.created_by.trim()) {
                        
                        this.hasError = true;
                        this.errorMessage = 'Please complete all required fields (*) before printing!';
                        this.playErrorSound();
                        
                        setTimeout(() => { this.hasError = false; }, 800);
                        return;
                    }

                    this.submitting = true;
                    this.playSuccessSound();

                    setTimeout(() => {
                        document.getElementById('tokenForm').submit();
                    }, 400);
                },

                resetForm() {
                    if (confirm('Clear all entered fields?')) {
                        document.querySelectorAll('.ts-wrapper').forEach(w => {
                            if (w.tomselect) w.tomselect.clear();
                        });
                        this.formData.company_id = '';
                        this.formData.company_name = '';
                        this.formData.branch_id = '';
                        this.formData.branch_name = '';
                        this.formData.floor_id = '';
                        this.formData.floor_name = '';
                        this.formData.supplier_id = '';
                        this.formData.supplier_name = '';
                        this.formData.invoice_no = '';
                        this.formData.created_by = '';
                        this.playTone(320, 'sine', 0.08);
                    }
                }
            }
        }

        // Initialize TomSelect & Dropdown Handlers
        document.addEventListener('DOMContentLoaded', function() {
            const getAlpineData = () => {
                const el = document.querySelector('[x-data]');
                return el ? Alpine.$data(el) : null;
            };

            // Standard searchable selects (Company, Branch, Floor)
            document.querySelectorAll('.searchable-select').forEach(function(el) {
                const fieldName = el.getAttribute('data-field');
                new TomSelect(el, {
                    create: false,
                    allowEmptyOption: true,
                    placeholder: 'Select...',
                    controlInput: '<input>',
                    onChange: function(value) {
                        const app = getAlpineData();
                        if (app) {
                            app.formData[el.name] = value;
                            app.formData[fieldName] = el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : '';
                            app.playTone(720, 'sine', 0.04);
                        }
                    }
                });
            });

            // Supplier AJAX Instant Smart Search
            const supplierSelect = new TomSelect('#supplier_id', {
                valueField: 'supplier_id',
                labelField: 'supplier_name',
                searchField: ['supplier_name', 'system_id'],
                loadThrottle: 300,
                placeholder: 'Search supplier name or ID...',
                controlInput: '<input>',
                load: function(query, callback) {
                    const url = 'api_get_suppliers.php?q=' + encodeURIComponent(query) + '&limit=20';
                    fetch(url)
                        .then(response => response.json())
                        .then(json => {
                            if (json.success && Array.isArray(json.results)) {
                                callback(json.results);
                            } else {
                                callback();
                            }
                        })
                        .catch(() => callback());
                },
                onChange: function(value) {
                    const app = getAlpineData();
                    if (app) {
                        app.formData.supplier_id = value;
                        const selectedOption = supplierSelect.options[value];
                        app.formData.supplier_name = selectedOption ? selectedOption.supplier_name : '';
                        app.playTone(720, 'sine', 0.04);
                    }
                },
                render: {
                    option: function(item, escape) {
                        const sysId = item.system_id ? `<span class="text-[10px] text-red-400 font-mono bg-slate-900 px-1.5 py-0.5 rounded border border-red-500/30">ID: ${escape(item.system_id)}</span>` : '';
                        return `<div class="py-2 px-2 flex items-center justify-between font-bold text-sm text-slate-100">
                                    <span>${escape(item.supplier_name)}</span>
                                    ${sysId}
                                </div>`;
                    },
                    item: function(item, escape) {
                        return `<div class="font-bold text-slate-100">${escape(item.supplier_name)}</div>`;
                    },
                    no_results: function(data, escape) {
                        return `<div class="p-3 text-xs text-slate-400 text-center">No vendor found matching "${escape(data.input)}"</div>`;
                    }
                }
            });
        });
    </script>
</body>
</html>