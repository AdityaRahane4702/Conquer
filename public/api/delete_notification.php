<?php
session_start();
require_once "../../config/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data["notif_id"])) {
    echo json_encode(["status" => "error", "message" => "Missing notif_id"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$notif_id = intval($data["notif_id"]);

$result = pg_query_params(
    $conn,
    "DELETE FROM notifications WHERE id = $1 AND user_id = $2",
    [$notif_id, $user_id]
);

if ($result) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>
