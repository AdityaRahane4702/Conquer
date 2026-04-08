<?php
session_start();
require_once "../../config/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$user_id = $_SESSION["user_id"];

$result = pg_query_params($conn, "DELETE FROM notifications WHERE user_id = $1", [$user_id]);

if ($result) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete from database"]);
}
?>
