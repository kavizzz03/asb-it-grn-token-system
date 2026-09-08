<?php
require_once 'db.php';
require_once 'auth.php';
checkAuth();

date_default_timezone_set('Asia/Colombo');

// Handle AJAX Search & Pagination for High-Volume Records
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');

    $search_query   = trim($_GET['q'] ?? '');
    $company_filter = filter_input(INPUT_GET, 'company_id', FILTER_VALIDATE_INT);
    $branch_filter  = filter_input(INPUT_GET, 'branch_id', FILTER_VALIDATE_INT);
    $floor_filter   = filter_input(INPUT_GET, 'floor_id', FILTER_VALIDATE_INT);
    
    // Pagination Controls
    $page   = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
    $limit  = 25; 
    $offset = ($page - 1) * $limit;

    $where  = [];
    $params = [];

    if (!empty($search_query)) {
        if (strlen($search_query) >= 3) {
            $where[] = "MATCH(t.token_id, t.invoice_no, t.order_no) AGAINST(:ft_query IN BOOLEAN MODE)";
            $params[':ft_query'] = $search_query . '*';
        } else {
            $where[] = "(t.token_id LIKE :q OR t.invoice_no LIKE :q OR t.order_no LIKE :q)";
            $params[':q'] = $search_query . '%';
        }
    }

    if ($company_filter) {
        $where[] = "t.company_id = :company_id";
        $params[':company_id'] = $company_filter;
    }

    if ($branch_filter) {
        $where[] = "t.branch_id = :branch_id";
        $params[':branch_id'] = $branch_filter;
    }

    if ($floor_filter) {
        $where[] = "t.floor_id = :floor_id";
        $params[':floor_id'] = $floor_filter;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    try {
        $sql = "SELECT 
                    t.token_id, t.order_no, t.invoice_no, t.priority_no, t.status, t.created_by, t.created_at,
                    c.company_name, b.branch_name, f.floor_name, s.supplier_name
                FROM (
                    SELECT token_id, company_id, branch_id, floor_id, supplier_id
                    FROM tokens t
                    $whereClause
                    ORDER BY t.token_id DESC
                    LIMIT :limit OFFSET :offset
                ) AS fast_t
                INNER JOIN tokens t ON fast_t.token_id = t.token_id
                LEFT JOIN companies c ON t.company_id = c.company_id
                LEFT JOIN branches b ON t.branch_id = b.branch_id
                LEFT JOIN floors f ON t.floor_id = f.floor_id
                LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'page'   => $page,
            'data'   => $results
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database Query Error']);
    }
    exit;
}

