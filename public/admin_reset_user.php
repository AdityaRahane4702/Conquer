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

$target = intval($_GET["id"]);

pg_query_params($conn, "DELETE FROM grids WHERE owner_id = $1", [$target]);
pg_query_params($conn, "DELETE FROM user_movements WHERE user_id = $1", [$target]);
pg_query_params($conn, "UPDATE users SET xp = 0, level = 1 WHERE id = $1", [$target]);

header("Location: admin_users.php");
exit;