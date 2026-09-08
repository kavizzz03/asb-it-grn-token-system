<?php
require_once 'db.php';

date_default_timezone_set('Asia/Colombo');

// Validate HTTP POST Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: create_token.php");
    exit;
}

// Extract & Sanitize Form Input
$company_id  = filter_input(INPUT_POST, 'company_id', FILTER_VALIDATE_INT);
$branch_id   = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
$floor_id    = filter_input(INPUT_POST, 'floor_id', FILTER_VALIDATE_INT);
$supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
$invoice_no  = trim(filter_input(INPUT_POST, 'invoice_no', FILTER_SANITIZE_SPECIAL_CHARS));
$created_by  = trim(filter_input(INPUT_POST, 'created_by', FILTER_SANITIZE_SPECIAL_CHARS));

if (!$company_id || !$branch_id || !$floor_id || !$supplier_id || empty($invoice_no) || empty($created_by)) {
    die("<div style='color:red; font-family:sans-serif; padding:10px; font-weight:bold;'>Error: Missing required parameters.</div>");
}

$today_date = date('Y-m-d');
$month_year = date('Y-m');
$created_at = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    // 1. Fetch Master Labels
    $stmtComp = $pdo->prepare("SELECT company_name FROM companies WHERE company_id = ?");
    $stmtComp->execute([$company_id]);
    $company_name = $stmtComp->fetchColumn();

    $stmtBranch = $pdo->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
    $stmtBranch->execute([$branch_id]);
    $branch_name = $stmtBranch->fetchColumn();

    $stmtFloor = $pdo->prepare("SELECT floor_name FROM floors WHERE floor_id = ?");
    $stmtFloor->execute([$floor_id]);
    $floor_name = $stmtFloor->fetchColumn();

    $stmtSup = $pdo->prepare("SELECT supplier_name, system_id FROM suppliers WHERE supplier_id = ?");
    $stmtSup->execute([$supplier_id]);
    $supplier = $stmtSup->fetch();
    $supplier_name = $supplier['supplier_name'] ?? 'N/A';

    // 2. Lock & Compute Daily Sequential Token ID (Format: YYYYMMDD0000001)
    $stmtDaily = $pdo->prepare("SELECT COUNT(*) FROM tokens WHERE token_date = ? FOR UPDATE");
    $stmtDaily->execute([$today_date]);
    $daily_count = $stmtDaily->fetchColumn() + 1;
    $token_id = date('Ymd') . str_pad($daily_count, 7, '0', STR_PAD_LEFT);

    // 3. Lock & Compute Per-Company Monthly Order Number (Format: YY.MM.01)
    $stmtOrder = $pdo->prepare("SELECT COUNT(*) FROM tokens WHERE company_id = ? AND month_year = ? FOR UPDATE");
    $stmtOrder->execute([$company_id, $month_year]);
    $order_count = $stmtOrder->fetchColumn() + 1;
    $order_no = date('y.m.') . str_pad($order_count, 2, '0', STR_PAD_LEFT);

    // 4. Compute Dynamic Queue Priority Number for Pending Documents
    $stmtPriority = $pdo->prepare("SELECT COUNT(*) FROM tokens WHERE token_date = ? AND status = 'pending' FOR UPDATE");
    $stmtPriority->execute([$today_date]);
    $priority_no = $stmtPriority->fetchColumn() + 1;

    // 5. Insert New Token Record
    $sqlInsert = "INSERT INTO tokens (
        token_id, company_id, branch_id, floor_id, supplier_id, 
        invoice_no, order_no, priority_no, status, created_by, 
        token_date, month_year, created_at
    ) VALUES (
        :token_id, :company_id, :branch_id, :floor_id, :supplier_id, 
        :invoice_no, :order_no, :priority_no, 'pending', :created_by, 
        :token_date, :month_year, :created_at
    )";

    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->execute([
        ':token_id'    => $token_id,
        ':company_id'  => $company_id,
        ':branch_id'   => $branch_id,
        ':floor_id'    => $floor_id,
        ':supplier_id' => $supplier_id,
        ':invoice_no'  => $invoice_no,
        ':order_no'    => $order_no,
        ':priority_no' => $priority_no,
        ':created_by'  => $created_by,
        ':token_date'  => $today_date,
        ':month_year'  => $month_year,
        ':created_at'  => $created_at
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("<div style='color:red; font-family:sans-serif; padding:10px; font-weight:bold;'>Transaction Failed: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token Ticket - <?php echo htmlspecialchars($token_id); ?></title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- JsBarcode Library for Code128 -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

    <style>
        /* Thermal Printer Zero-Margin Page Rules */
        @page {
            margin: 0 !important;
            padding: 0 !important;
            size: auto;
        }

        @media print {
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: 72mm !important;
                background: #ffffff !important;
                color: #000000 !important;
                display: block !important;
            }

            .no-print {
                display: none !important;
            }

            .print-area {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 72mm !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }

            * {
                color: #000000 !important;
                border-color: #000000 !important;
                background: transparent !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col items-center justify-center p-2 antialiased">

    <!-- Screen Controls -->
    <div class="no-print max-w-[72mm] w-full mb-3 flex items-center justify-between gap-2">
        <a href="create_token.php" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-800 text-slate-200 text-xs font-semibold rounded-xl shadow border border-slate-700 hover:bg-slate-700 transition">
            <i class="fa-solid fa-arrow-left"></i> Back to Kiosk
        </a>
        <button onclick="triggerPrint()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl shadow hover:bg-emerald-500 transition">
            <i class="fa-solid fa-print"></i> Print Token
        </button>
    </div>

    <!-- Printable Area -->
    <div class="print-area w-[72mm] bg-white p-1 text-black font-sans leading-tight rounded-xl shadow-2xl">
        
        <!-- Header -->
        <div class="text-center pb-1 border-b border-dashed border-black">
            <div class="text-[9px] font-black uppercase tracking-wider">ASB GROUP OF COMPANIES</div>
            <div class="text-[8px] font-bold uppercase">IT Department Token System</div>
            <h1 class="text-xs font-black uppercase mt-0.5"><?php echo htmlspecialchars($company_name); ?></h1>
        </div>

        <!-- Queue Priority Number -->
        <div class="py-1 text-center border-b border-dashed border-black">
            <span class="text-[8px] font-bold uppercase tracking-wider block">Queue Priority</span>
            <div class="text-3xl font-black leading-none my-0.5">
                #<?php echo str_pad($priority_no, 2, '0', STR_PAD_LEFT); ?>
            </div>
            <div class="text-[8px] font-extrabold uppercase">STATUS: PENDING</div>
        </div>

        <!-- Data Details Block -->
        <div class="py-1 text-[10px] border-b border-dashed border-black space-y-0.5">
            <div class="flex justify-between items-center">
                <span class="font-bold uppercase text-[8px]">Token ID:</span>
                <span class="font-mono font-bold text-[10px]"><?php echo htmlspecialchars($token_id); ?></span>
            </div>

            <div class="flex justify-between items-center">
                <span class="font-bold uppercase text-[8px]">Doc No:</span>
                <span class="font-mono font-bold text-[10px]"><?php echo htmlspecialchars($order_no); ?></span>
            </div>

            <div class="flex justify-between items-center">
                <span class="font-bold uppercase text-[8px]">Invoice No:</span>
                <span class="font-bold text-[10px]"><?php echo htmlspecialchars($invoice_no); ?></span>
            </div>

            <div class="flex justify-between items-center">
                <span class="font-bold uppercase text-[8px]">Vendor:</span>
                <span class="font-semibold text-[9px] text-right truncate max-w-[130px]"><?php echo htmlspecialchars($supplier_name); ?></span>
            </div>

            <div class="flex justify-between items-center">
                <span class="font-bold uppercase text-[8px]">Location:</span>
                <span class="font-medium text-[9px] text-right truncate max-w-[130px]">
                    <?php echo htmlspecialchars($branch_name); ?> - <?php echo htmlspecialchars($floor_name); ?>
                </span>
            </div>

            <div class="flex justify-between items-center">
                <span class="font-bold uppercase text-[8px]">Created By:</span>
                <span class="font-medium text-[9px]"><?php echo htmlspecialchars($created_by); ?></span>
            </div>

            <div class="flex justify-between items-center">
                <span class="font-bold uppercase text-[8px]">Date/Time:</span>
                <span class="font-mono text-[8px]"><?php echo $created_at; ?></span>
            </div>
        </div>

        <!-- Code 128 Barcode -->
        <div class="py-1 text-center border-b border-dashed border-black">
            <svg id="barcode" class="mx-auto max-w-full"></svg>
        </div>

        <!-- Footer -->
        <div class="pt-1 text-center text-[8px] font-black uppercase tracking-tight">
            KEEP THIS SLIP ATTACHED TO THE DOCUMENTS
        </div>

    </div>

    <!-- Script for Barcode Generation and Auto Redirect -->
    <script>
        let isRedirecting = false;

        function redirectToForm() {
            if (!isRedirecting) {
                isRedirecting = true;
                window.location.href = 'create_token.php';
            }
        }

        function triggerPrint() {
            window.print();
        }

        document.addEventListener("DOMContentLoaded", function() {
            // Render barcode
            JsBarcode("#barcode", "<?php echo $token_id; ?>", {
                format: "CODE128",
                width: 1.4,
                height: 28,
                displayValue: true,
                fontSize: 9,
                fontOptions: "bold",
                font: "monospace",
                margin: 0
            });

            // Trigger print dialog automatically on page load
            setTimeout(function() {
                window.print();
            }, 300);
        });

        // Redirect when print dialog is closed (printed or canceled)
        window.onafterprint = function() {
            redirectToForm();
        };

        // Fallback detection for browsers that don't trigger onafterprint reliably
        if (window.matchMedia) {
            const mediaQueryList = window.matchMedia('print');
            mediaQueryList.addEventListener('change', function(mql) {
                if (!mql.matches) {
                    redirectToForm();
                }
            });
        }
    </script>
</body>
</html>