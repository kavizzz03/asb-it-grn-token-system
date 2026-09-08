<?php
require_once 'db.php';

date_default_timezone_set('Asia/Colombo');

// Fetch ONLY TOP 10 priority tokens
try {
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
            c.company_name,
            c.company_id,
            b.branch_name,
            f.floor_name,
            s.supplier_name,
            s.contact_number,
            DATEDIFF(CURDATE(), t.token_date) as days_old
        FROM tokens t
        LEFT JOIN companies c ON t.company_id = c.company_id
        LEFT JOIN branches b ON t.branch_id = b.branch_id
        LEFT JOIN floors f ON t.floor_id = f.floor_id
        LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id
        WHERE t.status IN ('pending', 'process') 
          AND t.priority_no IS NOT NULL 
          AND t.priority_no > 0
        ORDER BY t.priority_no ASC
        LIMIT 10
    ";
    
    $topTokens = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get counts
    $totalPending = $pdo->query("SELECT COUNT(*) FROM tokens WHERE status = 'pending' OR status IS NULL")->fetchColumn();
    $totalProcess = $pdo->query("SELECT COUNT(*) FROM tokens WHERE status = 'process'")->fetchColumn();
    $totalComplete = $pdo->query("SELECT COUNT(*) FROM tokens WHERE status = 'complete'")->fetchColumn();
    $totalReceived = $pdo->query("SELECT COUNT(*) FROM tokens WHERE status = 'received'")->fetchColumn();
    $totalActive = $totalPending + $totalProcess;
    
    // Get old records count (>2 days)
    $oldCheck = $pdo->query("
        SELECT COUNT(*) FROM tokens 
        WHERE token_date < DATE_SUB(CURDATE(), INTERVAL 2 DAY) 
        AND status IN ('pending', 'process')
    ")->fetchColumn();
    $hasOldRecords = $oldCheck > 0;
    
    // Get old records details for display - FIXED with table aliases
    $oldRecords = $pdo->query("
        SELECT 
            t.token_id,
            t.invoice_no,
            t.token_date,
            DATEDIFF(CURDATE(), t.token_date) as days_old,
            s.supplier_name,
            f.floor_name,
            t.status
        FROM tokens t
        LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id
        LEFT JOIN floors f ON t.floor_id = f.floor_id
        WHERE t.token_date < DATE_SUB(CURDATE(), INTERVAL 2 DAY) 
        AND t.status IN ('pending', 'process')
        ORDER BY t.token_date ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current top token for initial state
    $currentTopToken = !empty($topTokens) ? $topTokens[0] : null;
    
} catch (Exception $e) {
    die("<div style='color:#ef4444; background:#0b0f19; padding:24px; font-weight:bold; font-family:sans-serif; text-align:center;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Group IT - Priority Display</title>
     <link rel="icon" type="image/png" href="logo.png">

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
                        'glow': 'glow 2.5s ease-in-out infinite alternate',
                        'slide-up': 'slideUp 0.8s ease-out',
                        'fade-in': 'fadeIn 1s ease-out',
                        'float': 'float 4s ease-in-out infinite',
                        'shimmer': 'shimmer 3s ease-in-out infinite',
                        'pulse-ring': 'pulseRing 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'bounce-slow': 'bounce 3s ease-in-out infinite',
                        'slide-in': 'slideIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                        'old-record-pulse': 'oldRecordPulse 2s ease-in-out infinite',
                    },
                    keyframes: {
                        glow: {
                            '0%': { boxShadow: '0 0 30px rgba(14, 165, 233, 0.2), inset 0 0 30px rgba(14, 165, 233, 0.05)' },
                            '100%': { boxShadow: '0 0 60px rgba(14, 165, 233, 0.5), inset 0 0 40px rgba(14, 165, 233, 0.15)' }
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(40px) scale(0.95)' },
                            '100%': { opacity: '1', transform: 'translateY(0) scale(1)' }
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-8px)' }
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-200% center' },
                            '100%': { backgroundPosition: '200% center' }
                        },
                        pulseRing: {
                            '0%, 100%': { transform: 'scale(1)', opacity: '0.8' },
                            '50%': { transform: 'scale(1.2)', opacity: '0.3' }
                        },
                        slideIn: {
                            '0%': { opacity: '0', transform: 'translateX(-20px)' },
                            '100%': { opacity: '1', transform: 'translateX(0)' }
                        },
                        oldRecordPulse: {
                            '0%, 100%': { backgroundColor: 'rgba(239, 68, 68, 0.15)', borderColor: 'rgba(239, 68, 68, 0.4)' },
                            '50%': { backgroundColor: 'rgba(239, 68, 68, 0.30)', borderColor: 'rgba(239, 68, 68, 0.8)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        ::-webkit-scrollbar { width: 4px; height: 4px; }
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
        
        .glass-card-light {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.04);
        }
        
        .priority-number {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 900;
            font-size: 2rem;
            background: linear-gradient(135deg, #38bdf8, #0ea5e9, #0284c7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .priority-number-sm {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            font-size: 1.25rem;
        }
        
        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-dot.pending { background: #fbbf24; box-shadow: 0 0 20px rgba(251, 191, 36, 0.3); }
        .status-dot.process { background: #60a5fa; animation: pulse 1.5s ease-in-out infinite; box-shadow: 0 0 20px rgba(96, 165, 250, 0.3); }
        .status-dot.complete { background: #34d399; box-shadow: 0 0 20px rgba(52, 211, 153, 0.3); }
        .status-dot.received { background: #a78bfa; box-shadow: 0 0 20px rgba(167, 139, 250, 0.3); }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.7); }
        }
        
        .priority-row {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .priority-row::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.02), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }
        
        .priority-row:hover::before {
            transform: translateX(100%);
        }
        
        .priority-row:hover {
            transform: scale(1.005);
            background: rgba(14, 165, 233, 0.04);
        }
        
        .priority-row.top-1 {
            border-left-color: #0ea5e9;
            background: linear-gradient(90deg, rgba(14, 165, 233, 0.12) 0%, transparent 70%);
        }
        
        .priority-row.top-2 {
            border-left-color: #38bdf8;
            background: linear-gradient(90deg, rgba(56, 189, 248, 0.08) 0%, transparent 70%);
        }
        
        .priority-row.top-3 {
            border-left-color: #7dd3fc;
            background: linear-gradient(90deg, rgba(125, 211, 252, 0.05) 0%, transparent 70%);
        }
        
        .bg-pattern {
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(14, 165, 233, 0.06) 0%, transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(14, 165, 233, 0.03) 0%, transparent 50%);
        }
        
        .glow-border {
            animation: glow 2.5s ease-in-out infinite alternate;
        }
        
        /* Old Record Styles */
        .old-record-row {
            animation: oldRecordPulse 2s ease-in-out infinite;
            border-left: 4px solid #ef4444 !important;
            background: rgba(239, 68, 68, 0.08) !important;
        }
        
        .old-record-row:hover {
            background: rgba(239, 68, 68, 0.15) !important;
        }
        
        .old-record-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            font-size: 0.6rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 9999px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            animation: pulse 1.5s ease-in-out infinite;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Welcome Slideshow */
        .slide-container {
            position: relative;
            overflow: hidden;
            border-radius: 1.5rem;
        }
        
        .slide-content {
            transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        /* Shimmer text effect */
        .shimmer-text {
            background: linear-gradient(90deg, #38bdf8, #0ea5e9, #7dd3fc, #38bdf8);
            background-size: 200% auto;
            animation: shimmer 3s linear infinite;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Enhanced table styles */
        .table-header-text {
            font-size: 0.7rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            font-weight: 800;
        }
        
        /* Responsive font sizes */
        @media (max-width: 1024px) {
            .priority-number { font-size: 1.5rem; }
            .priority-number-sm { font-size: 1rem; }
        }
        
        @media (max-width: 768px) {
            .priority-number { font-size: 1.2rem; }
            .priority-number-sm { font-size: 0.85rem; }
        }
        
        /* Flash animation for updated row */
        @keyframes flashRow {
            0% { background-color: rgba(14, 165, 233, 0.3); }
            100% { background-color: transparent; }
        }
        .flash-update {
            animation: flashRow 1.5s ease-out;
        }
        
        /* Old records list styles */
        .old-record-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 10px;
            border-radius: 10px;
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.1);
            margin-bottom: 4px;
            transition: all 0.3s ease;
        }
        
        .old-record-item:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
        }
        
        .old-record-token {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            font-weight: 700;
            color: #ef4444;
        }
        
        .old-record-invoice {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            font-weight: 600;
            color: #fca5a5;
        }
        
        .old-record-days {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            font-weight: 800;
            color: #ef4444;
            background: rgba(239, 68, 68, 0.15);
            padding: 1px 8px;
            border-radius: 9999px;
        }
    </style>
</head>
<body class="relative bg-slate-950 text-slate-100 min-h-screen flex flex-col overflow-hidden antialiased font-sans bg-pattern">

    <!-- Dynamic Background -->
    <div class="fixed inset-0 z-0 pointer-events-none">
        <div class="w-full h-full bg-cover bg-center transition-all duration-1500 ease-in-out" 
             style="background-image: url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1920&q=80'); opacity: 0.08;">
        </div>
        <div class="absolute inset-0 bg-gradient-to-b from-slate-950/95 via-slate-950/90 to-slate-950/95"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/70 via-transparent to-slate-950/70"></div>
        <div class="absolute inset-0 bg-gradient-to-tr from-asb-500/5 via-transparent to-asb-400/5 animate-pulse-slow"></div>
    </div>

    <!-- Startup Overlay - Only shows on first visit -->
    <div id="audio-overlay" class="fixed inset-0 z-50 bg-slate-950/98 backdrop-blur-2xl flex flex-col items-center justify-center cursor-pointer transition-all duration-700 ease-in-out">
        <div class="relative animate-slide-up">
            <div class="absolute inset-0 -m-10 rounded-full border-2 border-asb-400/20 animate-ping"></div>
            <div class="absolute inset-0 -m-6 rounded-full border-2 border-asb-400/30 animate-pulse" style="animation-delay: 0.5s;"></div>
            <div class="absolute inset-0 -m-12 rounded-full bg-asb-500/5 blur-2xl animate-pulse-slow"></div>
            
            <div class="glass-card p-12 rounded-3xl text-center max-w-2xl shadow-2xl shadow-asb-500/10 relative overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-asb-500/5 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-asb-600/5 rounded-full blur-3xl"></div>
                
                <div class="relative mb-8">
                    <div class="w-32 h-32 bg-gradient-to-br from-asb-600 to-asb-400 text-white rounded-3xl flex items-center justify-center mx-auto text-6xl shadow-2xl shadow-asb-500/40 transform hover:scale-105 transition-transform duration-500">
                        <i class="fa-solid fa-tv"></i>
                    </div>
                    <div class="absolute -top-2 -right-2 w-12 h-12 bg-emerald-500 rounded-full flex items-center justify-center text-white text-xl font-black shadow-lg shadow-emerald-500/50 animate-pulse">
                        <i class="fa-solid fa-check"></i>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <h2 class="text-5xl font-black text-white tracking-tight shimmer-text">ASB Group IT</h2>
                    <p class="text-slate-400 text-lg max-w-md mx-auto leading-relaxed">
                        Priority Queue Display • Top 10 • Live Voice Announcements
                    </p>
                    <div class="flex items-center justify-center gap-6 text-sm text-slate-500">
                        <span><i class="fa-regular fa-circle-check text-emerald-400 mr-2"></i> Voice Enabled</span>
                        <span class="w-px h-6 bg-slate-800"></span>
                        <span><i class="fa-regular fa-clock text-asb-400 mr-2"></i> Auto-Refresh 20s</span>
                        <span class="w-px h-6 bg-slate-800"></span>
                        <span><i class="fa-regular fa-bell text-amber-400 mr-2"></i> Smart Alerts</span>
                    </div>
                </div>
                
                <button onclick="grantPermission()" class="mt-8 w-full bg-gradient-to-r from-asb-600 to-asb-400 hover:from-asb-500 hover:to-asb-300 text-white font-extrabold py-4 rounded-2xl text-lg shadow-2xl shadow-asb-600/40 transition-all transform hover:scale-[1.02] active:scale-95 relative overflow-hidden group">
                    <span class="relative z-10 flex items-center justify-center gap-3">
                        <i class="fa-solid fa-play"></i>
                        <span>Activate Display System</span>
                        <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </span>
                    <div class="absolute inset-0 bg-gradient-to-r from-white/10 to-transparent group-hover:translate-x-full transition-transform duration-700"></div>
                </button>
                
                <div class="mt-4 text-xs text-slate-500 font-mono flex items-center justify-center gap-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    System Ready
                    <span class="w-px h-4 bg-slate-800"></span>
                    <span>v3.0</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Popup Modal -->
    <div id="popup-modal" class="fixed inset-0 z-40 bg-black/90 backdrop-blur-xl flex items-center justify-center hidden transition-all duration-300">
        <div class="glass-card p-10 rounded-3xl text-center max-w-2xl shadow-2xl space-y-5 relative overflow-hidden animate-slide-up">
            <div class="absolute -top-32 -left-32 w-64 h-64 bg-asb-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-32 -right-32 w-64 h-64 bg-amber-500/5 rounded-full blur-3xl"></div>
            
            <div id="popup-icon-wrapper" class="w-24 h-24 bg-asb-500/20 border-2 border-asb-400 text-asb-400 rounded-3xl flex items-center justify-center mx-auto text-5xl shadow-xl">
                <i id="popup-icon" class="fa-regular fa-sun"></i>
            </div>
            <div>
                <span id="popup-tag" class="bg-asb-500/20 text-asb-400 text-xs font-black px-4 py-1.5 rounded-full uppercase tracking-widest border border-asb-500/30">
                    ASB IT Notification
                </span>
                <h3 id="popup-title" class="text-3xl font-black text-white mt-3">Good Morning!</h3>
                <p id="popup-body" class="text-slate-300 text-lg mt-2 leading-relaxed font-medium">
                    Welcome to ASB Group of Companies IT Department.
                </p>
            </div>
            <div class="pt-2 flex flex-col items-center gap-2.5">
                <button onclick="closePopup()" class="bg-gradient-to-r from-asb-600 to-asb-400 text-white font-extrabold px-10 py-3.5 rounded-2xl text-base shadow-lg border border-asb-400/40 hover:brightness-110 transition-all">
                    <i class="fa-regular fa-circle-check mr-2"></i> Dismiss
                </button>
                <span class="text-xs text-slate-500 font-bold uppercase tracking-wider">Auto-closing in <span id="popup-timer" class="text-white">7</span>s</span>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="glass-card border-b border-white/5 px-8 py-3.5 flex items-center justify-between shadow-2xl relative z-10 flex-shrink-0">
        <div class="flex items-center space-x-6">
            <div class="flex items-center space-x-4">
                <div class="bg-gradient-to-br from-asb-600 to-asb-400 text-white px-4 py-2 rounded-2xl font-black text-2xl tracking-wider shadow-2xl shadow-asb-500/30 border border-asb-400/30">
                    ASB
                </div>
                <div>
                    <h1 class="text-xl font-black tracking-wide text-white uppercase">Group of Companies</h1>
                    <p class="text-xs text-slate-400 font-semibold flex items-center gap-3">
                        <span>IT Department</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-600"></span>
                        <span class="text-asb-400 font-bold text-sm">Priority Queue</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-600"></span>
                        <span class="text-emerald-400">● Live</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Weather -->
        <div class="hidden lg:flex items-center gap-4 bg-slate-950/60 border border-white/5 px-5 py-2.5 rounded-2xl">
            <i id="nav-weather-icon" class="fa-regular fa-cloud-sun text-asb-400 text-2xl"></i>
            <div class="flex items-center gap-5 text-sm font-mono">
                <div>
                    <span class="text-slate-500 text-[9px] uppercase block font-bold">Temp</span>
                    <span id="nav-temp" class="text-amber-400 font-extrabold text-lg">--°C</span>
                </div>
                <div class="w-px h-8 bg-slate-800/50"></div>
                <div>
                    <span class="text-slate-500 text-[9px] uppercase block font-bold">Wind</span>
                    <span id="nav-wind" class="text-emerald-400 font-extrabold text-lg">--</span>
                </div>
                <div class="w-px h-8 bg-slate-800/50"></div>
                <div>
                    <span class="text-slate-500 text-[9px] uppercase block font-bold">Rain</span>
                    <span id="nav-rain" class="text-indigo-400 font-extrabold text-lg">--%</span>
                </div>
            </div>
        </div>

        <!-- Clock -->
        <div class="flex items-center space-x-5">
            <div class="text-right border-l border-white/10 pl-5">
                <div id="dynamic-greeting" class="text-sm font-bold text-asb-400 uppercase tracking-wider flex items-center justify-end gap-2">
                    <i class="fa-regular fa-sun text-amber-400 text-lg"></i> Good Morning
                </div>
                <div id="live-clock" class="text-3xl font-mono font-extrabold text-white tracking-wider">--:--:--</div>
                <div id="live-date" class="text-xs text-slate-400 font-bold uppercase tracking-wider">----</div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow p-6 lg:p-8 grid grid-cols-12 gap-6 lg:gap-8 relative z-10 h-[calc(100vh-85px)]">
        
        <!-- Left Panel - Welcome Slideshow & Stats -->
        <section class="col-span-12 lg:col-span-4 flex flex-col gap-5 h-full">
            
            <!-- Welcome Slideshow Card -->
            <div class="glass-card rounded-3xl p-6 shadow-2xl flex-shrink-0 relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-asb-500/10 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-asb-600/5 rounded-full blur-3xl"></div>
                
                <div class="relative">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-xs font-black uppercase tracking-widest text-asb-400 flex items-center gap-2">
                            <i class="fa-regular fa-images"></i> Welcome
                        </span>
                        <span class="flex gap-1.5" id="slide-indicators">
                            <span class="slide-indicator w-8 h-1.5 rounded-full bg-asb-400 transition-all duration-300"></span>
                            <span class="slide-indicator w-3 h-1.5 rounded-full bg-slate-700 transition-all duration-300"></span>
                            <span class="slide-indicator w-3 h-1.5 rounded-full bg-slate-700 transition-all duration-300"></span>
                            <span class="slide-indicator w-3 h-1.5 rounded-full bg-slate-700 transition-all duration-300"></span>
                        </span>
                    </div>
                    
                    <div class="slide-container">
                        <div class="relative h-32 w-full overflow-hidden rounded-2xl">
                            <img id="slide-image" 
                                 src="https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=800&q=80" 
                                 alt="Welcome" 
                                 class="w-full h-full object-cover transition-all duration-700 ease-out">
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/30 to-transparent"></div>
                            <div class="absolute top-3 left-3 w-12 h-12 bg-asb-500/80 backdrop-blur-md text-white rounded-2xl flex items-center justify-center text-2xl shadow-lg border border-white/20">
                                <i id="slide-icon" class="fa-regular fa-building"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 space-y-1.5">
                        <h2 id="slide-title" class="text-2xl font-black text-white leading-tight">Welcome to ASB Group IT</h2>
                        <p id="slide-body" class="text-slate-400 text-sm leading-relaxed">
                            Providing seamless technology infrastructure and automated document return workflows.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Panel with Old Records -->
            <div class="glass-card rounded-3xl p-6 shadow-2xl flex-grow relative overflow-hidden">
                <div class="absolute -top-20 -left-20 w-40 h-40 bg-asb-500/5 rounded-full blur-2xl"></div>
                
                <div class="flex items-center justify-between pb-4 border-b border-white/5">
                    <span class="text-xs font-black uppercase tracking-widest text-asb-400 flex items-center gap-2">
                        <i class="fa-regular fa-chart-bar"></i> Queue Stats
                    </span>
                    <span class="text-sm text-slate-400 font-mono">
                        <span class="text-2xl font-black text-white"><?php echo $totalActive; ?></span> Active
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-4">
                    <div class="bg-slate-950/60 rounded-2xl px-4 py-3.5 border border-amber-500/10 hover:border-amber-500/30 transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-amber-400 flex items-center gap-2">
                                <i class="fa-regular fa-clock text-base"></i> Pending
                            </span>
                            <span class="text-2xl font-black text-amber-400 font-mono"><?php echo $totalPending; ?></span>
                        </div>
                    </div>
                    <div class="bg-slate-950/60 rounded-2xl px-4 py-3.5 border border-sky-500/10 hover:border-sky-500/30 transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-sky-400 flex items-center gap-2">
                                <i class="fa-regular fa-spinner fa-spin text-base"></i> Process
                            </span>
                            <span class="text-2xl font-black text-sky-400 font-mono"><?php echo $totalProcess; ?></span>
                        </div>
                    </div>
                    <div class="bg-slate-950/60 rounded-2xl px-4 py-3.5 border border-emerald-500/10 hover:border-emerald-500/30 transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-emerald-400 flex items-center gap-2">
                                <i class="fa-regular fa-circle-check text-base"></i> Complete
                            </span>
                            <span class="text-2xl font-black text-emerald-400 font-mono"><?php echo $totalComplete; ?></span>
                        </div>
                    </div>
                    <div class="bg-slate-950/60 rounded-2xl px-4 py-3.5 border border-purple-500/10 hover:border-purple-500/30 transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-purple-400 flex items-center gap-2">
                                <i class="fa-regular fa-box text-base"></i> Received
                            </span>
                            <span class="text-2xl font-black text-purple-400 font-mono"><?php echo $totalReceived; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Old Records Alert -->
                <?php if ($hasOldRecords): ?>
                <div class="mt-4 bg-red-950/30 rounded-2xl px-4 py-3 border border-red-500/30 animate-pulse">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-red-400 flex items-center gap-2">
                            <i class="fa-regular fa-triangle-exclamation text-lg"></i> Old Records Alert
                        </span>
                        <span class="text-2xl font-black text-red-400 font-mono"><?php echo $oldCheck; ?></span>
                    </div>
                    <div class="text-xs text-red-300/70 mt-0.5">> 2 days old • Requires immediate attention</div>
                </div>
                <?php endif; ?>

                <!-- Old Records List -->
                <?php if (!empty($oldRecords)): ?>
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-bold text-red-400 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-regular fa-clock-rotate-left"></i> Old Records
                        </span>
                        <span class="text-[8px] text-red-400/60">Token • Invoice • Days</span>
                    </div>
                    <div class="space-y-1 max-h-32 overflow-y-auto">
                        <?php foreach ($oldRecords as $old): ?>
                        <div class="old-record-item">
                            <span class="old-record-token"><?php echo htmlspecialchars($old['token_id']); ?></span>
                            <span class="old-record-invoice"><?php echo htmlspecialchars($old['invoice_no']); ?></span>
                            <span class="old-record-days"><?php echo $old['days_old']; ?>d</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Weather -->
                <div class="mt-auto pt-4 border-t border-white/5">
                    <div class="bg-slate-950/60 rounded-2xl p-3.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-bold uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-regular fa-location-dot text-asb-400 text-base"></i> Kalutara
                            </span>
                            <span id="weather-sync-status" class="text-[10px] bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-bold px-2.5 py-0.5 rounded-lg">Live</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mt-2 text-sm">
                            <div class="flex justify-between items-center"><span class="text-slate-500">Rain</span><span id="panel-rain" class="text-white font-mono font-bold">--</span></div>
                            <div class="flex justify-between items-center"><span class="text-slate-500">Wind</span><span id="panel-wind" class="text-white font-mono font-bold">--</span></div>
                        </div>
                    </div>
                </div>
            </div>

        </section>

        <!-- Right Panel - Top 10 Priority Table -->
        <section class="col-span-12 lg:col-span-8 flex flex-col h-full">
            
            <div class="glass-card rounded-3xl border-2 border-white/5 shadow-2xl overflow-hidden flex flex-col h-full">
                
                <!-- Table Header -->
                <div class="bg-slate-900/80 px-6 py-4 border-b border-white/5 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-4">
                        <div class="relative flex items-center gap-2">
                            <span class="relative flex h-4 w-4">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-4 w-4 bg-emerald-500"></span>
                            </span>
                            <h2 class="text-xl font-black text-white tracking-wide uppercase">Top 10 Priority Queue</h2>
                        </div>
                        <span class="text-xs bg-asb-500/10 text-asb-400 px-3 py-1 rounded-xl border border-asb-500/20 font-mono flex items-center gap-2">
                            <i class="fa-regular fa-rotate-right"></i> <span id="last-updated">Just now</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <?php if ($hasOldRecords): ?>
                        <span class="bg-red-500/20 text-red-400 border border-red-500/30 px-2.5 py-1 rounded-full uppercase tracking-wider flex items-center gap-1.5 animate-pulse text-sm">
                            <i class="fa-regular fa-triangle-exclamation"></i> <?php echo $oldCheck; ?> Old
                        </span>
                        <?php endif; ?>
                        <span class="text-slate-500 font-mono flex items-center gap-2 text-sm">
                            <i class="fa-regular fa-volume-high text-asb-400 text-base"></i> Voice Active
                        </span>
                    </div>
                </div>

                <!-- Column Headings -->
                <div class="grid grid-cols-12 bg-slate-950/80 py-3 px-6 text-xs font-black uppercase tracking-widest text-slate-400 border-b border-white/5 flex-shrink-0">
                    <div class="col-span-2 text-center">Rank</div>
                    <div class="col-span-2">Token ID</div>
                    <div class="col-span-2">Invoice</div>
                    <div class="col-span-2">Supplier</div>
                    <div class="col-span-1 text-center">Floor</div>
                    <div class="col-span-1 text-center">Status</div>
                    <div class="col-span-1 text-center">Age</div>
                    <div class="col-span-1 text-right">Time</div>
                </div>

                <!-- Table Body -->
                <div id="table-body" class="flex-grow overflow-y-auto">
                    <?php if (empty($topTokens)): ?>
                        <div class="p-16 text-center text-slate-500 font-bold text-xl flex flex-col items-center justify-center gap-4 h-full">
                            <div class="w-24 h-24 bg-slate-800/50 rounded-full flex items-center justify-center text-5xl text-slate-600">
                                <i class="fa-regular fa-circle-check"></i>
                            </div>
                            <span class="text-white text-2xl font-black">Queue Empty</span>
                            <span class="text-base text-slate-400">No priority items in queue.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($topTokens as $index => $token): 
                            $rank = $index + 1;
                            $isOld = isset($token['days_old']) && $token['days_old'] > 2;
                            
                            $rowClass = 'priority-row ';
                            if ($rank === 1) $rowClass .= 'top-1 glow-border';
                            elseif ($rank === 2) $rowClass .= 'top-2';
                            elseif ($rank === 3) $rowClass .= 'top-3';
                            $rowClass .= ' hover:bg-slate-800/30';
                            
                            if ($isOld) {
                                $rowClass .= ' old-record-row';
                            }
                            
                            $statusDot = 'status-dot ' . ($token['status'] ?? 'pending');
                            $statusLabel = ucfirst($token['status'] ?? 'Pending');
                            $timeStr = $token['created_at'] ? date('H:i', strtotime($token['created_at'])) : '--:--';
                            
                            $rankColor = $rank === 1 ? 'text-white' : ($rank === 2 ? 'text-asb-300' : ($rank === 3 ? 'text-asb-200' : 'text-slate-400'));
                            $rankSize = $rank === 1 ? 'text-4xl' : ($rank === 2 ? 'text-3xl' : 'text-2xl');
                            $tokenId = htmlspecialchars($token['token_id']);
                            
                            $daysOld = isset($token['days_old']) ? $token['days_old'] : 0;
                            $ageDisplay = $daysOld . 'd';
                            $ageColor = $isOld ? 'text-red-400 font-extrabold' : 'text-slate-500';
                        ?>
                            <div class="grid grid-cols-12 py-3.5 px-6 items-center text-base transition-all <?php echo $rowClass; ?>" data-token-id="<?php echo $tokenId; ?>">
                                <div class="col-span-2 text-center">
                                    <span class="font-mono font-extrabold <?php echo $rankColor . ' ' . $rankSize; ?>">
                                        #<?php echo $rank; ?>
                                    </span>
                                </div>
                                <div class="col-span-2 font-mono font-bold text-asb-300 text-base truncate">
                                    <?php echo $tokenId; ?>
                                    <?php if ($isOld): ?>
                                    <span class="old-record-badge ml-1">
                                        <i class="fa-regular fa-triangle-exclamation"></i>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-span-2 font-mono font-bold text-white text-base truncate">
                                    <?php echo htmlspecialchars($token['invoice_no']); ?>
                                </div>
                                <div class="col-span-2 font-semibold truncate pr-3 text-slate-200 text-base">
                                    <?php echo htmlspecialchars($token['supplier_name'] ?? 'N/A'); ?>
                                </div>
                                <div class="col-span-1 text-center font-semibold text-slate-400 text-base">
                                    <?php echo htmlspecialchars($token['floor_name'] ?? '—'); ?>
                                </div>
                                <div class="col-span-1 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="<?php echo $statusDot; ?>"></span>
                                        <span class="text-sm font-bold text-slate-300 hidden sm:inline"><?php echo $statusLabel; ?></span>
                                    </div>
                                </div>
                                <div class="col-span-1 text-center">
                                    <span class="font-mono text-sm <?php echo $ageColor; ?>">
                                        <?php echo $ageDisplay; ?>
                                        <?php if ($isOld): ?>
                                        <i class="fa-regular fa-triangle-exclamation text-red-400 ml-0.5"></i>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="col-span-1 text-right font-mono text-base text-slate-500 font-bold">
                                    <?php echo $timeStr; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="bg-slate-950/80 px-6 py-2.5 border-t border-white/5 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-4 text-sm text-slate-500">
                        <span>Showing <strong class="text-white text-base"><?php echo count($topTokens); ?></strong> of <strong class="text-white text-base"><?php echo $totalActive; ?></strong> active</span>
                        <span class="text-slate-700">|</span>
                        <span>Updated <span id="footer-time" class="text-white font-mono">Just now</span></span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-slate-500">Designed & Developed by</span>
                        <span class="font-extrabold text-white bg-gradient-to-r from-asb-600/30 to-asb-400/30 px-4 py-1.5 rounded-xl border border-asb-500/20 text-base">
                            Vexel IT by Kavizz
                        </span>
                        <span class="text-slate-600">|</span>
                        <span class="text-slate-500 text-xs">v3.0</span>
                    </div>
                </div>

            </div>

        </section>

    </main>

    <script>
        // ============================================
        // PERMISSION HANDLING - Only first time
        // ============================================
        function grantPermission() {
            localStorage.setItem('asb_tv_permission', 'granted');
            
            const overlay = document.getElementById('audio-overlay');
            overlay.classList.add('opacity-0');
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 700);
            
            initAudio();
            
            setTimeout(() => {
                checkAndAnnounceGreeting();
            }, 1500);
        }

        function checkPermission() {
            return localStorage.getItem('asb_tv_permission') === 'granted';
        }

        // ============================================
        // AUDIO INITIALIZATION
        // ============================================
        let isAudioUnlocked = false;
        let audioCtx = null;
        let femaleVoice = null;

        function initAudio() {
            if (!isAudioUnlocked) {
                try {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    if (audioCtx.state === 'suspended') audioCtx.resume();
                    
                    if ('speechSynthesis' in window) {
                        const utter = new SpeechSynthesisUtterance(' ');
                        utter.volume = 0;
                        window.speechSynthesis.speak(utter);
                    }

                    isAudioUnlocked = true;
                    
                    const overlay = document.getElementById('audio-overlay');
                    if (overlay && overlay.style.display !== 'none') {
                        overlay.classList.add('opacity-0');
                        setTimeout(() => {
                            overlay.style.display = 'none';
                        }, 700);
                    }
                } catch (e) {
                    console.warn('Audio init error:', e);
                }
            }
        }

        if (checkPermission()) {
            document.addEventListener('DOMContentLoaded', function() {
                const overlay = document.getElementById('audio-overlay');
                if (overlay) {
                    overlay.style.display = 'none';
                }
                initAudio();
                setTimeout(checkAndAnnounceGreeting, 2000);
            });
        }

        // ============================================
        // VOICE
        // ============================================
        function initFemaleVoice() {
            if ('speechSynthesis' in window) {
                const voices = window.speechSynthesis.getVoices();
                femaleVoice = voices.find(v => 
                    /female|zira|samantha|victoria|karen|fiona|veena|google us english|google uk english female/i.test(v.name)
                ) || voices.find(v => v.lang.startsWith('en')) || voices[0];
            }
        }

        if ('speechSynthesis' in window) {
            window.speechSynthesis.onvoiceschanged = initFemaleVoice;
            initFemaleVoice();
        }

        function speakMessage(text) {
            if ('speechSynthesis' in window && isAudioUnlocked) {
                try {
                    window.speechSynthesis.cancel();
                    const utterance = new SpeechSynthesisUtterance(text);
                    
                    if (!femaleVoice) initFemaleVoice();
                    if (femaleVoice) utterance.voice = femaleVoice;

                    utterance.rate = 0.85;
                    utterance.pitch = 1.05;
                    utterance.volume = 1;
                    window.speechSynthesis.speak(utterance);
                } catch (e) {
                    console.warn('Speech error:', e);
                }
            }
        }

        // ============================================
        // GREETING ANNOUNCEMENTS
        // ============================================
        let hasAnnouncedMorning = false;
        let hasAnnouncedEvening = false;

        function checkAndAnnounceGreeting() {
            const now = new Date();
            const hours = now.getHours();
            const minutes = now.getMinutes();
            const day = now.getDay();

            if (day === 0) return;

            if (hours === 8 && minutes >= 30 && minutes < 35 && !hasAnnouncedMorning) {
                hasAnnouncedMorning = true;
                hasAnnouncedEvening = false;
                const msg = "Good Morning! Welcome to ASB Group of Companies IT Department. Systems are now fully operational. Have a productive day!";
                showPopup("Good Morning! 🌅", msg, "Start of Working Day", "fa-regular fa-sun text-amber-400", 10000);
                speakMessage(msg);
                return;
            }

            if (hours === 17 && minutes >= 30 && minutes < 35 && !hasAnnouncedEvening) {
                hasAnnouncedEvening = true;
                hasAnnouncedMorning = false;
                const msg = "Thank you for your hard work today. The IT Department operations are closing. Take care and see you tomorrow!";
                showPopup("Good Evening! 🌙", msg, "End of Working Day", "fa-regular fa-moon text-indigo-400", 10000);
                speakMessage(msg);
                return;
            }

            if (hours === 0 && minutes === 0) {
                hasAnnouncedMorning = false;
                hasAnnouncedEvening = false;
            }
        }

        // ============================================
        // POPUP
        // ============================================
        let popupTimer = null;
        let popupCountdown = null;

        function showPopup(title, body, tag = "ASB IT Notification", iconClass = "fa-regular fa-bullhorn", autoDismissMs = 7000) {
            if (popupTimer) clearTimeout(popupTimer);
            if (popupCountdown) clearInterval(popupCountdown);

            document.getElementById('popup-title').textContent = title;
            document.getElementById('popup-body').textContent = body;
            document.getElementById('popup-tag').textContent = tag;
            document.getElementById('popup-icon').className = iconClass;
            document.getElementById('popup-modal').classList.remove('hidden');
            document.getElementById('popup-modal').classList.add('flex');

            let remaining = Math.floor(autoDismissMs / 1000);
            document.getElementById('popup-timer').textContent = remaining;
            
            popupCountdown = setInterval(() => {
                remaining--;
                document.getElementById('popup-timer').textContent = remaining;
                if (remaining <= 0) clearInterval(popupCountdown);
            }, 1000);

            popupTimer = setTimeout(() => {
                closePopup();
            }, autoDismissMs);
        }

        function closePopup() {
            if (popupTimer) clearTimeout(popupTimer);
            if (popupCountdown) clearInterval(popupCountdown);
            document.getElementById('popup-modal').classList.add('hidden');
            document.getElementById('popup-modal').classList.remove('flex');
        }

        // ============================================
        // CLOCK
        // ============================================
        function updateClockAndGreetings() {
            const now = new Date();
            const hours = now.getHours();

            document.getElementById('live-clock').textContent = now.toLocaleTimeString();
            document.getElementById('live-date').textContent = now.toLocaleDateString(undefined, { 
                weekday: 'long', 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric' 
            });

            const greetingEl = document.getElementById('dynamic-greeting');
            if (hours >= 5 && hours < 12) {
                greetingEl.innerHTML = `<i class="fa-regular fa-sun text-amber-400 text-lg"></i> Good Morning`;
            } else if (hours >= 12 && hours < 17) {
                greetingEl.innerHTML = `<i class="fa-regular fa-cloud-sun text-amber-300 text-lg"></i> Good Afternoon`;
            } else if (hours >= 17 && hours < 21) {
                greetingEl.innerHTML = `<i class="fa-regular fa-cloud-moon text-indigo-400 text-lg"></i> Good Evening`;
            } else {
                greetingEl.innerHTML = `<i class="fa-regular fa-moon text-slate-300 text-lg"></i> Good Night`;
            }

            checkAndAnnounceGreeting();

            const timeStr = now.toLocaleTimeString();
            document.getElementById('footer-time').textContent = timeStr;
            document.getElementById('last-updated').textContent = timeStr;
        }

        setInterval(updateClockAndGreetings, 1000);
        updateClockAndGreetings();

        // ============================================
        // WELCOME SLIDESHOW
        // ============================================
        const slides = [
            {
                icon: "fa-regular fa-building",
                title: "Welcome to ASB Group IT",
                body: "Providing seamless technology infrastructure, automated document return workflows, and systems support for ASB Group of Companies.",
                img: "https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=800&q=80"
            },
            {
                icon: "fa-regular fa-shield-halved",
                title: "Quality Assurance Priority",
                body: "Every returned document undergoes rigorous verification. Please present your token number at the designated floor desk.",
                img: "https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=800&q=80"
            },
            {
                icon: "fa-regular fa-cloud-sun-rain",
                title: "Kalutara Operations",
                body: "Monitoring active weather conditions for Kalutara district logistics and transport operations.",
                img: "https://images.unsplash.com/photo-1515694346937-94d85e41e6f0?auto=format&fit=crop&w=800&q=80"
            },
            {
                icon: "fa-regular fa-rocket",
                title: "Innovation & Technology",
                body: "Empowering ASB Group with cutting-edge IT solutions and automated workflow management systems.",
                img: "https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=800&q=80"
            }
        ];

        let currentSlide = 0;
        function cycleSlideshow() {
            currentSlide = (currentSlide + 1) % slides.length;
            const item = slides[currentSlide];

            document.getElementById('slide-icon').className = item.icon;
            document.getElementById('slide-title').textContent = item.title;
            document.getElementById('slide-body').textContent = item.body;
            
            const img = document.getElementById('slide-image');
            img.style.opacity = '0';
            setTimeout(() => {
                img.src = item.img;
                img.style.opacity = '1';
            }, 300);

            const indicators = document.querySelectorAll('.slide-indicator');
            indicators.forEach((ind, idx) => {
                ind.className = (idx === currentSlide) 
                    ? "slide-indicator w-8 h-1.5 rounded-full bg-asb-400 transition-all duration-300"
                    : "slide-indicator w-3 h-1.5 rounded-full bg-slate-700 transition-all duration-300";
            });
        }
        setInterval(cycleSlideshow, 7000);

        // ============================================
        // WEATHER
        // ============================================
        async function fetchLiveWeather() {
            try {
                const response = await fetch('https://api.open-meteo.com/v1/forecast?latitude=6.5854&longitude=79.9607&current=temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m,wind_direction_10m&hourly=precipitation_probability&forecast_days=1');
                const data = await response.json();

                if (data?.current) {
                    const c = data.current;
                    const temp = Math.round(c.temperature_2m);
                    const windSpeed = Math.round(c.wind_speed_10m);
                    const precipitation = c.precipitation;
                    const windDir = getWindDirection(c.wind_direction_10m);
                    const rainProb = data.hourly?.precipitation_probability?.[new Date().getHours()] ?? 0;

                    document.getElementById('nav-temp').textContent = `${temp}°C`;
                    document.getElementById('nav-wind').textContent = `${windSpeed} km/h`;
                    document.getElementById('nav-rain').textContent = `${rainProb}%`;

                    const iconEl = document.getElementById('nav-weather-icon');
                    if (c.weather_code >= 50) {
                        iconEl.className = "fa-regular fa-cloud-showers-heavy text-asb-400 text-2xl animate-bounce";
                    } else if (c.weather_code >= 1 && c.weather_code <= 3) {
                        iconEl.className = "fa-regular fa-cloud-sun text-amber-400 text-2xl";
                    } else if (c.weather_code === 0) {
                        iconEl.className = "fa-regular fa-sun text-amber-400 text-2xl";
                    } else {
                        iconEl.className = "fa-regular fa-cloud text-asb-400 text-2xl";
                    }

                    document.getElementById('panel-rain').textContent = precipitation > 0 ? `${precipitation}mm` : 'None';
                    document.getElementById('panel-wind').textContent = `${windDir} ${windSpeed}km/h`;
                    document.getElementById('weather-sync-status').textContent = "Live ✓";
                }
            } catch (error) {
                console.warn('Weather fetch error:', error);
                document.getElementById('weather-sync-status').textContent = "Offline";
            }
        }

        function getWindDirection(deg) {
            const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
            return directions[Math.round(deg / 45) % 8];
        }

        fetchLiveWeather();
        setInterval(fetchLiveWeather, 600000);

        // ============================================
        // QUEUE FETCHING - 20 SECOND REFRESH
        // ============================================
        let lastTopPriorityToken = null;
        let isFirstFetch = true;

        async function fetchPendingTokens() {
            try {
                const response = await fetch('api_get_pending_tokens.php?limit=10');
                const data = await response.json();

                if (data.success && data.results) {
                    updateTable(data.results);
                    
                    if (data.results.length > 0) {
                        const topToken = data.results[0];
                        if (!lastTopPriorityToken || lastTopPriorityToken.token_id !== topToken.token_id) {
                            if (!isFirstFetch && isAudioUnlocked) {
                                const floor = topToken.floor_name || 'Main Counter';
                                const msg = `Attention. Priority number ${topToken.priority_no}. Invoice ${topToken.invoice_no}. Please proceed to ${floor}.`;
                                showPopup(
                                    `Priority #${topToken.priority_no} Called`,
                                    `Invoice ${topToken.invoice_no} is ready. Please proceed to ${floor}.`,
                                    "Token Announcement",
                                    "fa-regular fa-bullhorn",
                                    7000
                                );
                                speakMessage(msg);
                            }
                            lastTopPriorityToken = topToken;
                            isFirstFetch = false;
                        }
                    } else {
                        lastTopPriorityToken = null;
                    }
                }
            } catch (error) {
                console.warn('Queue fetch error:', error);
            }
        }

        function updateTable(tokens) {
            const tableBody = document.getElementById('table-body');
            
            if (!tokens || tokens.length === 0) {
                tableBody.innerHTML = `
                    <div class="p-16 text-center text-slate-500 font-bold text-xl flex flex-col items-center justify-center gap-4 h-full">
                        <div class="w-24 h-24 bg-slate-800/50 rounded-full flex items-center justify-center text-5xl text-slate-600">
                            <i class="fa-regular fa-circle-check"></i>
                        </div>
                        <span class="text-white text-2xl font-black">Queue Empty</span>
                        <span class="text-base text-slate-400">No priority items in queue.</span>
                    </div>
                `;
                return;
            }

            let html = '';
            tokens.forEach((token, index) => {
                const rank = index + 1;
                const isOld = token.days_old && token.days_old > 2;
                
                let rowClass = 'priority-row ';
                if (rank === 1) rowClass += 'top-1 glow-border';
                else if (rank === 2) rowClass += 'top-2';
                else if (rank === 3) rowClass += 'top-3';
                rowClass += ' hover:bg-slate-800/30';
                
                if (isOld) {
                    rowClass += ' old-record-row';
                }
                
                const statusDot = 'status-dot ' + (token.status || 'pending');
                const statusLabel = token.status ? token.status.charAt(0).toUpperCase() + token.status.slice(1) : 'Pending';
                const timeStr = token.created_at ? new Date(token.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '--:--';
                
                const rankColor = rank === 1 ? 'text-white' : (rank === 2 ? 'text-asb-300' : (rank === 3 ? 'text-asb-200' : 'text-slate-400'));
                const rankSize = rank === 1 ? 'text-4xl' : (rank === 2 ? 'text-3xl' : 'text-2xl');
                
                const daysOld = token.days_old || 0;
                const ageDisplay = daysOld + 'd';
                const ageColor = isOld ? 'text-red-400 font-extrabold' : 'text-slate-500';
                
                html += `
                    <div class="grid grid-cols-12 py-3.5 px-6 items-center text-base transition-all ${rowClass}" data-token-id="${escapeHtml(token.token_id)}">
                        <div class="col-span-2 text-center">
                            <span class="font-mono font-extrabold ${rankColor} ${rankSize}">
                                #${rank}
                            </span>
                        </div>
                        <div class="col-span-2 font-mono font-bold text-asb-300 text-base truncate">
                            ${escapeHtml(token.token_id)}
                            ${isOld ? `<span class="old-record-badge ml-1"><i class="fa-regular fa-triangle-exclamation"></i></span>` : ''}
                        </div>
                        <div class="col-span-2 font-mono font-bold text-white text-base truncate">
                            ${escapeHtml(token.invoice_no)}
                        </div>
                        <div class="col-span-2 font-semibold truncate pr-3 text-slate-200 text-base">
                            ${escapeHtml(token.supplier_name || 'N/A')}
                        </div>
                        <div class="col-span-1 text-center font-semibold text-slate-400 text-base">
                            ${escapeHtml(token.floor_name || '—')}
                        </div>
                        <div class="col-span-1 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <span class="${statusDot}"></span>
                                <span class="text-sm font-bold text-slate-300 hidden sm:inline">${statusLabel}</span>
                            </div>
                        </div>
                        <div class="col-span-1 text-center">
                            <span class="font-mono text-sm ${ageColor}">
                                ${ageDisplay}
                                ${isOld ? '<i class="fa-regular fa-triangle-exclamation text-red-400 ml-0.5"></i>' : ''}
                            </span>
                        </div>
                        <div class="col-span-1 text-right font-mono text-base text-slate-500 font-bold">
                            ${timeStr}
                        </div>
                    </div>
                `;
            });

            tableBody.innerHTML = html;
            
            // Update footer
            const footerSpan = document.querySelector('.flex.items-center.gap-4.text-sm.text-slate-500');
            if (footerSpan) {
                const activeCount = tokens.length;
                const totalActive = <?php echo $totalActive; ?>;
                footerSpan.innerHTML = `
                    Showing <strong class="text-white text-base">${activeCount}</strong> of <strong class="text-white text-base">${totalActive}</strong> active
                    <span class="text-slate-700">|</span>
                    <span>Updated <span id="footer-time" class="text-white font-mono">Just now</span></span>
                `;
            }
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        setTimeout(fetchPendingTokens, 1000);
        setInterval(fetchPendingTokens, 20000);

        // ============================================
        // CHECK PERMISSION ON LOAD
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            if (checkPermission()) {
                const overlay = document.getElementById('audio-overlay');
                if (overlay) {
                    overlay.style.display = 'none';
                }
                initAudio();
            }
        });
    </script>
</body>
</html>