<?php
session_start();
require_once "../../config/db.php";

header("Content-Type: application/json");

// Only admins can create bots
if (!isset($_SESSION["is_admin"]) || !$_SESSION["is_admin"]) {
    echo json_encode(["status" => "error", "message" => "Admin only"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["lat"]) || !isset($data["lng"])) {
    echo json_encode(["status" => "error", "message" => "Location missing"]);
    exit;
}

$lat = floatval($data["lat"]);
$lng = floatval($data["lng"]);

// Use provided name or generate one
$bot_name = !empty($data["bot_name"]) ? trim($data["bot_name"]) : "Bot_" . substr(md5(time()), 0, 4);
$bot_color = '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);

// 1. Create bot user
$res = pg_query_params(
    $conn,
    "INSERT INTO users (username, password, color, is_bot) VALUES ($1, 'bot_pass', $2, TRUE) RETURNING id",
    [$bot_name, $bot_color]
);

if (!$res) {
    echo json_encode(["status" => "error", "message" => pg_last_error($conn)]);
    exit;
}

$bot_id = pg_fetch_result($res, 0, 0);

// 2. Set initial position
pg_query_params(
    $conn,
    "INSERT INTO user_movements (user_id, latitude, longitude) VALUES ($1, $2, $3)",
    [$bot_id, $lat, $lng]
);

echo json_encode(["status" => "success", "bot_name" => $bot_name, "color" => $bot_color]);
