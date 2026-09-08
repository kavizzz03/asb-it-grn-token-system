<?php
session_start();
require_once 'db.php';

date_default_timezone_set('Asia/Colombo');
header('Content-Type: application/json');

try {
    $company_id = isset($_GET['company_id']) && $_GET['company_id'] !== '' ? intval($_GET['company_id']) : null;
    $branch_id = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? intval($_GET['branch_id']) : null;
    $floor_id = isset($_GET['floor_id']) && $_GET['floor_id'] !== '' ? intval($_GET['floor_id']) : null;
    $supplier_id = isset($_GET['supplier_id']) && $_GET['supplier_id'] !== '' ? intval($_GET['supplier_id']) : null;
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';

    $sql = "
        SELECT 
            t.token_id,
            t.invoice_no,
            t.order_no,
            t.priority_no,
            t.status,
            t.created_by,
            t.processed_by,
            t.token_date,
            t.created_at,
            c.company_name,
            b.branch_name,
            f.floor_name,
            s.supplier_name,
            s.contact_number
        FROM tokens t
        LEFT JOIN companies c ON t.company_id = c.company_id
        LEFT JOIN branches b ON t.branch_id = b.branch_id
        LEFT JOIN floors f ON t.floor_id = f.floor_id
        LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id
        WHERE 1=1
    ";

    $params = [];

    if ($company_id) {
        $sql .= " AND t.company_id = :company_id";
        $params[':company_id'] = $company_id;
    }

    if ($branch_id) {
        $sql .= " AND t.branch_id = :branch_id";
        $params[':branch_id'] = $branch_id;
    }

    if ($floor_id) {
        $sql .= " AND t.floor_id = :floor_id";
        $params[':floor_id'] = $floor_id;
    }

    if ($supplier_id) {
        $sql .= " AND t.supplier_id = :supplier_id";
        $params[':supplier_id'] = $supplier_id;
    }

    if (!empty($search)) {
        $sql .= " AND (t.token_id LIKE :search OR t.invoice_no LIKE :search OR t.order_no LIKE :search OR s.supplier_name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    // Order by priority (NULLs last for completed/received)
    $sql .= " ORDER BY 
        CASE 
            WHEN t.status IN ('complete', 'received') THEN 999999 
            WHEN t.priority_no IS NULL THEN 999998 
            ELSE t.priority_no 
        END ASC,
        t.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>