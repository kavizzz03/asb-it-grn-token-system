<?php
session_start();
require_once 'db.php';

date_default_timezone_set('Asia/Colombo');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$tokenId     = trim($input['token_id'] ?? '');
$newPriority = isset($input['new_priority']) && $input['new_priority'] !== '' && $input['new_priority'] !== null ? intval($input['new_priority']) : null;
$rawStatus   = trim($input['status'] ?? '');
$updatedBy   = trim($input['updated_by'] ?? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'QC Supervisor'));
$userId      = $_SESSION['user_id'] ?? 1;
$note        = trim($input['note'] ?? '');

if (empty($tokenId)) {
    echo json_encode(['success' => false, 'error' => 'Missing Token ID']);
    exit;
}

// Map and normalize status
$statusMap = [
    'pending'    => 'pending',
    'process'    => 'process',
    'processing' => 'process',
    'complete'   => 'complete',
    'completed'  => 'complete',
    'received'   => 'received'
];

$targetStatus = null;
if (!empty($rawStatus)) {
    $normalized = strtolower($rawStatus);
    if (isset($statusMap[$normalized])) {
        $targetStatus = $statusMap[$normalized];
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid status: ' . htmlspecialchars($rawStatus)]);
        exit;
    }
}

try {
    // Fetch current token state
    $stmt = $pdo->prepare("SELECT token_id, invoice_no, priority_no, status, processed_by FROM tokens WHERE token_id = :token_id LIMIT 1");
    $stmt->execute(['token_id' => $tokenId]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token) {
        echo json_encode(['success' => false, 'error' => "Token {$tokenId} not found in database"]);
        exit;
    }

    $oldPriority = ($token['priority_no'] !== null && intval($token['priority_no']) > 0) ? intval($token['priority_no']) : null;
    $oldStatus   = $token['status'];
    $invoiceNo   = $token['invoice_no'];
    
    // IMPORTANT: Keep existing status - never change it
    if ($targetStatus === null) {
        $targetStatus = $oldStatus;
    }

    // Check if record is completed or received - prevent changes
    if ($oldStatus === 'complete' || $oldStatus === 'received') {
        echo json_encode([
            'success' => false, 
            'error' => 'Cannot change priority for completed or received records. These records are locked.'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $finalPriority = $oldPriority;

    // Handle priority changes (only for pending/process)
    if ($newPriority !== null && $newPriority >= 1) {
        if ($oldPriority === null) {
            // No existing priority - insert at position
            $shiftStmt = $pdo->prepare("
                UPDATE tokens 
                SET priority_no = priority_no + 1 
                WHERE status != 'received' 
                  AND status != 'complete'
                  AND token_id != :token_id 
                  AND priority_no >= :new_priority
            ");
            $shiftStmt->execute([
                'new_priority' => $newPriority,
                'token_id'     => $tokenId
            ]);
        } elseif ($newPriority < $oldPriority) {
            // Moving up in priority
            $shiftStmt = $pdo->prepare("
                UPDATE tokens 
                SET priority_no = priority_no + 1 
                WHERE status != 'received' 
                  AND status != 'complete'
                  AND token_id != :token_id 
                  AND priority_no >= :new_priority 
                  AND priority_no < :old_priority
            ");
            $shiftStmt->execute([
                'new_priority' => $newPriority,
                'old_priority' => $oldPriority,
                'token_id'     => $tokenId
            ]);
        } elseif ($newPriority > $oldPriority) {
            // Moving down in priority
            $shiftStmt = $pdo->prepare("
                UPDATE tokens 
                SET priority_no = priority_no - 1 
                WHERE status != 'received' 
                  AND status != 'complete'
                  AND token_id != :token_id 
                  AND priority_no > :old_priority 
                  AND priority_no <= :new_priority
            ");
            $shiftStmt->execute([
                'old_priority' => $oldPriority,
                'new_priority' => $newPriority,
                'token_id'     => $tokenId
            ]);
        }
        $targetPriority = $newPriority;
    } else {
        $targetPriority = $oldPriority;
    }

    // Update the token with new priority, keeping status unchanged
    $updateStmt = $pdo->prepare("
        UPDATE tokens 
        SET priority_no = :priority_no, 
            updated_at = NOW() 
        WHERE token_id = :token_id
    ");
    $updateStmt->execute([
        'priority_no'  => $targetPriority,
        'token_id'     => $tokenId
    ]);
    $finalPriority = $targetPriority;

    // Re-normalize all active pending/process tokens
    $allActiveStmt = $pdo->query("
        SELECT token_id, priority_no 
        FROM tokens 
        WHERE status != 'received' 
          AND status != 'complete'
        ORDER BY 
            CASE WHEN priority_no IS NULL OR priority_no = 0 THEN 999999 ELSE priority_no END ASC,
            created_at ASC
    ");
    $allActive = $allActiveStmt->fetchAll(PDO::FETCH_ASSOC);

    $normStmt = $pdo->prepare("UPDATE tokens SET priority_no = :p WHERE token_id = :id");
    $counter = 1;
    foreach ($allActive as $item) {
        if ($item['token_id'] == $tokenId) {
            $finalPriority = $counter;
        }
        if (intval($item['priority_no']) !== $counter) {
            $normStmt->execute(['p' => $counter, 'id' => $item['token_id']]);
        }
        $counter++;
    }

    // Log priority update if rank changed
    if ($oldPriority !== $finalPriority && $finalPriority !== null) {
        $pLogStmt = $pdo->prepare("
            INSERT INTO priority_logs (token_id, invoice_no, old_priority, new_priority, updated_by, updated_at) 
            VALUES (:token_id, :invoice_no, :old_priority, :new_priority, :updated_by, NOW())
        ");
        $pLogStmt->execute([
            'token_id'     => $tokenId,
            'invoice_no'   => $invoiceNo,
            'old_priority' => $oldPriority ?? 0,
            'new_priority' => $finalPriority,
            'updated_by'   => $updatedBy
        ]);
    }

    // Log in token_logs
    $logNote = !empty($note) ? $note : "Priority Rank: #" . ($oldPriority ?? 'None') . " -> #" . $finalPriority;
    $logNote .= " | Status unchanged: '{$oldStatus}'";

    $tLogStmt = $pdo->prepare("
        INSERT INTO token_logs (token_id, user_id, action_type, old_value, new_value, note, created_at) 
        VALUES (:token_id, :user_id, :action_type, :old_val, :new_val, :note, NOW())
    ");
    $tLogStmt->execute([
        'token_id'    => $tokenId,
        'user_id'     => $userId,
        'action_type' => 'PRIORITY_UPDATE',
        'old_val'     => "Rank: " . ($oldPriority ?? 'None'),
        'new_val'     => "Rank: " . ($finalPriority ?? 'None'),
        'note'        => $logNote
    ]);

    $pdo->commit();

    echo json_encode([
        'success'      => true,
        'message'      => "Token {$tokenId} priority updated to Rank #{$finalPriority}",
        'token_id'     => $tokenId,
        'new_priority' => $finalPriority,
        'status'       => $oldStatus // Return current status (unchanged)
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error'   => 'Database update error: ' . $e->getMessage()
    ]);
}
?>