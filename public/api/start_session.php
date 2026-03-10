<?php
session_start();
require_once "../../config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION["user_id"];

// End any other active sessions just in case
pg_query_params($conn, "UPDATE user_sessions SET status = 'ended', end_time = CURRENT_TIMESTAMP WHERE user_id = $1 AND status = 'active'", [$user_id]);

$result = pg_query_params(
    $conn,
    "INSERT INTO user_sessions (user_id, status) VALUES ($1, 'active') RETURNING id",
    [$user_id]
);

if ($result) {
    $row = pg_fetch_assoc($result);
    echo json_encode(["status" => "success", "session_id" => $row["id"]]);
} else {
    echo json_encode(["status" => "error", "message" => pg_last_error($conn)]);
}
