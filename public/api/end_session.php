<?php
session_start();
require_once "../../config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data["session_id"])) {
    echo json_encode(["status" => "error", "message" => "Missing session ID"]);
    exit;
}

$session_id = intval($data["session_id"]);
$user_id = $_SESSION["user_id"];

// Calculate total session stats before closing
$move_stats = pg_query_params(
    $conn,
    "SELECT 
        COUNT(*) as move_count,
        SUM(CASE WHEN id > 0 THEN 1 ELSE 0 END) as placeholder 
     FROM user_movements 
     WHERE session_id = $1 AND user_id = $2",
    [$session_id, $user_id]
);

// We update the session as ended
$result = pg_query_params(
    $conn,
    "UPDATE user_sessions 
     SET status = 'ended', 
         end_time = CURRENT_TIMESTAMP
     WHERE id = $1 AND user_id = $2",
    [$session_id, $user_id]
);

if ($result) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => pg_last_error($conn)]);
}
