<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error"]);
    exit;
}

$user_id = $_SESSION["user_id"];

$result = pg_query_params(
    $conn,
    "SELECT COUNT(*) FROM notifications WHERE user_id = $1 AND is_read = FALSE",
    [$user_id]
);

$count = pg_fetch_result($result, 0, 0);

echo json_encode(["status" => "success", "unread_count" => intval($count)]);
