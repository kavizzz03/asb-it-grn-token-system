<?php
require_once 'db.php';
require_once 'auth.php';
checkAuth();

$currentUser = $_SESSION['username'] ?? ($_SESSION['user']['username'] ?? 'System User');
$currentUserId = $_SESSION['user_id'] ?? ($_SESSION['user']['user_id'] ?? null);

$msg = '';
$error = '';

// -------------------------------------------------------------------------
// 1. AJAX Auto-Suggestion Endpoint
// -------------------------------------------------------------------------
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT 
            t.token_id, t.order_no, t.invoice_no, t.status, t.priority_no, t.processed_by,
            c.company_name, b.branch_name
        FROM tokens t
        LEFT JOIN companies c ON t.company_id = c.company_id
        LEFT JOIN branches b ON t.branch_id = b.branch_id
        WHERE t.token_id LIKE :q 
           OR t.invoice_no LIKE :q 
           OR t.order_no LIKE :q 
           OR t.supplier_id LIKE :q 
           OR c.company_name LIKE :q 
           OR b.branch_name LIKE :q
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([':q' => "%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// -------------------------------------------------------------------------
// 2. Handle Form Submission: Status Change & Note Logging
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $token_id   = trim($_POST['token_id'] ?? '');
    $new_status = $_POST['status'] ?? '';
    $note       = trim($_POST['note'] ?? '');

    $allowed_statuses = ['pending', 'process', 'received', 'complete'];

    if (!in_array($new_status, $allowed_statuses)) {
        $error = "Invalid status selected.";
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch current token record
            $stmt = $pdo->prepare("SELECT * FROM tokens WHERE token_id = :id FOR UPDATE");
            $stmt->execute([':id' => $token_id]);
            $currentToken = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentToken) {
                throw new Exception("Token record not found.");
            }

            $previous_processed_by = $currentToken['processed_by'];
            $old_status            = $currentToken['status'];
            $old_priority          = $currentToken['priority_no'];

            // Priority Rule: If status is 'complete', force priority_no to 0
            $new_priority = ($new_status === 'complete') ? 0 : $old_priority;

            // Determine Handler & Replacement Logging
            if (empty($previous_processed_by)) {
                $assigned_handler = $currentUser;
            } elseif ($previous_processed_by !== $currentUser) {
                // Log replacement action
                $logStmt = $pdo->prepare("
                    INSERT INTO token_logs (token_id, user_id, action_type, old_value, new_value, note, created_at)
                    VALUES (:token_id, :user_id, 'REPLACEMENT', :old_val, :new_val, :note, NOW())
                ");
                $logStmt->execute([
                    ':token_id' => $token_id,
                    ':user_id'  => $currentUserId,
                    ':old_val'  => 'Handled by: ' . $previous_processed_by,
                    ':new_val'  => 'Replaced by: ' . $currentUser,
                    ':note'     => 'Handler replaced during status update.' . ($note ? " Note: $note" : "")
                ]);
                $assigned_handler = $currentUser;
            } else {
                $assigned_handler = $previous_processed_by;
            }

            // Update Tokens Table
            $updateStmt = $pdo->prepare("
                UPDATE tokens 
                SET status = :status, 
                    priority_no = :priority_no, 
                    processed_by = :processed_by, 
                    updated_at = NOW() 
                WHERE token_id = :token_id
            ");
            $updateStmt->execute([
                ':status'       => $new_status,
                ':priority_no'  => $new_priority,
                ':processed_by' => $assigned_handler,
                ':token_id'     => $token_id
            ]);

            // Log Status Change / Note Addition
            if ($old_status !== $new_status || !empty($note)) {
                $statusLogStmt = $pdo->prepare("
                    INSERT INTO token_logs (token_id, user_id, action_type, old_value, new_value, note, created_at)
                    VALUES (:token_id, :user_id, 'STATUS_CHANGE', :old_val, :new_val, :note, NOW())
                ");
                $statusLogStmt->execute([
                    ':token_id' => $token_id,
                    ':user_id'  => $currentUserId,
                    ':old_val'  => $old_status,
                    ':new_val'  => $new_status,
                    ':note'     => $note
                ]);
            }

            $pdo->commit();
            $msg = "Token status and handler details updated successfully.";

            header("Location: status_update.php?selected_id=" . urlencode($token_id) . "&msg=" . urlencode($msg));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------------
// 3. Fetch Selected Token Details & History
// -------------------------------------------------------------------------
$selectedToken = null;
$tokenLogs     = [];
$selected_id   = trim($_GET['selected_id'] ?? '');

if (!empty($selected_id)) {
    $stmt = $pdo->prepare("
        SELECT 
            t.*, 
            c.company_name, 
            b.branch_name
        FROM tokens t
        LEFT JOIN companies c ON t.company_id = c.company_id
        LEFT JOIN branches b ON t.branch_id = b.branch_id
        WHERE t.token_id = :id
    ");
    $stmt->execute([':id' => $selected_id]);
    $selectedToken = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedToken) {
        $logStmt = $pdo->prepare("
            SELECT l.*, u.username AS action_by_name 
            FROM token_logs l
            LEFT JOIN users u ON l.user_id = u.user_id
            WHERE l.token_id = :id
            ORDER BY l.created_at DESC
        ");
        $logStmt->execute([':id' => $selected_id]);
        $tokenLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Token Status & Handler Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body * { visibility: hidden; }
            #printableSection, #printableSection * { visibility: visible; }
            #printableSection { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 md:p-8">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Navigation Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 no-print">
            <div>
                <h1 class="text-2xl font-bold text-white">Token Status & Workflow Management</h1>
                <p class="text-xs text-slate-400">Search by Token ID, Invoice, Order, Company, Branch, or Supplier</p>
            </div>
            <a href="dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-xl text-xs font-bold transition">
                <i class="fa-solid fa-arrow-left mr-1"></i> Dashboard
            </a>
        </div>

        <?php if ($msg): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-xl text-sm no-print">
                <i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl text-sm no-print">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Search Bar with Live Suggestions -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl relative no-print">
            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Search Token Document</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <input type="text" id="searchInput" autocomplete="off" placeholder="Type Token ID, Invoice No, Order No, Supplier ID, Company, or Branch..." 
                    class="w-full bg-slate-950 border border-slate-800 rounded-xl py-3 pl-10 pr-4 text-sm text-white focus:outline-none focus:border-sky-500">
            </div>
            <div id="suggestionsBox" class="absolute left-6 right-6 mt-2 bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl z-50 max-h-60 overflow-y-auto hidden divide-y divide-slate-800"></div>
        </div>

        <?php if ($selectedToken): ?>
            <div id="printableSection" class="space-y-6">
                <!-- Summary Card -->
                <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <span class="text-xs text-slate-400 block mb-1">Token ID</span>
                        <span class="text-xl font-bold font-mono text-sky-400"><?= htmlspecialchars($selectedToken['token_id']) ?></span>
                        <span class="text-xs text-slate-400 block font-mono">Date: <?= htmlspecialchars($selectedToken['token_date']) ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block mb-1">Invoice / Order</span>
                        <span class="text-sm font-bold text-white block">Inv: <?= htmlspecialchars($selectedToken['invoice_no'] ?: 'N/A') ?></span>
                        <span class="text-xs text-slate-400 block">Order: <?= htmlspecialchars($selectedToken['order_no'] ?: 'N/A') ?></span>
                        <span class="text-xs text-slate-400 block"><?= htmlspecialchars($selectedToken['company_name'] ?: 'Company N/A') ?> (<?= htmlspecialchars($selectedToken['branch_name'] ?: 'Branch N/A') ?>)</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block mb-1">Status & Priority</span>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                <?= htmlspecialchars($selectedToken['status']) ?>
                            </span>
                            <span class="px-2 py-0.5 rounded text-xs font-mono bg-slate-800 text-amber-400">
                                Priority: <?= htmlspecialchars($selectedToken['priority_no'] ?? 0) ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block mb-1">Processed By</span>
                        <span class="text-sm font-bold text-white block">
                            <?= $selectedToken['processed_by'] ? htmlspecialchars($selectedToken['processed_by']) : '<span class="text-slate-500 italic">Unassigned</span>' ?>
                        </span>
                        <span class="text-xs text-slate-400 block font-mono">Created By: <?= htmlspecialchars($selectedToken['created_by']) ?></span>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Update Status Panel -->
                    <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl space-y-4 no-print h-fit">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                            <h2 class="text-lg font-bold text-white">Update Status & Handler</h2>
                            <button onclick="window.print()" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1.5 rounded-xl text-xs font-bold transition">
                                <i class="fa-solid fa-print mr-1"></i> Print
                            </button>
                        </div>

                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="token_id" value="<?= htmlspecialchars($selectedToken['token_id']) ?>">

                            <!-- Handler Info Box -->
                            <div class="bg-slate-950 border border-slate-800 p-3 rounded-xl text-xs space-y-1">
                                <span class="text-slate-400 block">Handler Auto-Assignment:</span>
                                <?php if (empty($selectedToken['processed_by'])): ?>
                                    <p class="text-amber-400">Unassigned. Updating will set handler to: <strong><?= htmlspecialchars($currentUser) ?></strong></p>
                                <?php elseif ($selectedToken['processed_by'] === $currentUser): ?>
                                    <p class="text-emerald-400">Currently processing by you (<strong><?= htmlspecialchars($currentUser) ?></strong>).</p>
                                <?php else: ?>
                                    <p class="text-rose-400">Previously assigned to: <strong><?= htmlspecialchars($selectedToken['processed_by']) ?></strong>.</p>
                                    <p class="text-slate-400">Updating will reassign handler to: <strong><?= htmlspecialchars($currentUser) ?></strong> and log replacement.</p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1">Status</label>
                                <select name="status" required class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2.5 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                                    <?php foreach (['pending' => 'Pending', 'process' => 'Process', 'received' => 'Received', 'complete' => 'Complete'] as $stKey => $stLabel): ?>
                                        <option value="<?= $stKey ?>" <?= $selectedToken['status'] === $stKey ? 'selected' : '' ?>><?= $stLabel ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-[10px] text-slate-500 mt-1">Selecting 'Complete' sets Priority No to 0 automatically.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1">Add Note / Remark</label>
                                <textarea name="note" rows="3" placeholder="Enter process notes or details..." class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-sm text-white focus:outline-none focus:border-sky-500"></textarea>
                            </div>

                            <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 text-white font-bold py-3 rounded-xl text-sm transition">
                                Update Status & Save Logs
                            </button>
                        </form>
                    </div>

                    <!-- Logs Timeline -->
                    <div class="lg:col-span-2 bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl space-y-4">
                        <div class="border-b border-slate-800 pb-3 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-white">Token History Logs</h2>
                            <span class="text-xs text-slate-400">Total Logs: <?= count($tokenLogs) ?></span>
                        </div>

                        <?php if (empty($tokenLogs)): ?>
                            <p class="text-xs text-slate-500 italic py-4">No historical logs found for this token.</p>
                        <?php else: ?>
                            <div class="space-y-3 max-h-[500px] overflow-y-auto pr-2">
                                <?php foreach ($tokenLogs as $log): ?>
                                    <div class="bg-slate-950 border border-slate-800 p-4 rounded-2xl text-xs space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-sky-400 uppercase font-mono tracking-wider">
                                                <?= htmlspecialchars($log['action_type']) ?>
                                            </span>
                                            <span class="text-slate-500 font-mono"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></span>
                                        </div>
                                        <div class="text-slate-300">
                                            <span class="text-slate-400">Logged By:</span> <strong><?= htmlspecialchars($log['action_by_name'] ?? 'System') ?></strong>
                                        </div>
                                        <?php if ($log['old_value'] || $log['new_value']): ?>
                                            <div class="grid grid-cols-2 gap-2 bg-slate-900/60 p-2 rounded-xl text-[11px] font-mono">
                                                <div><span class="text-slate-500 block">From:</span> <?= htmlspecialchars($log['old_value'] ?? 'N/A') ?></div>
                                                <div><span class="text-slate-500 block">To:</span> <?= htmlspecialchars($log['new_value'] ?? 'N/A') ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($log['note'])): ?>
                                            <div class="bg-amber-500/10 border border-amber-500/20 p-2.5 rounded-xl text-amber-300">
                                                <strong class="block text-[10px] uppercase text-amber-400">Note:</strong>
                                                <?= nl2br(htmlspecialchars($log['note'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-slate-900 border border-slate-800 p-12 rounded-3xl text-center space-y-3 no-print">
                <i class="fa-solid fa-file-circle-question text-4xl text-slate-600"></i>
                <p class="text-sm text-slate-400">No token selected. Search above by Token ID, Invoice, Order, Company, Branch, or Supplier to begin.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Live Search JavaScript -->
    <script>
        const searchInput = document.getElementById('searchInput');
        const suggestionsBox = document.getElementById('suggestionsBox');

        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length === 0) {
                suggestionsBox.classList.add('hidden');
                suggestionsBox.innerHTML = '';
                return;
            }

            fetch(`status_update.php?ajax_search=1&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    suggestionsBox.innerHTML = '';
                    if (data.length === 0) {
                        suggestionsBox.innerHTML = '<div class="p-3 text-xs text-slate-500 italic">No matching tokens found</div>';
                    } else {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'p-3 hover:bg-slate-800 cursor-pointer flex justify-between items-center text-xs transition';
                            div.innerHTML = `
                                <div>
                                    <strong class="text-white block font-mono">#${item.token_id} (Inv: ${item.invoice_no || 'N/A'})</strong>
                                    <span class="text-slate-400">Order: ${item.order_no || 'N/A'} | ${item.company_name || ''} - ${item.branch_name || ''}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-sky-500/10 text-sky-400 border border-sky-500/20 font-bold uppercase">${item.status}</span>
                            `;
                            div.onclick = function() {
                                window.location.href = `status_update.php?selected_id=${encodeURIComponent(item.token_id)}`;
                            };
                            suggestionsBox.appendChild(div);
                        });
                    }
                    suggestionsBox.classList.remove('hidden');
                });
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.classList.add('hidden');
            }
        });
    </script>
</body>
</html>