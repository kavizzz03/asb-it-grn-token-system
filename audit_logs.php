<?php
require_once 'db.php';
require_once 'auth.php';
checkAuth();

// Fetch audit records
$stmt = $pdo->query("SELECT l.*, u.full_name FROM user_logs l JOIN users u ON l.user_id = u.user_id ORDER BY l.timestamp DESC LIMIT 100");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Audit Logs - Token System</title>
     <link rel="icon" type="image/png" href="logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-white">System Access Audit Logs</h1>
            <a href="dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-xl text-xs font-bold transition">&larr; Back to Dashboard</a>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-800 text-xs font-bold uppercase text-sky-400 border-b border-slate-700">
                    <tr>
                        <th class="p-4">Timestamp</th>
                        <th class="p-4">User</th>
                        <th class="p-4">Username</th>
                        <th class="p-4">Event Action</th>
                        <th class="p-4">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="p-8 text-center text-slate-500">No login or logout records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-4 font-mono text-xs text-slate-400"><?= htmlspecialchars($log['timestamp']) ?></td>
                                <td class="p-4 font-bold text-white"><?= htmlspecialchars($log['full_name']) ?></td>
                                <td class="p-4 font-mono"><?= htmlspecialchars($log['username']) ?></td>
                                <td class="p-4">
                                    <?php if ($log['action'] === 'LOGIN'): ?>
                                        <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2.5 py-1 rounded-full text-xs font-bold">
                                            <i class="fa-solid fa-right-to-bracket mr-1"></i> LOGIN
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-rose-500/10 text-rose-400 border border-rose-500/20 px-2.5 py-1 rounded-full text-xs font-bold">
                                            <i class="fa-solid fa-right-from-bracket mr-1"></i> LOGOUT
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 font-mono text-slate-400"><?= htmlspecialchars($log['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>