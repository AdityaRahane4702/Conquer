<?php
session_start();
require_once "../../config/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data["session_id"])) {
    echo json_encode(["status" => "error", "message" => "Missing session_id"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$session_id = intval($data["session_id"]);

$result = pg_query_params(
    $conn,
    "DELETE FROM user_sessions WHERE id = $1 AND user_id = $2",
    [$session_id, $user_id]
);

if ($result) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>
