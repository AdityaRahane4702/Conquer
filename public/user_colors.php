<?php
require_once "../config/db.php";

$result = pg_query($conn,
    "SELECT u.id, u.username, u.color,
            COUNT(g.id) AS total_grids
     FROM users u
     LEFT JOIN grids g ON u.id = g.owner_id
     GROUP BY u.id
     ORDER BY total_grids DESC"
);

echo "<h2>User Color Panel 🎨</h2>";

echo "<table border='1' cellpadding='10' cellspacing='0'>";
echo "<tr>
        <th>Username</th>
        <th>Color Code</th>
        <th>Color Preview</th>
        <th>Total Grids</th>
      </tr>";

while ($row = pg_fetch_assoc($result)) {

    echo "<tr>
            <td>{$row['username']}</td>
            <td>{$row['color']}</td>
            <td>
                <div style='width:40px;height:20px;
                            background:{$row['color']};
                            border:1px solid #000;'>
                </div>
            </td>
            <td>{$row['total_grids']}</td>
          </tr>";
}

echo "</table>";
?>