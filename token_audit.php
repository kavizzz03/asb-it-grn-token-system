<?php
// DB Connection Settings
$host    = '127.0.0.1';
$db      = 'token_system';
$user    = 'root';
$pass    = ''; // Adjust to match your database password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// Retrieve search and filter parameters
$tab        = $_GET['tab'] ?? 'all';
$search     = trim($_GET['search'] ?? '');
$token_date = $_GET['token_date'] ?? '';
$branch_id  = $_GET['branch_id'] ?? '';
$floor_id   = $_GET['floor_id'] ?? '';

// Build Dynamic SQL Query with Token, Branch, and Floor filters
$query = "SELECT l.*, 
                 COALESCE(u.name, u.username, CONCAT('User #', l.user_id)) AS display_user
          FROM token_logs l 
          LEFT JOIN users u ON l.user_id = u.user_id 
          LEFT JOIN tokens t ON l.token_id = t.token_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND l.token_id LIKE :search";
    $params['search'] = "%$search%";
}

if (!empty($token_date)) {
    $query .= " AND DATE(l.created_at) = :token_date";
    $params['token_date'] = $token_date;
}

if (!empty($branch_id)) {
    $query .= " AND t.branch_id = :branch_id";
    $params['branch_id'] = $branch_id;
}

if (!empty($floor_id)) {
    $query .= " AND t.floor_id = :floor_id";
    $params['floor_id'] = $floor_id;
}

$query .= " ORDER BY l.log_id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (\PDOException $e) {
    // Fallback join if user primary key is 'id' instead of 'user_id'
    $query = str_replace("u.user_id", "u.id", $query);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
}

// Get current script filename automatically
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Token Audit Logs</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f6f9; }
        .filter-card { background: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-card form { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-card input, .filter-card button { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; }
        .filter-card button { background-color: #007bff; color: white; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; }
        th { background-color: #f8fafc; font-weight: bold; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .badge-pending { background: #ffeaa7; color: #d63031; }
        .badge-process { background: #74b9ff; color: #0984e3; }
        .badge-received { background: #a29bfe; color: #6c5ce7; }
        .badge-complete { background: #55efc4; color: #00b894; }
    </style>
</head>
<body>

<h2>Token Audit Logs</h2>

<div class="filter-card">
    <form method="GET" action="<?= htmlspecialchars($current_page) ?>">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <input type="text" name="search" placeholder="Search Token ID..." value="<?= htmlspecialchars($search) ?>">
        <input type="date" name="token_date" value="<?= htmlspecialchars($token_date) ?>">
        <input type="text" name="branch_id" placeholder="Branch ID" value="<?= htmlspecialchars($branch_id) ?>">
        <input type="text" name="floor_id" placeholder="Floor ID" value="<?= htmlspecialchars($floor_id) ?>">
        <button type="submit">Filter Logs</button>
        <a href="<?= htmlspecialchars($current_page) ?>" style="align-self: center; text-decoration: none; color: #666;">Reset</a>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>Log ID</th>
            <th>Token ID</th>
            <th>Processed / Logged By</th>
            <th>Action</th>
            <th>Old Value</th>
            <th>New Value</th>
            <th>Note / Details</th>
            <th>Timestamp</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['log_id']) ?></td>
                    <td><strong><?= htmlspecialchars($log['token_id']) ?></strong></td>
                    <td><?= htmlspecialchars($log['display_user']) ?></td>
                    <td><?= htmlspecialchars($log['action_type']) ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($log['old_value']) ?>"><?= htmlspecialchars($log['old_value']) ?></span></td>
                    <td><span class="badge badge-<?= htmlspecialchars($log['new_value']) ?>"><?= htmlspecialchars($log['new_value']) ?></span></td>
                    <td><?= htmlspecialchars($log['note'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" style="text-align: center; color: #888;">No token logs found matching criteria.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>