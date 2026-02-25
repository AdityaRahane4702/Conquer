<?php
require_once "../../config/db.php";

$result = pg_query(
    $conn,
    "SELECT g.grid_x, g.grid_y, g.strength, u.color
     FROM grids g
     JOIN users u ON g.owner_id = u.id"
);

$grids = [];

while ($row = pg_fetch_assoc($result)) {
    $grids[] = $row;
}

echo json_encode($grids);