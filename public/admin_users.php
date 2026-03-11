<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$check = pg_query_params(
    $conn,
    "SELECT is_admin FROM users WHERE id = $1",
    [$user_id]
);

$row = pg_fetch_assoc($check);

if (!$row["is_admin"]) {
    die("Access denied.");
}

$result = pg_query($conn,
    "SELECT u.id, u.username, u.level, u.xp,
            COUNT(g.id) AS total_grids
     FROM users u
     LEFT JOIN grids g ON u.id = g.owner_id
     GROUP BY u.id
     ORDER BY u.level DESC"
);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Users - Conquer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="bottom-nav">
    <a href="dashboard.php"><span>📍</span> Map</a>
    <a href="leaderboard.php"><span>🏆</span> Leaders</a>
    <a href="notifications.php" class="nav-item">
        <span>🔔</span> Alerts
        <span id="notif-badge" class="notif-badge">0</span>
    </a>
    <a href="profile.php"><span>👤</span> Profile</a>
    <?php if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]): ?>
        <a href="admin.php" class="active" style="color: #f87171;"><span>🛡️</span> Admin</a>
    <?php endif; ?>
</div>

<div class="admin-container">
    <header>
        <h2>User Management</h2>
        <div class="nav-links">
            <a href="admin.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </header>

    <div class="table-container">
        <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Level</th>
                <th>XP</th>
                <th>Total Grids</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>

        <?php while ($row = pg_fetch_assoc($result)) { ?>

        <tr>
            <td><?php echo $row["id"]; ?></td>
            <td><?php echo $row["username"]; ?></td>
            <td><?php echo $row["level"]; ?></td>
            <td><?php echo $row["xp"]; ?></td>
            <td><?php echo $row["total_grids"]; ?></td>
            <td>
                <a href="admin_reset_user.php?id=<?php echo $row["id"]; ?>" class="action-link reset">Reset Stats</a> 
                <a href="admin_delete_user.php?id=<?php echo $row["id"]; ?>" 
                   onclick="return confirm('Are you sure you want to PERMANENTLY delete this user and all their data?');" 
                   class="action-link delete">Delete User</a>
            </td>
        </tr>

        <?php } ?>
        </tbody>
        </table>
    </div>
</div>

</body>
</html>