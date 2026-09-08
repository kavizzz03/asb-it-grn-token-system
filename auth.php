<?php
if (session_status() === PHP_SESSION_NONE) {
    // Set 12-hour cookie lifespan on initialization (12 hours = 43200 seconds)
    session_set_cookie_params([
        'lifetime' => 43200,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Validates user authentication and enforces 12-hour session expiry
 */
function checkAuth() {
    // 1. Verify if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }

    // 2. Enforce 12-hour session expiration
    if (isset($_SESSION['expire_time']) && time() > $_SESSION['expire_time']) {
        // Unset session variables
        $_SESSION = array();

        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Destroy server session
        session_destroy();

        header("Location: index.php?error=expired");
        exit;
    }
}

/**
 * Logs user actions and IP addresses to the audit database
 */
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

/**
 * Fetches authorized UI tabs based on user role
 */
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