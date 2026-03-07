<?php
session_start();
require_once "../../config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status"=>"error"]);
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
    echo json_encode(["status"=>"denied"]);
    exit;
}

$total_users = pg_fetch_result(
    pg_query($conn, "SELECT COUNT(*) FROM users"),
    0, 0
);

$total_grids = pg_fetch_result(
    pg_query($conn, "SELECT COUNT(*) FROM grids"),
    0, 0
);

$total_xp = pg_fetch_result(
    pg_query($conn, "SELECT COALESCE(SUM(xp),0) FROM users"),
    0, 0
);

$total_distance = pg_fetch_result(
    pg_query($conn, "SELECT COALESCE(SUM(total_distance),0) FROM users"),
    0, 0
);

$top_player = pg_query($conn,
    "SELECT username, xp FROM users ORDER BY xp DESC LIMIT 1"
);

$top = pg_fetch_assoc($top_player);

echo json_encode([
    "status"=>"success",
    "total_users"=>$total_users,
    "total_grids"=>$total_grids,
    "total_xp"=>$total_xp,
    "total_distance"=>round($total_distance, 2),
    "top_player"=>$top ? $top["username"] : "None",
    "top_xp"=>$top ? $top["xp"] : 0
]);