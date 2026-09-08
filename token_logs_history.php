<?php 
require_once 'db.php'; 

date_default_timezone_set('Asia/Colombo');

// ---------------------------------------------------------------------
// 1. AJAX API Endpoint: Fetch Token Logs dynamically for Modal
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'fetch_logs' && !empty($_GET['token_id'])) {
    header('Content-Type: application/json');
    try {
        $logQuery = "
            SELECT 
                tl.log_id, 
                tl.token_id, 
                tl.user_id, 
                COALESCE(u.name, u.username) AS user_name,
                tl.action_type, 
                tl.old_value, 
                tl.new_value, 
                tl.note, 
                tl.created_at 
            FROM token_logs tl
            LEFT JOIN users u ON tl.user_id = u.user_id
            WHERE tl.token_id = :token_id 
            ORDER BY tl.created_at DESC
        ";
        
        try {
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute(['token_id' => trim($_GET['token_id'])]);
            $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Fallback join condition if primary key in users table is 'id' instead of 'user_id'
            $logQuery = str_replace("tl.user_id = u.user_id", "tl.user_id = u.id", $logQuery);
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute(['token_id' => trim($_GET['token_id'])]);
            $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'logs' => $logs]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---------------------------------------------------------------------
// 2. Fetch Dropdown Options
// ---------------------------------------------------------------------
try {
    $branches = $pdo->query("SELECT branch_id, branch_name FROM branches WHERE status='active' ORDER BY branch_name ASC")->fetchAll();
    $floors   = $pdo->query("SELECT floor_id, floor_name FROM floors ORDER BY floor_id ASC")->fetchAll();
} catch (Exception $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// ---------------------------------------------------------------------
// 3. Search & Query Filtering (Updated for All Records)
// ---------------------------------------------------------------------
$activeTab = $_GET['tab'] ?? 'all';
$allowedTabs = ['all', 'complete', 'received'];
if (!in_array($activeTab, $allowedTabs)) {
    $activeTab = 'all';
}

$where = []; 
$params = [];

// Only filter by status if a specific status tab is requested
if ($activeTab !== 'all') {
    $where[] = "t.status = :tab_status";
    $params['tab_status'] = $activeTab;
}

if (!empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $where[] = "(t.token_id LIKE :search OR t.invoice_no LIKE :search OR t.order_no LIKE :search OR s.supplier_name LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($_GET['token_date'])) {
    $where[] = "t.token_date = :token_date";
    $params['token_date'] = $_GET['token_date'];
}

if (!empty($_GET['branch_id'])) {
    $where[] = "t.branch_id = :branch_id";
    $params['branch_id'] = (int)$_GET['branch_id'];
}

if (!empty($_GET['floor_id'])) {
    $where[] = "t.floor_id = :floor_id";
    $params['floor_id'] = (int)$_GET['floor_id'];
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $stmt = $pdo->prepare("
        SELECT 
            t.*, 
            b.branch_name, 
            f.floor_name, 
            s.supplier_name 
        FROM tokens t
        LEFT JOIN branches b ON t.branch_id = b.branch_id
        LEFT JOIN floors f ON t.floor_id = f.floor_id
        LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id
        $whereSql
        ORDER BY t.updated_at DESC, t.created_at DESC
    ");
    $stmt->execute($params);
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tokens = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tokens & Audit Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col font-sans" 
      x-data="{ 
          selectedToken: null, 
          showLogsModal: false, 
          activeLogsTokenId: '', 
          tokenLogs: [], 
          loadingLogs: false,
          
          fetchLogs(tokenId) {
              this.activeLogsTokenId = tokenId;
              this.showLogsModal = true;
              this.loadingLogs = true;
              this.tokenLogs = [];
              fetch(`manage_tokens.php?action=fetch_logs&token_id=${encodeURIComponent(tokenId)}`)
                  .then(res => res.json())
                  .then(data => {
                      if (data.success) {
                          this.tokenLogs = data.logs;
                      }
                      this.loadingLogs = false;
                  })
                  .catch(err => {
                      this.loadingLogs = false;
                  });
          }
      }">

    <!-- Header -->
    <header class="bg-slate-800 border-b border-slate-700 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="bg-sky-600 text-white font-black px-3 py-1.5 rounded-xl text-xl shadow-lg">ASB</div>
                <div>
                    <h1 class="font-bold text-lg leading-tight text-white">Token Processing & Logs Management</h1>
                    <p class="text-xs text-slate-400">View token records and audit log histories</p>
                </div>
            </div>
            <div class="text-xs font-bold bg-slate-700/80 text-sky-400 px-3.5 py-1.5 rounded-full border border-slate-600">
                <i class="fa-regular fa-calendar-check mr-1.5"></i> <?php echo date("F j, Y"); ?>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6 w-full flex-grow space-y-6">

        <!-- Dynamic Feedback Alerts -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'received_success'): ?>
                <div class="bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 p-4 rounded-2xl flex items-center gap-3 text-sm shadow-lg">
                    <i class="fa-solid fa-circle-check text-emerald-400 text-xl"></i>
                    <div>
                        <strong>Success!</strong> Token <strong>#<?php echo htmlspecialchars($_GET['token'] ?? ''); ?></strong> status updated to <strong>Received</strong>.
                    </div>
                </div>
            <?php elseif ($_GET['msg'] === 'already_received'): ?>
                <div class="bg-amber-950/80 border border-amber-500/50 text-amber-300 p-4 rounded-2xl flex items-center gap-3 text-sm shadow-lg">
                    <i class="fa-solid fa-triangle-exclamation text-amber-400 text-xl"></i>
                    <div>
                        <strong>Notice:</strong> Token <strong>#<?php echo htmlspecialchars($_GET['token'] ?? ''); ?></strong> is already marked as received.
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- STEP 1: Search & Dynamic Filter -->
        <section class="bg-slate-800 rounded-2xl border border-slate-700 shadow-md overflow-hidden">
            <div class="bg-slate-800/80 px-6 py-4 border-b border-slate-700 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full bg-sky-600 text-white font-extrabold text-xs flex items-center justify-center">1</span>
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider">Search & Filter Tokens</h2>
                </div>
                <!-- Navigation Tabs -->
                <div class="flex gap-2">
                    <a href="manage_tokens.php?tab=all" class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 <?php echo $activeTab === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'; ?>">
                        <i class="fa-solid fa-list"></i> All Files
                    </a>
                    <a href="manage_tokens.php?tab=complete" class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 <?php echo $activeTab === 'complete' ? 'bg-sky-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'; ?>">
                        <i class="fa-solid fa-hourglass-half"></i> Complete Status
                    </a>
                    <a href="manage_tokens.php?tab=received" class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 <?php echo $activeTab === 'received' ? 'bg-emerald-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'; ?>">
                        <i class="fa-solid fa-circle-check"></i> Already Received
                    </a>
                </div>
            </div>

            <div class="p-6">
                <form method="GET" action="manage_tokens.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">

                    <div class="lg:col-span-2">
                        <label class="block text-xs font-semibold text-slate-300 mb-1"><i class="fa-solid fa-magnifying-glass mr-1"></i> Keyword Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                               placeholder="Token ID, Invoice #, Order #, Vendor..." 
                               class="w-full px-3.5 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 focus:outline-none focus:border-sky-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1"><i class="fa-regular fa-calendar-days mr-1"></i> Token Date</label>
                        <input type="date" name="token_date" value="<?php echo htmlspecialchars($_GET['token_date'] ?? ''); ?>" 
                               class="w-full px-3.5 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 focus:outline-none focus:border-sky-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1"><i class="fa-solid fa-building mr-1"></i> Branch</label>
                        <select name="branch_id" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 focus:outline-none focus:border-sky-500">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $br): ?>
                                <option value="<?php echo $br['branch_id']; ?>" <?php echo (($_GET['branch_id'] ?? '') == $br['branch_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($br['branch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1"><i class="fa-solid fa-layer-group mr-1"></i> Floor</label>
                        <select name="floor_id" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 focus:outline-none focus:border-sky-500">
                            <option value="">All Floors</option>
                            <?php foreach ($floors as $fl): ?>
                                <option value="<?php echo $fl['floor_id']; ?>" <?php echo (($_GET['floor_id'] ?? '') == $fl['floor_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fl['floor_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="lg:col-span-5 flex justify-end gap-3 pt-2">
                        <a href="manage_tokens.php?tab=<?php echo $activeTab; ?>" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-xl text-xs font-bold transition flex items-center gap-2">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                        <button type="submit" class="px-6 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-2">
                            <i class="fa-solid fa-sliders"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- STEP 2: Token Cards -->
        <section class="bg-slate-800 rounded-2xl border border-slate-700 shadow-md overflow-hidden">
            <div class="bg-slate-800/80 px-6 py-4 border-b border-slate-700 flex items-center gap-3">
                <span class="w-7 h-7 rounded-full bg-sky-600 text-white font-extrabold text-xs flex items-center justify-center">2</span>
                <h2 class="text-sm font-bold text-white uppercase tracking-wider">Filtered Records (<?php echo count($tokens); ?>)</h2>
            </div>

            <div class="p-6">
                <?php if (empty($tokens)): ?>
                    <div class="text-center py-10 text-slate-500">
                        <i class="fa-solid fa-inbox text-4xl mb-3 block text-slate-600"></i>
                        <p class="font-semibold text-sm">No matching tokens found for filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($tokens as $row): ?>
                            <div class="bg-slate-900 border border-slate-700 rounded-xl p-4 flex flex-col justify-between space-y-3 transition hover:border-sky-500"
                                 :class="selectedToken && selectedToken.token_id == '<?php echo $row['token_id']; ?>' ? 'ring-2 ring-sky-500 border-sky-500 bg-slate-900/90' : ''">
                                
                                <div>
                                    <div class="flex justify-between items-start border-b border-slate-800 pb-2.5 mb-3">
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block"><i class="fa-solid fa-hashtag"></i> Token ID</span>
                                            <span class="text-base font-black text-sky-400">#<?php echo htmlspecialchars($row['token_id']); ?></span>
                                        </div>
                                        <?php if ($row['status'] === 'received'): ?>
                                            <span class="px-2.5 py-1 bg-emerald-950 border border-emerald-600 text-emerald-400 text-[10px] font-extrabold rounded-full flex items-center gap-1">
                                                <i class="fa-solid fa-circle-check"></i> RECEIVED
                                            </span>
                                        <?php elseif ($row['status'] === 'complete'): ?>
                                            <span class="px-2.5 py-1 bg-sky-950 border border-sky-600 text-sky-300 text-[10px] font-extrabold rounded-full flex items-center gap-1">
                                                <i class="fa-solid fa-hourglass-half"></i> COMPLETE
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 bg-slate-800 border border-slate-600 text-slate-300 text-[10px] font-extrabold rounded-full flex items-center gap-1">
                                                <i class="fa-solid fa-circle-info"></i> <?php echo strtoupper(htmlspecialchars($row['status'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="space-y-2 text-xs">
                                        <div class="flex justify-between">
                                            <span class="text-slate-400"><i class="fa-solid fa-file-invoice mr-1"></i> Invoice No:</span>
                                            <span class="font-bold text-slate-100"><?php echo htmlspecialchars($row['invoice_no']); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-400"><i class="fa-solid fa-cart-shopping mr-1"></i> Order No:</span>
                                            <span class="font-semibold text-slate-300"><?php echo htmlspecialchars($row['order_no']); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-400"><i class="fa-solid fa-handshake mr-1"></i> Vendor/Supplier:</span>
                                            <span class="text-slate-200 font-medium"><?php echo htmlspecialchars($row['supplier_name'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-400"><i class="fa-solid fa-location-dot mr-1"></i> Location:</span>
                                            <span class="text-slate-300"><?php echo htmlspecialchars($row['branch_name'] ?? '-'); ?> / <?php echo htmlspecialchars($row['floor_name'] ?? '-'); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-400"><i class="fa-solid fa-calendar-day mr-1"></i> Token Date:</span>
                                            <span class="text-slate-300"><?php echo htmlspecialchars($row['token_date']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-slate-800 space-y-2">
                                    <button @click="fetchLogs('<?php echo $row['token_id']; ?>')" 
                                            class="w-full py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5">
                                        <i class="fa-solid fa-clock-rotate-left text-sky-400"></i> View Logs History
                                    </button>

                                    <button @click="selectedToken = <?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>; $nextTick(() => { document.getElementById('step-3-action').scrollIntoView({ behavior: 'smooth' }); })" 
                                            class="w-full py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold transition shadow-md flex items-center justify-center gap-1.5">
                                        <i class="fa-solid fa-eye"></i> View Details & Actions
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- STEP 3: Record Details & Action View -->
        <section id="step-3-action" class="bg-slate-800 rounded-2xl border border-slate-700 shadow-md overflow-hidden">
            <div class="bg-slate-800/80 px-6 py-4 border-b border-slate-700 flex items-center gap-3">
                <span class="w-7 h-7 rounded-full bg-indigo-600 text-white font-extrabold text-xs flex items-center justify-center">3</span>
                <h2 class="text-sm font-bold text-white uppercase tracking-wider">Selected Record Full Details</h2>
            </div>

            <div class="p-6">
                <template x-if="!selectedToken">
                    <div class="text-center py-8 text-slate-400 bg-slate-900/50 rounded-xl border border-dashed border-slate-700">
                        <i class="fa-solid fa-hand-pointer text-2xl mb-2 text-sky-400"></i>
                        <p class="text-xs font-semibold">Select any record card from Step 2 to display its full metadata.</p>
                    </div>
                </template>

                <template x-if="selectedToken">
                    <div class="space-y-6">
                        <!-- Full Details Panel -->
                        <div class="bg-slate-900 border border-slate-700 rounded-xl p-5 grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                            <div class="space-y-3">
                                <div>
                                    <span class="text-slate-400 block"><i class="fa-solid fa-hashtag mr-1"></i> Token ID:</span>
                                    <span class="font-black text-sky-400 text-base" x-text="'#' + selectedToken.token_id"></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block"><i class="fa-solid fa-info-circle mr-1"></i> Current Status:</span>
                                    <span class="font-extrabold text-amber-400 uppercase text-sm" x-text="selectedToken.status"></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block"><i class="fa-regular fa-calendar-days mr-1"></i> Token Date:</span>
                                    <span class="font-bold text-slate-100" x-text="selectedToken.token_date"></span>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <span class="text-slate-400 block"><i class="fa-solid fa-file-invoice mr-1"></i> Invoice No:</span>
                                    <span class="font-bold text-slate-100" x-text="selectedToken.invoice_no || 'N/A'"></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block"><i class="fa-solid fa-cart-shopping mr-1"></i> Order No:</span>
                                    <span class="font-bold text-slate-100" x-text="selectedToken.order_no || 'N/A'"></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block"><i class="fa-solid fa-handshake mr-1"></i> Vendor / Supplier:</span>
                                    <span class="font-medium text-slate-200" x-text="selectedToken.supplier_name || 'N/A'"></span>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <span class="text-slate-400 block"><i class="fa-solid fa-building mr-1"></i> Branch Location:</span>
                                    <span class="font-medium text-slate-200" x-text="selectedToken.branch_name || 'N/A'"></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block"><i class="fa-solid fa-layer-group mr-1"></i> Floor Level:</span>
                                    <span class="font-medium text-slate-200" x-text="selectedToken.floor_name || 'N/A'"></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block"><i class="fa-solid fa-clock mr-1"></i> Last Updated:</span>
                                    <span class="font-mono text-slate-300" x-text="selectedToken.updated_at || selectedToken.created_at"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Processing Form (Shown if not yet marked as received) -->
                        <template x-if="selectedToken.status !== 'received'">
                            <form action="update_status.php" method="POST" class="max-w-xl space-y-4 pt-2 border-t border-slate-700">
                                <h3 class="text-sm font-bold text-slate-200"><i class="fa-solid fa-pen-to-square text-emerald-400 mr-1.5"></i> Mark Token as Received</h3>
                                <input type="hidden" name="token_id" :value="selectedToken.token_id">

                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1">
                                        Received By (Staff Name / ID) <span class="text-rose-400">*</span>
                                    </label>
                                    <input type="text" name="processed_by" required placeholder="Enter staff name or ID" 
                                           class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                                </div>

                                <div class="flex items-center gap-3">
                                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition shadow-md flex items-center gap-2">
                                        <i class="fa-solid fa-circle-check"></i> Save & Mark as Received
                                    </button>
                                    <button type="button" @click="selectedToken = null" class="px-4 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-xl text-xs font-bold transition">
                                        Close Details
                                    </button>
                                </div>
                            </form>
                        </template>

                        <!-- Read-only Alert (If already received) -->
                        <template x-if="selectedToken.status === 'received'">
                            <div class="p-4 bg-emerald-950/40 border border-emerald-600/40 rounded-xl text-emerald-300 text-xs flex justify-between items-center">
                                <div>
                                    <i class="fa-solid fa-circle-check text-emerald-400 mr-1.5"></i>
                                    <strong>Status Notice:</strong> This token has already been processed and marked as Received.
                                </div>
                                <button type="button" @click="selectedToken = null" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg text-xs font-bold transition">
                                    Close Details
                                </button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </section>

        <!-- AUDIT LOGS MODAL -->
        <div x-show="showLogsModal" 
             x-transition.opacity
             class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4" 
             style="display: none;">
            
            <div @click.away="showLogsModal = false" class="bg-slate-800 border border-slate-700 rounded-2xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl overflow-hidden">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center bg-slate-800/90">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-sky-400 text-lg"></i>
                        <h3 class="font-bold text-white text-sm">Token Logs History — <span class="text-sky-400" x-text="'#' + activeLogsTokenId"></span></h3>
                    </div>
                    <button @click="showLogsModal = false" class="text-slate-400 hover:text-white transition">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto space-y-4 flex-grow text-xs">
                    <template x-if="loadingLogs">
                        <div class="text-center py-8 text-slate-400">
                            <i class="fa-solid fa-spinner fa-spin text-2xl text-sky-400 mb-2"></i>
                            <p>Loading log details...</p>
                        </div>
                    </template>

                    <template x-if="!loadingLogs && tokenLogs.length === 0">
                        <div class="text-center py-8 text-slate-500">
                            <i class="fa-solid fa-folder-open text-3xl mb-2"></i>
                            <p>No audit log history available for this token.</p>
                        </div>
                    </template>

                    <template x-if="!loadingLogs && tokenLogs.length > 0">
                        <div class="space-y-3">
                            <template x-for="log in tokenLogs" :key="log.log_id">
                                <div class="bg-slate-900 border border-slate-700 rounded-xl p-4 space-y-2">
                                    <div class="flex justify-between items-center border-b border-slate-800 pb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded bg-slate-800 border border-slate-700 text-sky-300 font-mono text-[11px]" x-text="log.action_type"></span>
                                            <span class="text-slate-400 text-[11px]" x-text="'by ' + (log.user_name || 'System / ID: ' + (log.user_id || 'N/A'))"></span>
                                        </div>
                                        <span class="text-slate-400 font-mono text-[11px]" x-text="log.created_at"></span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 text-slate-300 pt-1">
                                        <div>
                                            <span class="text-slate-500 block text-[10px]">Old Value:</span>
                                            <span class="font-semibold text-rose-400" x-text="log.old_value || 'N/A'"></span>
                                        </div>
                                        <div>
                                            <span class="text-slate-500 block text-[10px]">New Value:</span>
                                            <span class="font-semibold text-emerald-400" x-text="log.new_value || 'N/A'"></span>
                                        </div>
                                    </div>

                                    <template x-if="log.note">
                                        <div class="mt-2 pt-2 border-t border-slate-800/80 text-slate-300 italic bg-slate-950/40 p-2 rounded-lg">
                                            <i class="fa-solid fa-note-sticky text-amber-400 mr-1"></i> <span x-text="log.note"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </main>
</body>
</html>