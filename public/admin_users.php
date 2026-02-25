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
</head>
<body>

<h2>User Management</h2>

<a href="admin.php">Back to Dashboard</a>
<br><br>

<table border="1" cellpadding="10">
<tr>
    <th>ID</th>
    <th>Username</th>
    <th>Level</th>
    <th>XP</th>
    <th>Total Grids</th>
    <th>Actions</th>
</tr>

<?php while ($row = pg_fetch_assoc($result)) { ?>

<tr>
    <td><?php echo $row["id"]; ?></td>
    <td><?php echo $row["username"]; ?></td>
    <td><?php echo $row["level"]; ?></td>
    <td><?php echo $row["xp"]; ?></td>
    <td><?php echo $row["total_grids"]; ?></td>
    <td>
        <a href="admin_reset_user.php?id=<?php echo $row["id"]; ?>">Reset User</a>
    </td>
</tr>

<?php } ?>

</table>

</body>
</html>