<?php
require_once 'db.php';
require_once 'auth.php';
checkAuth();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
    $role_name = trim($_POST['role_name'] ?? '');
    if (!empty($role_name)) {
        $stmt = $pdo->prepare("INSERT INTO roles (role_name) VALUES (:name)");
        $stmt->execute([':name' => $role_name]);
        $msg = 'Role created successfully!';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_tabs'])) {
    $role_id = $_POST['role_id'];
    $assigned_tabs = $_POST['tabs'] ?? [];

    $pdo->prepare("DELETE FROM role_tabs WHERE role_id = :role_id")->execute([':role_id' => $role_id]);
    $insertStmt = $pdo->prepare("INSERT INTO role_tabs (role_id, tab_id) VALUES (:role_id, :tab_id)");
    foreach ($assigned_tabs as $tab_id) {
        $insertStmt->execute([':role_id' => $role_id, ':tab_id' => $tab_id]);
    }
    $msg = 'Permissions updated successfully!';
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY role_id ASC")->fetchAll(PDO::FETCH_ASSOC);
$allTabs = $pdo->query("SELECT * FROM tabs ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

$selectedRole = $_GET['role_id'] ?? ($roles[0]['role_id'] ?? null);
$currentAssignedTabs = [];

if ($selectedRole) {
    $stmt = $pdo->prepare("SELECT tab_id FROM role_tabs WHERE role_id = :role_id");
    $stmt->execute([':role_id' => $selectedRole]);
    $currentAssignedTabs = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roles & Access - Token System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-white">Role & Tab Permissions</h1>
            <a href="dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-xl text-xs font-bold transition">&larr; Back to Dashboard</a>
        </div>

        <?php if ($msg): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-xl text-sm"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl h-fit">
                <h2 class="text-lg font-bold text-white mb-4">Create New Role</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="create_role" value="1">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Role Name</label>
                        <input type="text" name="role_name" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500" placeholder="e.g. Supervisor">
                    </div>
                    <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white font-bold px-6 py-2 rounded-xl text-sm transition">Add Role</button>
                </form>
            </div>

            <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
                <h2 class="text-lg font-bold text-white mb-4">Assign Navigation Tabs</h2>
                <form method="GET" class="mb-6">
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Select Role</label>
                    <select name="role_id" onchange="this.form.submit()" class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['role_id'] ?>" <?= $selectedRole == $r['role_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['role_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($selectedRole): ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="assign_tabs" value="1">
                        <input type="hidden" name="role_id" value="<?= $selectedRole ?>">

                        <label class="block text-xs font-semibold text-slate-400 mb-2">Toggle Assigned Tabs:</label>
                        <div class="space-y-2 max-h-60 overflow-y-auto pr-2">
                            <?php foreach ($allTabs as $tab): ?>
                                <label class="flex items-center space-x-3 bg-slate-950 p-3 rounded-xl border border-slate-800 cursor-pointer hover:border-slate-700">
                                    <input type="checkbox" name="tabs[]" value="<?= $tab['tab_id'] ?>" <?= in_array($tab['tab_id'], $currentAssignedTabs) ? 'checked' : '' ?> class="rounded text-sky-600 focus:ring-0">
                                    <i class="<?= htmlspecialchars($tab['icon_class']) ?> text-sky-400 w-5"></i>
                                    <span class="text-sm font-semibold text-white"><?= htmlspecialchars($tab['tab_name']) ?></span>
                                    <span class="text-xs text-slate-500 font-mono ml-auto"><?= htmlspecialchars($tab['url_link']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 rounded-xl text-sm transition">
                            Save Tab Mapping
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>