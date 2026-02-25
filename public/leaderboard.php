<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Leaderboard - Conquer</title>
    <link rel="stylesheet" href="/assets/css/leaderboard.css">
</head>
<body>

<h2>Leaderboard 🏆</h2>
<a href="dashboard.php">Back to Map</a>

<table border="1" cellpadding="10" cellspacing="0" id="leaderboardTable">
    <thead>
        <tr>
            <th>Rank</th>
            <th>Username</th>
            <th>Color</th>
            <th>Total Grids</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

<script>
fetch('/api/get_leaderboard.php')
    .then(res => res.json())
    .then(data => {

        const tbody = document.querySelector("#leaderboardTable tbody");
        tbody.innerHTML = "";

        data.forEach((user, index) => {

            const row = document.createElement("tr");

            row.innerHTML = `
                <td>${index + 1}</td>
                <td>${user.username}</td>
                <td>
                    <div style="width:40px;height:20px;background:${user.color};border:1px solid #000;"></div>
                </td>
                <td>${user.total_grids}</td>
            `;

            tbody.appendChild(row);
        });
    });
</script>

</body>
</html>