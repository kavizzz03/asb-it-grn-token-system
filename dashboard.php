<?php
require_once 'db.php';
require_once 'auth.php';
checkAuth();

$assignedTabs = getAssignedTabs($pdo, $_SESSION['role_id']);

// Default fallback data if empty
if (empty($assignedTabs)) {
    $assignedTabs = [];
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Smart Token System - Executive Dashboard</title>
     <link rel="icon" type="image/png" href="logo.png">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            900: '#0c4a6e',
                            950: '#030712'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts & Font Awesome Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom Micro-Animations & Custom Scrollbar -->
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* Glassmorphism Styles */
        .glass-panel {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .glass-panel-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-panel-hover:hover {
            transform: translateY(-4px);
            background: rgba(30, 41, 59, 0.75);
            border-color: rgba(56, 189, 248, 0.3);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(14, 165, 233, 0.2);
        }

        /* Glowing Effects */
        .glow-sky {
            box-shadow: 0 0 25px -5px rgba(14, 165, 233, 0.3);
        }

        /* Hero Slideshow Transitions */
        .slide-fade {
            transition: opacity 1s ease-in-out;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #030712;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #0ea5e9;
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col selection:bg-sky-500 selection:text-white antialiased overflow-x-hidden">

    <!-- Top Glow Accent Line -->
    <div class="h-1 w-full bg-gradient-to-r from-sky-500 via-indigo-500 to-cyan-400"></div>

    <!-- Header / Navbar -->
    <header class="sticky top-0 z-50 glass-panel border-b border-slate-800/80 px-6 py-3.5">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            
            <!-- Brand & Division Title -->
            <div class="flex items-center space-x-4">
                <div class="relative group">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-sky-500 to-indigo-600 rounded-2xl blur opacity-75 group-hover:opacity-100 transition duration-300"></div>
                    <div class="relative bg-slate-900 border border-slate-700 text-white font-black px-3.5 py-1.5 rounded-xl text-xl tracking-wider">
                        ASB
                    </div>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-extrabold uppercase tracking-widest text-sky-400 bg-sky-500/10 px-2 py-0.5 rounded-md border border-sky-500/20">IT Department</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5 animate-pulse"></span> Systems Active
                        </span>
                    </div>
                    <h1 class="text-sm font-bold text-slate-200 tracking-tight hidden sm:block">Smart Token System Management</h1>
                </div>
            </div>

            <!-- Live Sri Lanka Clock & User Profile Bar -->
            <div class="flex items-center space-x-6 text-sm">
                
                <!-- Live Clock (Sri Lanka / Colombo Timezone) -->
                <div class="hidden md:flex flex-col items-end border-r border-slate-800 pr-6">
                    <div class="flex items-center text-sky-400 font-mono text-xs font-semibold gap-1.5">
                        <i class="fa-solid fa-clock text-[11px] animate-pulse"></i>
                        <span id="slClock">00:00:00 AM</span>
                    </div>
                    <div class="text-[11px] font-medium text-slate-400 flex items-center gap-1 mt-0.5">
                        <i class="fa-solid fa-location-dot text-slate-500 text-[10px]"></i>
                        <span>Asia/Colombo (SLST)</span>
                    </div>
                </div>

                <!-- User Information -->
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-sky-500/20 text-sm">
                        <?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <div class="font-bold text-slate-100 text-xs leading-snug"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Authorized User') ?></div>
                        <div class="text-[11px] text-sky-400 font-medium"><?= htmlspecialchars($_SESSION['role_name'] ?? 'System Operator') ?></div>
                    </div>
                </div>

                <!-- Logout Button -->
                <a href="logout.php" class="bg-slate-800/80 hover:bg-rose-500/20 hover:text-rose-400 border border-slate-700/80 hover:border-rose-500/40 text-slate-300 px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 group">
                    <i class="fa-solid fa-right-from-bracket group-hover:translate-x-0.5 transition-transform"></i>
                    <span class="hidden lg:inline">Logout</span>
                </a>
            </div>

        </div>
    </header>

    <!-- Main Content Container -->
    <div class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col gap-8">
        
        <!-- Hero Section: Dynamic Image Slideshow & Quick Metrics -->
        <div class="relative rounded-3xl overflow-hidden border border-slate-800 bg-slate-900 shadow-2xl min-h-[260px] flex items-center">
            
            <!-- Slideshow Background Images -->
            <div id="slideshow-container" class="absolute inset-0 z-0">
                <div class="slide-fade absolute inset-0 bg-cover bg-center opacity-40 transition-opacity duration-1000" style="background-image: url('https://images.unsplash.com/photo-1551836022-d5d88e9218df?q=80&w=1600&auto=format&fit=crop');"></div>
                <div class="slide-fade absolute inset-0 bg-cover bg-center opacity-0 transition-opacity duration-1000" style="background-image: url('https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=1600&auto=format&fit=crop');"></div>
                <div class="slide-fade absolute inset-0 bg-cover bg-center opacity-0 transition-opacity duration-1000" style="background-image: url('https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?q=80&w=1600&auto=format&fit=crop');"></div>
            </div>

            <!-- Gradient Overlays -->
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/80 to-transparent z-10"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent z-10"></div>

            <!-- Banner Hero Content -->
            <div class="relative z-20 p-8 sm:p-10 w-full flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-sky-500/10 border border-sky-500/30 text-sky-300 text-xs font-semibold mb-3">
                        <i class="fa-solid fa-microchip text-sky-400"></i> Smart Enterprise Queue Platform
                    </div>
                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight">
                        Welcome Back, <span class="bg-gradient-to-r from-sky-400 via-cyan-300 to-indigo-300 bg-clip-text text-transparent"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
                    </h2>
                    <p class="text-slate-300 text-sm mt-2 font-normal leading-relaxed">
                        ASB Group IT Smart Token infrastructure streamlines operational workflows, printing tickets, managing queue priority, and monitoring real-time service metrics.
                    </p>
                </div>

                <!-- Quick Operational Badges -->
                <div class="flex flex-wrap md:flex-col gap-3 min-w-[200px]">
                    <div class="glass-panel px-4 py-2.5 rounded-2xl flex items-center justify-between gap-4 border-l-4 border-l-sky-500 w-full">
                        <div>
                            <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Date (Sri Lanka)</div>
                            <div id="slDate" class="text-xs font-mono font-bold text-white">Loading...</div>
                        </div>
                        <i class="fa-regular fa-calendar text-sky-400 text-lg"></i>
                    </div>
                    <div class="glass-panel px-4 py-2.5 rounded-2xl flex items-center justify-between gap-4 border-l-4 border-l-emerald-500 w-full">
                        <div>
                            <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Assigned Modules</div>
                            <div class="text-xs font-mono font-bold text-white"><?= count($assignedTabs) ?> Active Tools</div>
                        </div>
                        <i class="fa-solid fa-cubes text-emerald-400 text-lg"></i>
                    </div>
                </div>
            </div>

            <!-- Slideshow Controls -->
            <div class="absolute bottom-3 right-6 z-30 flex items-center space-x-2">
                <button onclick="changeSlide(0)" class="slide-dot w-2.5 h-2.5 rounded-full bg-sky-400 transition-all"></button>
                <button onclick="changeSlide(1)" class="slide-dot w-2.5 h-2.5 rounded-full bg-slate-600 hover:bg-slate-400 transition-all"></button>
                <button onclick="changeSlide(2)" class="slide-dot w-2.5 h-2.5 rounded-full bg-slate-600 hover:bg-slate-400 transition-all"></button>
            </div>
        </div>

        <!-- Metric Pulse Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            
            <div class="glass-panel p-5 rounded-2xl border border-slate-800 flex items-center justify-between">
                <div>
                    <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider">System State</div>
                    <div class="text-xl font-bold text-white mt-1">Operational</div>
                    <div class="text-[11px] text-emerald-400 mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-circle-check"></i> 100% Uptime Sync
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400 text-xl">
                    <i class="fa-solid fa-server"></i>
                </div>
            </div>

            <div class="glass-panel p-5 rounded-2xl border border-slate-800 flex items-center justify-between">
                <div>
                    <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Network Latency</div>
                    <div class="text-xl font-bold text-white mt-1">12 ms</div>
                    <div class="text-[11px] text-sky-400 mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-bolt"></i> Low Latency Local
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl">
                    <i class="fa-solid fa-network-wired"></i>
                </div>
            </div>

            <div class="glass-panel p-5 rounded-2xl border border-slate-800 flex items-center justify-between">
                <div>
                    <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Security Access</div>
                    <div class="text-xl font-bold text-white mt-1">Encrypted</div>
                    <div class="text-[11px] text-indigo-400 mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-shield-halved"></i> Role Authentication
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-xl">
                    <i class="fa-solid fa-lock"></i>
                </div>
            </div>

            <div class="glass-panel p-5 rounded-2xl border border-slate-800 flex items-center justify-between">
                <div>
                    <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Printing Gateway</div>
                    <div class="text-xl font-bold text-white mt-1">ESC/POS Thermal</div>
                    <div class="text-[11px] text-cyan-400 mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-print"></i> 72mm Format Ready
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-xl">
                    <i class="fa-solid fa-ticket"></i>
                </div>
            </div>

        </div>

        <!-- Assigned System Modules / Access Grid -->
        <div>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-xl font-bold text-white tracking-tight">Assigned System Modules</h3>
                    <p class="text-slate-400 text-xs mt-0.5">Select an authorized control module to launch operations</p>
                </div>
                <span class="text-xs font-mono font-semibold text-slate-400 bg-slate-900 border border-slate-800 px-3 py-1.5 rounded-xl">
                    Role: <span class="text-sky-400"><?= htmlspecialchars($_SESSION['role_name'] ?? 'Default') ?></span>
                </span>
            </div>

            <?php if (empty($assignedTabs)): ?>
                <!-- Empty State -->
                <div class="glass-panel rounded-3xl p-12 text-center border border-dashed border-slate-800">
                    <div class="w-16 h-16 rounded-3xl bg-slate-800/80 border border-slate-700 flex items-center justify-center text-slate-400 text-2xl mx-auto mb-4">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <h4 class="text-lg font-bold text-white">No Modules Assigned</h4>
                    <p class="text-slate-400 text-xs max-w-md mx-auto mt-1">
                        Your account role currently has no active operational modules assigned. Please contact the IT System Administrator to request permission access.
                    </p>
                </div>
            <?php else: ?>
                <!-- Interactive Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($assignedTabs as $index => $tab): ?>
                        <a href="<?= htmlspecialchars($tab['url_link']) ?>" class="glass-panel glass-panel-hover rounded-3xl p-6 flex flex-col justify-between group relative overflow-hidden">
                            
                            <!-- Glowing background ambient light -->
                            <div class="absolute -right-10 -top-10 w-32 h-32 bg-sky-500/10 rounded-full blur-2xl group-hover:bg-sky-500/20 transition-all duration-500"></div>

                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-sky-500/20 to-indigo-500/10 border border-sky-500/30 flex items-center justify-center text-sky-400 text-xl group-hover:scale-110 group-hover:bg-sky-500 group-hover:text-white transition-all duration-300 shadow-lg">
                                        <i class="<?= htmlspecialchars($tab['icon_class'] ?: 'fa-solid fa-layer-group') ?>"></i>
                                    </div>
                                    <span class="text-[10px] font-mono font-bold text-slate-500 group-hover:text-sky-400 transition">
                                        MODULE #0<?= $index + 1 ?>
                                    </span>
                                </div>

                                <h4 class="text-lg font-bold text-white group-hover:text-sky-300 transition-colors mb-2">
                                    <?= htmlspecialchars($tab['tab_name']) ?>
                                </h4>
                                
                                <p class="text-slate-400 text-xs leading-relaxed line-clamp-2">
                                    Access operational procedures, manage entries, and execute administrative workflows for <?= htmlspecialchars($tab['tab_name']) ?>.
                                </p>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between text-xs font-semibold text-slate-400 group-hover:text-sky-400 transition-colors">
                                <span>Launch Workspace</span>
                                <i class="fa-solid fa-arrow-right-long group-hover:translate-x-1.5 transition-transform duration-300"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Corporate Footer with Developer Credits -->
    <footer class="mt-auto glass-panel border-t border-slate-800/80 py-6 px-6">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
            
            <!-- Corporate Brand Details -->
            <div class="text-center md:text-left">
                <div class="font-bold text-slate-300 flex items-center justify-center md:justify-start gap-2">
                    <span class="bg-sky-500/20 text-sky-400 text-[10px] font-extrabold px-2 py-0.5 rounded border border-sky-500/30">ASB GROUP</span>
                    <span>ASB Group of Companies &bull; IT Department</span>
                </div>
                <p class="text-slate-500 text-[11px] mt-1">Smart Enterprise Token & Queue Management Infrastructure</p>
            </div>

            <!-- Software Engineering Credits -->
            <div class="text-center md:text-right border-t md:border-t-0 border-slate-800 pt-3 md:pt-0 w-full md:w-auto">
                <div class="text-slate-400">
                    Designed & Developed by <span class="font-bold text-sky-400 hover:text-sky-300 transition">Vexel IT by Kavizz</span>
                </div>
                <div class="text-[10px] text-slate-600 font-mono mt-0.5">
                    System Version 2.5 &bull; Sri Lanka Regional Server
                </div>
            </div>

        </div>
    </footer>

    <!-- Interactive Scripts & Time Sync -->
    <script>
        // Real-time Sri Lanka (Asia/Colombo) Clock
        function updateSLClock() {
            const options = {
                timeZone: 'Asia/Colombo',
                hour12: true,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };

            const dateOptions = {
                timeZone: 'Asia/Colombo',
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            };

            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', options);
            const dateString = now.toLocaleDateString('en-US', dateOptions);

            const clockElement = document.getElementById('slClock');
            const dateElement = document.getElementById('slDate');

            if (clockElement) clockElement.textContent = timeString;
            if (dateElement) dateElement.textContent = dateString;
        }

        setInterval(updateSLClock, 1000);
        updateSLClock();

        // Banner Image Slideshow Controller
        let currentSlide = 0;
        const slides = document.querySelectorAll('#slideshow-container .slide-fade');
        const dots = document.querySelectorAll('.slide-dot');

        function changeSlide(index) {
            slides.forEach((slide, i) => {
                if (i === index) {
                    slide.classList.remove('opacity-0');
                    slide.classList.add('opacity-40');
                } else {
                    slide.classList.remove('opacity-40');
                    slide.classList.add('opacity-0');
                }
            });

            dots.forEach((dot, i) => {
                if (i === index) {
                    dot.classList.remove('bg-slate-600');
                    dot.classList.add('bg-sky-400');
                } else {
                    dot.classList.remove('bg-sky-400');
                    dot.classList.add('bg-slate-600');
                }
            });

            currentSlide = index;
        }

        function autoSlide() {
            let nextSlide = (currentSlide + 1) % slides.length;
            changeSlide(nextSlide);
        }

        setInterval(autoSlide, 5000);
    </script>
</body>
</html>