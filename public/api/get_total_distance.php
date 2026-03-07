<?php
session_start();
require_once "../../config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status"=>"error"]);
    exit;
}

$user_id = $_SESSION["user_id"];

$result = pg_query_params(
    $conn,
    "SELECT total_distance, xp, level FROM users WHERE id = $1",
    [$user_id]
);

if ($row = pg_fetch_assoc($result)) {
    echo json_encode([
        "status" => "success",
        "distance_km" => round(floatval($row["total_distance"]), 3),
        "xp" => intval($row["xp"]),
        "level" => intval($row["level"])
    ]);
} else {
    echo json_encode(["status" => "error"]);
}
exit;
