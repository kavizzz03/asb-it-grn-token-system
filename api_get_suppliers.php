<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Disable standard PHP error display to prevent malformed JSON outputs
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Read & sanitize query parameters
$query  = isset($_GET['q']) ? trim($_GET['q']) : '';
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 30; // 30 items default per chunk
$offset = ($page - 1) * $limit;

try {
    // Base condition for active suppliers
    $where = ["(status = 'active' OR status IS NULL)"];
    $params = [];

    // Apply search filter for vendor name or code / system_id
    if ($query !== '') {
        $where[] = "(supplier_name LIKE :query_name OR system_id LIKE :query_code OR supplier_id = :query_id)";
        $params[':query_name'] = '%' . $query . '%';
        $params[':query_code'] = $query . '%';
        $params[':query_id']   = ctype_digit($query) ? (int)$query : 0;
    }

    $whereClause = implode(' AND ', $where);

    // 1. Fetch total count for pagination calculation
    $countSql = "SELECT COUNT(*) FROM suppliers WHERE {$whereClause}";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalRecords = (int)$stmtCount->fetchColumn();

    // 2. Fetch paginated slice sorted by vendor name
    $dataSql = "SELECT supplier_id, supplier_name, system_id 
                FROM suppliers 
                WHERE {$whereClause} 
                ORDER BY supplier_name ASC 
                LIMIT :limit OFFSET :offset";

    $stmtData = $pdo->prepare($dataSql);
    
    // Bind string/search parameters
    foreach ($params as $key => $val) {
        $stmtData->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    
    // Bind integer limits for MySQL PDO strict mode
    $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();

    $rawSuppliers = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // Format & sanitize output array
    $suppliers = array_map(function($row) {
        return [
            'supplier_id'   => (string)$row['supplier_id'],
            'supplier_name' => htmlspecialchars($row['supplier_name'], ENT_QUOTES, 'UTF-8'),
            'system_id'     => !empty($row['system_id']) ? htmlspecialchars($row['system_id'], ENT_QUOTES, 'UTF-8') : null
        ];
    }, $rawSuppliers);

    $hasMore = ($offset + count($suppliers)) < $totalRecords;

    // Return structured JSON response matching kiosk UI
    echo json_encode([
        'success'   => true,
        'total'     => $totalRecords,
        'page'      => $page,
        'limit'     => $limit,
        'has_more'  => $hasMore,
        'next_page' => $hasMore ? ($page + 1) : null,
        'results'   => $suppliers
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Supplier query failed. Please check server logs.'
    ]);
}