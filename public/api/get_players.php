<?php
require_once "../../config/db.php";

// Fetch bots and recently active human players (last 1 hour)
$result = pg_query(
    $conn,
    "SELECT DISTINCT ON (u.id) 
        u.id, 
        u.username, 
        u.color, 
        u.is_bot,
        m.latitude, 
        m.longitude, 
        m.recorded_at
     FROM users u
     JOIN user_movements m ON u.id = m.user_id
     WHERE u.is_bot = TRUE 
        OR m.recorded_at >= CURRENT_TIMESTAMP - INTERVAL '1 hour'
     ORDER BY u.id, m.recorded_at DESC"
);

$players = [];
while ($row = pg_fetch_assoc($result)) {
    $players[] = [
        "id" => $row["id"],
        "username" => $row["username"],
        "color" => $row["color"],
        "is_bot" => $row["is_bot"] === 't',
        "lat" => floatval($row["latitude"]),
        "lng" => floatval($row["longitude"])
    ];
}

echo json_encode($players);
