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

pg_query($conn, "BEGIN");
$success = true;

$queries = [
    "DELETE FROM grids",
    "DELETE FROM user_sessions",
    "DELETE FROM user_movements",
    "DELETE FROM notifications",
    "DELETE FROM users WHERE is_admin = FALSE",
    "UPDATE users SET total_distance = 0, xp = 0, level = 1 WHERE is_admin = TRUE"
];

foreach ($queries as $q) {
    if (!pg_query($conn, $q)) {
        $success = false;
        break;
    }
}

if ($success) {
    pg_query($conn, "COMMIT");
} else {
    pg_query($conn, "ROLLBACK");
    die("Database error during reset.");
}

header("Location: admin.php");
exit;