// Fetch Dropdown Master Data
$companies = $pdo->query("SELECT company_id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$branches  = $pdo->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$floors    = $pdo->query("SELECT floor_id, floor_name FROM floors ORDER BY floor_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle Single Token Reprint Retrieval
$selected_token = null;
if (isset($_GET['token_id'])) {
    $token_id_param = trim($_GET['token_id']);
    $stmtReprint = $pdo->prepare("SELECT 
                t.token_id, t.order_no, t.invoice_no, t.priority_no, t.status, t.created_by, t.created_at,
                c.company_name, b.branch_name, f.floor_name, s.supplier_name
            FROM tokens t
            LEFT JOIN companies c ON t.company_id = c.company_id
            LEFT JOIN branches b ON t.branch_id = b.branch_id
            LEFT JOIN floors f ON t.floor_id = f.floor_id
            LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id
            WHERE t.token_id = ?");
    $stmtReprint->execute([$token_id_param]);
    $selected_token = $stmtReprint->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token Search & Regenerate - ASB Group IT</title>
     <link rel="icon" type="image/png" href="logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

    <style>
        @page { margin: 0 !important; padding: 0 !important; size: auto; }
        @media print {
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            html, body {
                margin: 0 !important; padding: 0 !important;
                width: 72mm !important; background: #ffffff !important; color: #000000 !important;
            }
            .no-print { display: none !important; }
            .print-area {
                position: absolute !important; top: 0 !important; left: 0 !important;
                width: 72mm !important; margin: 0 !important; padding: 0 !important;
            }
            * { color: #000000 !important; border-color: #000000 !important; background: transparent !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-8 flex flex-col justify-between">

    <div class="no-print max-w-6xl mx-auto space-y-6 w-full">
        
        <!-- Standard Header -->
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="bg-sky-500/10 border border-sky-500/30 text-sky-400 text-[10px] font-bold px-2 py-0.5 rounded-md uppercase tracking-wider">ASB Group IT Department</span>
                </div>
                <h1 class="text-2xl font-bold text-white mt-1">Token Search & Regenerate</h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="create_token.php" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <i class="fa-solid fa-plus"></i> New Token
                </a>
                <a href="dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-xl text-xs font-bold transition">&larr; Back to Dashboard</a>
            </div>
        </div>

        <!-- Filter & Search Controls Form Container -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
            <h2 class="text-lg font-bold text-white mb-4">Search & Filter Parameters</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                
                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Search Keyword</label>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-500 text-xs"></i>
                        <input type="text" id="searchInput" placeholder="Invoice No, Token ID, Order No..." class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Company</label>
                    <select id="companyFilter" class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?php echo (int)$c['company_id']; ?>"><?php echo htmlspecialchars($c['company_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Branch</label>
                    <select id="branchFilter" class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo (int)$b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Floor</label>
                    <select id="floorFilter" class="w-full bg-slate-950 border border-slate-800 rounded-xl py-2 px-3 text-sm text-white focus:outline-none focus:border-sky-500">
                        <option value="">All Floors</option>
                        <?php foreach ($floors as $f): ?>
                            <option value="<?php echo (int)$f['floor_id']; ?>"><?php echo htmlspecialchars($f['floor_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
        </div>

        <!-- Data Table Module -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-800 text-xs font-bold uppercase text-sky-400 border-b border-slate-700">
                        <tr>
                            <th class="p-4 font-mono">Token ID</th>
                            <th class="p-4 font-mono">Doc No</th>
                            <th class="p-4">Invoice No</th>
                            <th class="p-4">Vendor</th>
                            <th class="p-4">Location</th>
                            <th class="p-4">Created At</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="resultsTable" class="divide-y divide-slate-800">
                        <tr><td colspan="7" class="p-8 text-center text-slate-500">Loading token records...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Toolbar -->
            <div class="p-4 bg-slate-900/80 border-t border-slate-800 flex items-center justify-between text-xs">
                <button id="prevBtn" onclick="changePage(-1)" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-xl text-xs font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">
                    &larr; Previous
                </button>
                <span id="pageIndicator" class="font-bold font-mono text-slate-400 text-xs">Page 1</span>
                <button id="nextBtn" onclick="changePage(1)" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-xl text-xs font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">
                    Next &rarr;
                </button>
            </div>
        </div>
    </div>

    <!-- Thermal Printable Ticket Area -->
    <?php if ($selected_token): ?>
    <div id="printContainer" class="flex flex-col items-center justify-center min-h-screen py-6 my-auto">
        <div class="no-print max-w-[72mm] w-full mb-3 flex items-center justify-between">
            <a href="regenerate_token.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1">
                <i class="fa-solid fa-xmark"></i> Close Slip
            </a>
            <button onclick="window.print()" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-lg">
                <i class="fa-solid fa-print"></i> Reprint Ticket
            </button>
        </div>

        <div class="print-area w-[72mm] bg-white p-2 text-black font-sans leading-tight border border-slate-300 shadow-2xl">
            <div class="text-center pb-1 border-b border-dashed border-black">
                <div class="text-[9px] font-black uppercase tracking-wider">ASB GROUP OF COMPANIES</div>
                <div class="text-[8px] font-bold uppercase">IT Department Token System</div>
                <div class="text-[7px] font-black uppercase text-red-600">[ REPRINT TICKET ]</div>
                <h1 class="text-xs font-black uppercase mt-0.5"><?php echo htmlspecialchars($selected_token['company_name']); ?></h1>
            </div>

            <div class="py-1 text-center border-b border-dashed border-black">
                <span class="text-[8px] font-bold uppercase tracking-wider block">Queue Priority</span>
                <div class="text-3xl font-black leading-none my-0.5">
                    #<?php echo str_pad((int)$selected_token['priority_no'], 2, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="text-[8px] font-extrabold uppercase">STATUS: <?php echo strtoupper(htmlspecialchars($selected_token['status'])); ?></div>
            </div>

            <div class="py-1 text-[10px] border-b border-dashed border-black space-y-0.5">
                <div class="flex justify-between items-center">
                    <span class="font-bold uppercase text-[8px]">Token ID:</span>
                    <span class="font-mono font-bold text-[10px]"><?php echo htmlspecialchars($selected_token['token_id']); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="font-bold uppercase text-[8px]">Doc No:</span>
                    <span class="font-mono font-bold text-[10px]"><?php echo htmlspecialchars($selected_token['order_no']); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="font-bold uppercase text-[8px]">Invoice No:</span>
                    <span class="font-bold text-[10px]"><?php echo htmlspecialchars($selected_token['invoice_no']); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="font-bold uppercase text-[8px]">Vendor:</span>
                    <span class="font-semibold text-[9px] text-right truncate max-w-[130px]"><?php echo htmlspecialchars($selected_token['supplier_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="font-bold uppercase text-[8px]">Location:</span>
                    <span class="font-medium text-[9px] text-right truncate max-w-[130px]">
                        <?php echo htmlspecialchars($selected_token['branch_name']); ?> - <?php echo htmlspecialchars($selected_token['floor_name']); ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="font-bold uppercase text-[8px]">Created By:</span>
                    <span class="font-medium text-[9px]"><?php echo htmlspecialchars($selected_token['created_by']); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="font-bold uppercase text-[8px]">Date/Time:</span>
                    <span class="font-mono text-[8px]"><?php echo htmlspecialchars($selected_token['created_at']); ?></span>
                </div>
            </div>

            <div class="py-1 text-center border-b border-dashed border-black">
                <svg id="barcodeReprint" class="mx-auto max-w-full"></svg>
            </div>

            <div class="pt-1 text-center text-[8px] font-black uppercase tracking-tight">
                KEEP THIS SLIP ATTACHED TO THE DOCUMENTS
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            JsBarcode("#barcodeReprint", "<?php echo htmlspecialchars($selected_token['token_id']); ?>", {
                format: "CODE128", width: 1.4, height: 28, displayValue: true, fontSize: 9, fontOptions: "bold", font: "monospace", margin: 0
            });
        });
    </script>
    <?php endif; ?>

    <!-- Standard Footer -->
    <footer class="no-print mt-8 text-center text-xs text-slate-500 border-t border-slate-900 pt-4">
        <p>ASB Group of Companies &bull; IT Department Token Management System</p>
        <p class="mt-1">Designed and Developed by <span class="font-bold text-slate-400">Vexel IT by Kavizz</span></p>
    </footer>

    <!-- AJAX Fetch Operations -->
    <script>
        let currentPage = 1;
        const searchInput   = document.getElementById('searchInput');
        const companyFilter = document.getElementById('companyFilter');
        const branchFilter  = document.getElementById('branchFilter');
        const floorFilter   = document.getElementById('floorFilter');
        const resultsTable  = document.getElementById('resultsTable');
        const pageIndicator = document.getElementById('pageIndicator');
        const prevBtn       = document.getElementById('prevBtn');
        const nextBtn       = document.getElementById('nextBtn');

        function escapeHTML(str) {
            if (!str) return 'N/A';
            return str.replace(/[&<>'"]/g, 
                tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
            );
        }

        function fetchTokens(page = 1) {
            currentPage = page;
            const query   = encodeURIComponent(searchInput.value.trim());
            const company = companyFilter.value;
            const branch  = branchFilter.value;
            const floor   = floorFilter.value;

            resultsTable.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-500"><i class="fa-solid fa-spinner fa-spin mr-2 text-sky-400"></i> Querying database...</td></tr>';

            fetch(`regenerate_token.php?action=search&q=${query}&company_id=${company}&branch_id=${branch}&floor_id=${floor}&page=${currentPage}`)
                .then(res => res.json())
                .then(res => {
                    if (res.status !== 'success' || !res.data || res.data.length === 0) {
                        resultsTable.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-500">No matching token records found.</td></tr>';
                        nextBtn.disabled = true;
                        prevBtn.disabled = currentPage <= 1;
                        pageIndicator.innerText = `Page ${currentPage}`;
                        return;
                    }

                    resultsTable.innerHTML = '';
                    res.data.forEach(row => {
                        resultsTable.innerHTML += `
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="p-4 font-mono font-bold text-sky-400">${escapeHTML(row.token_id)}</td>
                                <td class="p-4 font-mono">${escapeHTML(row.order_no)}</td>
                                <td class="p-4 font-bold text-white">${escapeHTML(row.invoice_no)}</td>
                                <td class="p-4">${escapeHTML(row.supplier_name)}</td>
                                <td class="p-4">${escapeHTML(row.branch_name)} - ${escapeHTML(row.floor_name)}</td>
                                <td class="p-4 font-mono text-xs text-slate-400">${escapeHTML(row.created_at)}</td>
                                <td class="p-4 text-right">
                                    <a href="regenerate_token.php?token_id=${encodeURIComponent(row.token_id)}" class="bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/30 px-3 py-1.5 rounded-xl text-xs font-bold transition inline-flex items-center gap-1">
                                        <i class="fa-solid fa-print"></i> Select
                                    </a>
                                </td>
                            </tr>
                        `;
                    });

                    pageIndicator.innerText = `Page ${currentPage}`;
                    prevBtn.disabled = currentPage <= 1;
                    nextBtn.disabled = res.data.length < 25;
                })
                .catch(err => {
                    console.error("Search Error:", err);
                    resultsTable.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-rose-400">Error retrieving data. Check server logs.</td></tr>';
                });
        }

        function changePage(direction) {
            const newPage = currentPage + direction;
            if (newPage >= 1) {
                fetchTokens(newPage);
            }
        }

        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => fetchTokens(1), 300);
        });

        companyFilter.addEventListener('change', () => fetchTokens(1));
        branchFilter.addEventListener('change', () => fetchTokens(1));
        floorFilter.addEventListener('change', () => fetchTokens(1));

        // Initial Fetch Execution
        fetchTokens(1);
    </script>
</body>
</html>