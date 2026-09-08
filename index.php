<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.username = :username AND u.password = :password");
        $stmt->execute([':username' => $username, ':password' => $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_id']   = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];

            // Log Login Event
            logUserAction($pdo, $user['user_id'], $user['username'], 'LOGIN');

            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Group - IT Department Token System</title>
     <link rel="icon" type="image/png" href="logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-mesh {
            background-color: #020617;
            background-image: 
                radial-gradient(at 0% 0%, rgba(14, 165, 233, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(56, 189, 248, 0.1) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 0.5) 0px, transparent 100%);
        }
        .glass-panel {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="h-full bg-mesh text-slate-100 flex flex-col justify-between selection:bg-sky-500 selection:text-white">

    <!-- Top Navigation / Branding Header -->
    <header class="w-full max-w-7xl mx-auto p-6 flex justify-between items-center z-10">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-sky-600 via-sky-500 to-cyan-400 flex items-center justify-center font-black text-white text-lg shadow-lg shadow-sky-500/20 ring-1 ring-white/20">
                ASB
            </div>
            <div class="flex flex-col">
                <span class="font-bold tracking-tight text-white leading-none">ASB Group of Companies</span>
                <span class="text-[10px] text-sky-400 tracking-widest uppercase font-semibold mt-0.5">IT Department Token System</span>
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs text-slate-400 bg-slate-900/60 border border-slate-800 px-3 py-1.5 rounded-full">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>IT Portal Operational</span>
        </div>
    </header>

    <!-- Main Container -->
    <main class="w-full max-w-6xl mx-auto px-4 py-6 flex-1 flex items-center justify-center z-10">
        <div class="w-full grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            
            <!-- Visual Hero Side (Visible on large screens) -->
            <div class="hidden lg:flex lg:col-span-7 flex-col justify-between h-full p-8 relative rounded-3xl overflow-hidden glass-card min-h-[560px]">
                <!-- Background Image Overlay with Gradient Mask -->
                <div class="absolute inset-0 z-0">
                    <img src="https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?q=80&w=1200&auto=format&fit=crop" alt="Abstract Security Grid" class="w-full h-full object-cover opacity-20 mix-blend-luminosity">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent"></div>
                </div>

                <div class="relative z-10">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold px-3 py-1 rounded-full bg-sky-500/10 border border-sky-500/20 text-sky-300">
                        <i class="fa-solid fa-shield-halved"></i> ASB IT Authorization Gateway
                    </span>
                </div>

                <div class="relative z-10 space-y-4 max-w-lg">
                    <h2 class="text-3xl font-extrabold tracking-tight text-white leading-tight">
                        ASB Group IT Department Token System
                    </h2>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        Internal security portal for managing authorization tokens, role-based resource permissions, and IT infrastructure logging across ASB Group of Companies.
                    </p>
                    
                    <!-- Feature Pills -->
                    <div class="pt-4 flex flex-wrap gap-3">
                        <div class="flex items-center gap-2 bg-slate-900/80 border border-slate-800 px-3 py-2 rounded-xl text-xs text-slate-300">
                            <i class="fa-solid fa-key text-sky-400"></i> Role-Based Access
                        </div>
                        <div class="flex items-center gap-2 bg-slate-900/80 border border-slate-800 px-3 py-2 rounded-xl text-xs text-slate-300">
                            <i class="fa-solid fa-clock-rotate-left text-sky-400"></i> Event Audit Logging
                        </div>
                        <div class="flex items-center gap-2 bg-slate-900/80 border border-slate-800 px-3 py-2 rounded-xl text-xs text-slate-300">
                            <i class="fa-solid fa-code text-sky-400"></i> Vexel IT Architecture
                        </div>
                    </div>
                </div>

                <!-- Developer Attribution inside hero card -->
                <div class="relative z-10 pt-8 border-t border-slate-800/60 flex items-center justify-between text-xs text-slate-400">
                    <span>ASB Infrastructure v2.4</span>
                    <span class="text-slate-300">
                        Designed & Developed by <strong class="text-sky-400 font-semibold">Vexel IT</strong> by <strong class="text-white font-semibold">Kavizz</strong>
                    </span>
                </div>
            </div>

            <!-- Login Form Side -->
            <div class="lg:col-span-5 w-full">
                <div class="glass-panel p-8 sm:p-10 rounded-3xl shadow-2xl relative">
                    
                    <div class="space-y-2 mb-8">
                        <h1 class="text-2xl font-bold tracking-tight text-white">IT System Sign In</h1>
                        <p class="text-xs text-slate-400">Enter your official credentials to access the token portal.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="mb-6 bg-red-500/10 border border-red-500/20 text-red-400 text-xs px-4 py-3.5 rounded-2xl flex items-center gap-3 animate-shake">
                            <div class="w-6 h-6 rounded-lg bg-red-500/20 flex items-center justify-center shrink-0 text-red-400">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <span class="font-medium"><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-5">
                        <!-- Username Field -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-300">Username</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 group-focus-within:text-sky-400 transition-colors">
                                    <i class="fa-solid fa-user-gear text-sm"></i>
                                </div>
                                <input type="text" name="username" required autocomplete="username"
                                    class="w-full bg-slate-950/80 border border-slate-800 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all duration-200" 
                                    placeholder="Enter your username">
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center">
                                <label class="block text-xs font-semibold text-slate-300">Password</label>
                            </div>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 group-focus-within:text-sky-400 transition-colors">
                                    <i class="fa-solid fa-lock text-sm"></i>
                                </div>
                                <input type="password" id="passwordInput" name="password" required autocomplete="current-password"
                                    class="w-full bg-slate-950/80 border border-slate-800 rounded-xl py-3 pl-10 pr-11 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all duration-200" 
                                    placeholder="••••••••••••">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition-colors focus:outline-none" title="Toggle password visibility">
                                    <i class="fa-solid fa-eye text-sm" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" 
                            class="w-full mt-2 bg-gradient-to-r from-sky-600 to-sky-500 hover:from-sky-500 hover:to-sky-400 text-white font-semibold py-3 px-4 rounded-xl transition duration-200 text-sm shadow-lg shadow-sky-600/25 flex items-center justify-center gap-2 group cursor-pointer active:scale-[0.99]">
                            <span>Authorize Access</span>
                            <i class="fa-solid fa-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </form>

                    <!-- Extra Help / System Note -->
                    <div class="mt-6 pt-6 border-t border-slate-800/80 text-center space-y-1">
                        <p class="text-[11px] text-slate-500">
                            Managed by ASB Group IT Department.
                        </p>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <!-- Global Footer -->
    <footer class="w-full max-w-7xl mx-auto p-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs text-slate-500 z-10">
        <div>
            &copy; <?= date('Y') ?> ASB Group of Companies — IT Department Token System.
        </div>
        <div class="flex items-center gap-1.5 text-slate-400">
            <span>Designed and Developed by</span>
            <span class="text-sky-400 font-medium">Vexel IT</span>
            <span>by</span>
            <span class="text-slate-200 font-semibold">Kavizz</span>
        </div>
    </footer>

    <!-- Password Toggle Visibility Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('passwordInput');
            const toggleButton = document.getElementById('togglePassword');
            const toggleIcon = document.getElementById('toggleIcon');

            toggleButton.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                
                if (isPassword) {
                    passwordInput.setAttribute('type', 'text');
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                    toggleIcon.classList.add('text-sky-400');
                } else {
                    passwordInput.setAttribute('type', 'password');
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.remove('text-sky-400');
                    toggleIcon.classList.add('fa-eye');
                }
            });
        });
    </script>
</body>
</html>