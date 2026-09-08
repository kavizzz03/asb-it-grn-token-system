<?php
session_start();
require_once 'db.php';

date_default_timezone_set('Asia/Colombo');

// Target frontend page to redirect back to
$redirectPage = 'received.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenId     = trim($_POST['token_id'] ?? '');
    $processedBy = trim($_POST['processed_by'] ?? '');
    // Pull logged-in User ID from session if available, otherwise default to 1
    $userId      = $_SESSION['user_id'] ?? 1; 

    if (!empty($tokenId) && !empty($processedBy)) {
        try {
            // 1. Fetch current token record & verify existence
            $checkStmt = $pdo->prepare("SELECT status, invoice_no FROM tokens WHERE token_id = :token_id LIMIT 1");
            $checkStmt->execute(['token_id' => $tokenId]);
            $tokenRecord = $checkStmt->fetch();

            if (!$tokenRecord) {
                header("Location: {$redirectPage}?tab=complete&msg=not_found&token=" . urlencode($tokenId));
                exit;
            }

            $currentStatus = $tokenRecord['status'];

            // 2. Validation: ONLY allow receiving if status is currently 'complete'
            if ($currentStatus !== 'complete') {
                if ($currentStatus === 'received') {
                    header("Location: {$redirectPage}?tab=received&msg=already_received&token=" . urlencode($tokenId));
                } else {
                    // Status is 'pending' or 'process'
                    header("Location: {$redirectPage}?tab=complete&msg=not_complete&token=" . urlencode($tokenId) . "&status=" . urlencode($currentStatus));
                }
                exit;
            }

            // 3. Process status transition ('complete' -> 'received') with Transaction
            $pdo->beginTransaction();

            // Update `tokens` table
            $updateStmt = $pdo->prepare("
                UPDATE tokens 
                SET status = 'received', 
                    processed_by = :processed_by, 
                    updated_at = NOW() 
                WHERE token_id = :token_id AND status = 'complete'
            ");
            $updateStmt->execute([
                'processed_by' => $processedBy,
                'token_id'     => $tokenId
            ]);

            // 4. Add audit trace to `token_logs` table
            if ($updateStmt->rowCount() > 0) {
                $logStmt = $pdo->prepare("
                    INSERT INTO token_logs (token_id, user_id, action_type, old_value, new_value, note, created_at) 
                    VALUES (:token_id, :user_id, 'STATUS_CHANGE', 'complete', 'received', :note, NOW())
                ");
                $logStmt->execute([
                    'token_id' => $tokenId,
                    'user_id'  => $userId,
                    'note'     => 'Marked received by staff: ' . $processedBy
                ]);
            }

            $pdo->commit();

            // Success: Redirect to the 'received' tab showing the newly received token at the top
            header("Location: {$redirectPage}?tab=received&msg=received_success&token=" . urlencode($tokenId));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            die("<div style='color:#f87171; background:#0b0f19; padding:24px; font-weight:bold; font-family:sans-serif; text-align:center;'>Database Transaction Error: " . htmlspecialchars($e->getMessage()) . "</div>");
        }
    } else {
        // Missing required fields
        header("Location: {$redirectPage}?tab=complete&msg=not_complete&token=" . urlencode($tokenId));
        exit;
    }
}

// Fallback redirect
header("Location: {$redirectPage}");
exit;