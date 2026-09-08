<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
}

function logUserAction($pdo, $user_id, $username, $action) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, username, action, ip_address) VALUES (:user_id, :username, :action, :ip_address)");
    $stmt->execute([
        ':user_id'    => $user_id,
        ':username'   => $username,
        ':action'     => $action,
        ':ip_address' => $ip_address
    ]);
}

function getAssignedTabs($pdo, $role_id) {
    $stmt = $pdo->prepare("
        SELECT t.tab_name, t.url_link, t.icon_class 
        FROM tabs t
        INNER JOIN role_tabs rt ON t.tab_id = rt.tab_id
        WHERE rt.role_id = :role_id
        ORDER BY t.sort_order ASC
    ");
    $stmt->execute([':role_id' => $role_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>