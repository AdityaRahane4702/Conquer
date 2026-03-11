<?php
require_once "../../config/db.php";

$result = pg_query($conn,
    "SELECT u.username, u.color, u.is_bot, u.xp, u.level, u.total_distance, COUNT(g.id) AS total_grids
     FROM users u
     LEFT JOIN grids g ON u.id = g.owner_id
     GROUP BY u.id, u.username, u.color, u.is_bot, u.xp, u.level, u.total_distance
     ORDER BY total_grids DESC, u.xp DESC"
);

$leaders = [];

while ($row = pg_fetch_assoc($result)) {
    $leaders[] = $row;
}

header("Content-Type: application/json");
echo json_encode($leaders);