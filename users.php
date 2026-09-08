<?php
require_once 'db.php';
require_once 'auth.php';
checkAuth();

$msg = '';
$error = '';

// Handle CREATE User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $role_id    = $_POST['role_id'] ?? '';
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $full_name  = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');

    if (!preg_match('/^94\d{9}$/', $contact_no)) {
        $error = 'Contact number must start with 94 followed by 9 digits (e.g., 94771234567).';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (role_id, username, password, full_name, email, contact_no) VALUES (:role_id, :username, :password, :full_name, :email, :contact_no)");
            $stmt->execute([
                ':role_id'    => $role_id,
                ':username'   => $username,
                ':password'   => $password,
                ':full_name'  => $full_name,
                ':email'      => $email,
                ':contact_no' => $contact_no
            ]);
            $msg = 'User created successfully!';
        } catch (PDOException $e) {
            $error = 'Database error or duplicate record: ' . $e->getMessage();
        }
    }
}

// Handle UPDATE User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $user_id    = (int)$_POST['user_id'];
    $role_id    = $_POST['role_id'] ?? '';
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $full_name  = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');

    if (!preg_match('/^94\d{9}$/', $contact_no)) {
        $error = 'Contact number must start with 94 followed by 9 digits (e.g., 94771234567).';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role_id = :role_id, username = :username, password = :password, full_name = :full_name, email = :email, contact_no = :contact_no WHERE user_id = :user_id");
            $stmt->execute([
                ':user_id'    => $user_id,
                ':role_id'    => $role_id,
                ':username'   => $username,
                ':password'   => $password,
                ':full_name'  => $full_name,
                ':email'      => $email,
                ':contact_no' => $contact_no
            ]);
            $msg = 'User updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating user: ' . $e->getMessage();
        }
    }
}

// Handle DELETE User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $user_id = (int)$_POST['user_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $msg = 'User deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Error deleting user: ' . $e->getMessage();
    }
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY role_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id ORDER BY u.user_id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - Token System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-white">User Management</h1>
            <a href="dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-xl text-xs font-bold transition">&larr; Back to Dashboard</a>
        </div>

        <?php if ($msg): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-xl text-sm"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Create User Form -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
            <h2 class="text-lg font-bold text-white mb-4">Add User Account</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Full Name</label>
                    <input type="text" name="full_name" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Username</label>
                    <input type="text" name="username" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Password (Plain Text)</label>
                    <input type="text" name="password" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Email</label>
                    <input type="email" name="email" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Contact No (Must start with 94)</label>
                    <input type="text" name="contact_no" placeholder="94771234567" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Assign Role</label>
                    <select name="role_id" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2 lg:col-span-3 flex justify-end">
                    <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white font-bold px-6 py-2.5 rounded-xl text-sm transition">Save User</button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-800 text-xs font-bold uppercase text-sky-400 border-b border-slate-700">
                    <tr>
                        <th class="p-4">Name</th>
                        <th class="p-4">Username</th>
                        <th class="p-4">Password</th>
                        <th class="p-4">Role</th>
                        <th class="p-4">Email</th>
                        <th class="p-4">Contact</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-4 font-bold text-white"><?= htmlspecialchars($u['full_name']) ?></td>
                            <td class="p-4 font-mono"><?= htmlspecialchars($u['username']) ?></td>
                            <td class="p-4 font-mono text-amber-400"><?= htmlspecialchars($u['password']) ?></td>
                            <td class="p-4"><span class="bg-sky-500/10 text-sky-400 border border-sky-500/20 px-2.5 py-1 rounded-full text-xs font-bold"><?= htmlspecialchars($u['role_name']) ?></span></td>
                            <td class="p-4"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="p-4 font-mono"><?= htmlspecialchars($u['contact_no']) ?></td>
                            <td class="p-4 text-right space-x-2">
                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)" class="bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 px-3 py-1.5 rounded-xl text-xs font-bold transition">
                                    <i class="fa-solid fa-pen mr-1"></i> Edit
                                </button>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" class="inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <button type="submit" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-3 py-1.5 rounded-xl text-xs font-bold transition">
                                        <i class="fa-solid fa-trash mr-1"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-2xl w-full max-w-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-white">Edit User Account</h3>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Username</label>
                    <input type="text" name="username" id="edit_username" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Password</label>
                    <input type="text" name="password" id="edit_password" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Email</label>
                    <input type="email" name="email" id="edit_email" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Contact No (Must start with 94)</label>
                    <input type="text" name="contact_no" id="edit_contact_no" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Assign Role</label>
                    <select name="role_id" id="edit_role_id" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2 flex justify-end space-x-3 mt-2">
                    <button type="button" onclick="closeEditModal()" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-xl text-sm font-bold transition">Cancel</button>
                    <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white font-bold px-6 py-2 rounded-xl text-sm transition">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_password').value = user.password;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_contact_no').value = user.contact_no;
            document.getElementById('edit_role_id').value = user.role_id;
            
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }
    </script>
</body>
</html>