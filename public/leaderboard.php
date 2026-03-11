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
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>window.currentUserName = <?php echo json_encode($_SESSION['username']); ?>;</script>
    <style>
        .bot-badge {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, 0.4);
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 900;
        }
        .me-tag {
            background: rgba(34, 211, 238, 0.2);
            color: #22d3ee;
            border: 1px solid rgba(34, 211, 238, 0.4);
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 900;
        }
    </style>
</head>
<body>

<div class="container" id="leaderboardApp" style="padding-bottom: 100px;">
    <header>
        <h2>HALL OF FAME</h2>
        <a href="dashboard.php" class="back-btn">← Back</a>
    </header>

    <div class="leaderboard-list" id="leaderboardList">
        <!-- JS will populate this -->
        <div style="text-align:center; padding: 40px; color: #64748b;">Loading legends...</div>
    </div>
</div>

<script>
fetch('/api/get_leaderboard.php')
    .then(res => res.json())
    .then(data => {
        const list = document.querySelector("#leaderboardList");
        list.innerHTML = "";

        data.forEach((user, index) => {
            const rank = index + 1;
            let rankClass = 'rank-normal';
            if (rank === 1) rankClass = 'rank-1';
            else if (rank === 2) rankClass = 'rank-2';
            else if (rank === 3) rankClass = 'rank-3';

            const card = document.createElement("div");
            card.className = "leaderboard-card";
            card.style.animationDelay = `${index * 0.05}s`;

            const isMe = user.username === window.currentUserName;
            const isBot = user.is_bot === 't';

            card.innerHTML = `
                <div class="rank-badge ${rankClass}">${rank}</div>
                <div class="user-main">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="username">${user.username}</span>
                        ${isMe ? '<span class="me-tag">YOU</span>' : ''}
                    </div>
                    <div class="user-meta">
                        <div style="display:flex; gap:10px; font-size: 11px; opacity: 0.8;">
                           <span>⚡ Level ${user.level}</span>
                           <span>💠 ${user.xp} XP</span>
                           <span>🏃 ${parseFloat(user.total_distance).toFixed(2)} KM</span>
                        </div>
                    </div>
                </div>
                <div class="grid-count">
                    <span class="count-val">${user.total_grids}</span>
                    <span class="count-label">Grids</span>
                </div>
            `;

            list.appendChild(card);
        });
    });

// Check Notifications
function checkNotifications() {
    fetch('/api/get_unread_notifications.php')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById("notif-badge");
            if (data.unread_count > 0) {
                badge.innerText = data.unread_count;
                badge.style.display = "block";
            } else {
                badge.style.display = "none";
            }
        });
}
setInterval(checkNotifications, 4000);
checkNotifications();
</script>

<div class="bottom-nav">
    <a href="dashboard.php"><span>📍</span> Map</a>
    <a href="leaderboard.php" class="active"><span>🏆</span> Leaders</a>
    <a href="notifications.php" class="nav-item">
        <span>🔔</span> Alerts
        <span id="notif-badge" class="notif-badge">0</span>
    </a>
    <a href="profile.php"><span>👤</span> Profile</a>
    <?php if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]): ?>
        <a href="admin.php" style="color: #f87171;"><span>🛡️</span> Admin</a>
    <?php endif; ?>
</div>

</body>
</html>