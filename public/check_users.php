<?php
require_once "../config/db.php";

$result = pg_query($conn, "SELECT id, username, created_at FROM users ORDER BY id ASC");

echo "<h2>Registered Users</h2>";

echo "<table border='1' cellpadding='8'>";
echo "<tr>
        <th>ID</th>
        <th>Username</th>
        <th>Created At</th>
      </tr>";

while ($row = pg_fetch_assoc($result)) {
    echo "<tr>
            <td>{$row['id']}</td>
            <td>{$row['username']}</td>
            <td>{$row['created_at']}</td>
          </tr>";
}

echo "</table>";