<?php
require_once 'db.php';
require_once 'auth.php';
checkAuth();

$msg = '';
$error = '';

// Handle New Tab Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tab'])) {
    $tab_name   = trim($_POST['tab_name'] ?? '');
    $url_link   = trim($_POST['url_link'] ?? '');
    $icon_class = trim($_POST['icon_class'] ?? 'fa-solid fa-link');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if (!empty($tab_name) && !empty($url_link)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tabs (tab_name, url_link, icon_class, sort_order) VALUES (:tab_name, :url_link, :icon_class, :sort_order)");
            $stmt->execute([
                ':tab_name'   => $tab_name,
                ':url_link'   => $url_link,
                ':icon_class' => $icon_class,
                ':sort_order' => $sort_order
            ]);
            $msg = 'New navigation tab added successfully!';
        } catch (PDOException $e) {
            $error = 'Error adding tab: ' . $e->getMessage();
        }
    } else {
        $error = 'Please provide both Tab Name and URL Link.';
    }
}

// Handle Tab Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tab'])) {
    $tab_id = (int)$_POST['tab_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM tabs WHERE tab_id = :tab_id");
        $stmt->execute([':tab_id' => $tab_id]);
        $msg = 'Navigation tab removed successfully!';
    } catch (PDOException $e) {
        $error = 'Error deleting tab: ' . $e->getMessage();
    }
}

// Fetch all tabs ordered by sort_order
$tabs = $pdo->query("SELECT * FROM tabs ORDER BY sort_order ASC, tab_id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tab Administration - Token System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-white">Navigation Tab Admin</h1>
            <a href="dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-xl text-xs font-bold transition">&larr; Back to Dashboard</a>
        </div>

        <?php if ($msg): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-xl text-sm"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Create Tab Form -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
            <h2 class="text-lg font-bold text-white mb-4">Add New Tab Module</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <input type="hidden" name="create_tab" value="1">
                
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Tab Name</label>
                    <input type="text" name="tab_name" required placeholder="e.g. Reports" class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">URL Link</label>
                    <input type="text" name="url_link" required placeholder="e.g. reports.php" class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">FontAwesome Icon Class</label>
                    <input type="text" name="icon_class" placeholder="fa-solid fa-chart-line" value="fa-solid fa-link" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="1" min="0" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>

                <div class="md:col-span-2 lg:col-span-4 flex justify-end">
                    <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white font-bold px-6 py-2.5 rounded-xl text-sm transition">Add Navigation Tab</button>
                </div>
            </form>
        </div>

        <!-- System Navigation Tabs List -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-800 text-xs font-bold uppercase text-sky-400 border-b border-slate-700">
                    <tr>
                        <th class="p-4 text-center">Order</th>
                        <th class="p-4">Icon</th>
                        <th class="p-4">Tab Name</th>
                        <th class="p-4">URL Path</th>
                        <th class="p-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php if (empty($tabs)): ?>
                        <tr><td colspan="5" class="p-8 text-center text-slate-500">No navigation tabs defined.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tabs as $t): ?>
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-4 font-mono font-bold text-center text-slate-400"><?= (int)$t['sort_order'] ?></td>
                                <td class="p-4 text-sky-400 text-lg"><i class="<?= htmlspecialchars($t['icon_class']) ?>"></i></td>
                                <td class="p-4 font-bold text-white"><?= htmlspecialchars($t['tab_name']) ?></td>
                                <td class="p-4 font-mono text-xs text-slate-400"><?= htmlspecialchars($t['url_link']) ?></td>
                                <td class="p-4 text-right">
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this tab?');" class="inline">
                                        <input type="hidden" name="delete_tab" value="1">
                                        <input type="hidden" name="tab_id" value="<?= $t['tab_id'] ?>">
                                        <button type="submit" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-3 py-1.5 rounded-xl text-xs font-bold transition">
                                            <i class="fa-solid fa-trash mr-1"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>