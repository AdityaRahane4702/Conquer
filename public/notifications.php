<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Fetch notifications
$query = "SELECT * FROM notifications WHERE user_id = $1 ORDER BY created_at DESC LIMIT 50";
$result = pg_query_params($conn, $query, [$user_id]);
$notifications = pg_fetch_all($result) ?: [];

// Mark all as read when opening the page
pg_query_params($conn, "UPDATE notifications SET is_read = TRUE WHERE user_id = $1", [$user_id]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Alerts - Conquer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            background: radial-gradient(circle at top left, #1e293b, #0f172a);
        }
        .notifications-container {
            padding: 20px;
            padding-bottom: 120px;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .notif-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.8));
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            animation: slideIn 0.4s ease-out forwards;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .notif-card:hover {
            transform: translateX(5px);
            background: rgba(30, 41, 59, 0.85);
            border-color: rgba(34, 211, 238, 0.3);
        }
        .notif-card.unread {
            border-left: 5px solid #22d3ee;
            background: rgba(34, 211, 238, 0.05);
        }
        .notif-icon {
            font-size: 20px;
            min-width: 42px;
            height: 42px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.4);
        }
        .notif-content {
            flex: 1;
        }
        .notif-msg {
            font-size: 15px;
            font-weight: 500;
            line-height: 1.4;
            color: #f8fafc;
            margin-bottom: 4px;
        }
        .notif-time {
            font-size: 11px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .delete-notif-btn {
            background: transparent;
            border: none;
            color: rgba(148, 163, 184, 0.3);
            font-size: 18px;
            cursor: pointer;
            padding: 5px;
            line-height: 1;
            transition: all 0.2s;
            font-weight: bold;
        }
        .delete-notif-btn:hover {
            color: #ef4444;
            transform: scale(1.2);
        }
        .page-header {
            padding: 30px 20px 15px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .page-header h2 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #fff;
            margin: 0;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .clear-btn {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .clear-btn:hover {
            background: #ef4444;
            color: white;
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .no-notif {
            text-align: center;
            padding: 80px 20px;
            color: #64748b;
        }
    </style>
</head>
<body>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <h2>INTEL FEED</h2>
    <?php if (!empty($notifications)): ?>
        <button onclick="clearNotifications()" class="clear-btn">Clear All</button>
    <?php endif; ?>
</div>

<div class="notifications-container">
    <?php if (empty($notifications)): ?>
        <div class="no-notif">
            <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.2; filter: grayscale(1);">📡</div>
            <p style="font-size: 16px; font-weight: 600;">No mission intelligence available.</p>
            <p style="font-size: 14px; opacity: 0.6; margin-top: 5px;">Return to base and scan the perimeter.</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notif): 
            $icon = "🔔";
            if ($notif['type'] == 'attack') $icon = "💢";
            if ($notif['type'] == 'level_up') $icon = "⚡";
            if ($notif['type'] == 'mission') $icon = "🎯";
        ?>
            <div class="notif-card <?php echo $notif['is_read'] === 'f' ? 'unread' : ''; ?>" id="notif-<?php echo $notif['id']; ?>">
                <div class="notif-icon"><?php echo $icon; ?></div>
                <div class="notif-content">
                    <div class="notif-msg"><?php echo htmlspecialchars($notif['message']); ?></div>
                    <div class="notif-time"><?php echo date("M d, H:i", strtotime($notif['created_at'])); ?></div>
                </div>
                <button onclick="deleteNotification(<?php echo $notif['id']; ?>)" class="delete-notif-btn">&times;</button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="bottom-nav">
    <a href="dashboard.php"><span>📍</span> Map</a>
    <a href="leaderboard.php"><span>🏆</span> Leaders</a>
    <a href="notifications.php" class="active"><span>🔔</span> Alerts</a>
    <a href="profile.php"><span>👤</span> Profile</a>
    <?php if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]): ?>
        <a href="admin.php" style="color: #f87171;"><span>🛡️</span> Admin</a>
    <?php endif; ?>
</div>

<script>
function clearNotifications() {
    if (!confirm("Are you sure you want to wipe all Intel reports?")) return;
    
    fetch('/api/clear_notifications.php', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert("Failed to clear reports.");
            }
        });
}

function deleteNotification(id) {
    fetch('/api/delete_notification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notif_id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const card = document.getElementById('notif-' + id);
            if (card) {
                card.style.transform = 'scale(0.9) translateX(20px)';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    if (document.querySelectorAll('.notif-card').length === 0) {
                        location.reload();
                    }
                }, 300);
            }
        }
    });
}
</script>
</body>
</html>
