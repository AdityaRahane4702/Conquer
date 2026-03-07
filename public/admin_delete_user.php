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

if (isset($_GET["id"])) {
    $target = intval($_GET["id"]);

    // Prevent deleting yourself
    if ($target === intval($user_id)) {
        die("You cannot delete your own admin account.");
    }

    // Since we have ON DELETE CASCADE in the database schema for grids and movements,
    // deleting the user will automatically clean up their data.
    pg_query_params($conn, "DELETE FROM users WHERE id = $1", [$target]);
}

header("Location: admin_users.php");
exit;
