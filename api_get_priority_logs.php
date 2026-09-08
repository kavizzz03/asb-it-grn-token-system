<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $sql = "SELECT log_id, invoice_no, old_priority, new_priority, updated_by, DATE_FORMAT(updated_at, '%H:%i:%s') as update_time 
            FROM priority_logs 
            ORDER BY log_id DESC 
            LIMIT 10";

    $stmt = $pdo->query($sql);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'results' => $logs
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}