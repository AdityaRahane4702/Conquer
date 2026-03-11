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
        .notifications-container {
            padding: 20px;
            padding-bottom: 100px;
            overflow-y: auto;
            flex: 1;
        }
        .notif-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideIn 0.3s ease-out forwards;
        }
        .notif-card.unread {
            border-left: 4px solid #22d3ee;
            background: rgba(34, 211, 238, 0.05);
        }
        .notif-icon {
            font-size: 20px;
            min-width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notif-content {
            flex: 1;
        }
        .notif-msg {
            font-size: 14px;
            line-height: 1.4;
            color: #e2e8f0;
            margin-bottom: 4px;
        }
        .notif-time {
            font-size: 11px;
            color: #64748b;
        }
        .page-header {
            padding: 24px 20px 10px;
            background: #0f172a;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .page-header h2 {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: #fff;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .no-notif {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
    </style>
</head>
<body>

<div class="page-header">
    <h2>INTEL FEED</h2>
</div>

<div class="notifications-container">
    <?php if (empty($notifications)): ?>
        <div class="no-notif">
            <div style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;">📡</div>
            <p>No new intelligence reports, soldier. Stay vigilant!</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notif): 
            $icon = "🔔";
            if ($notif['type'] == 'attack') $icon = "💢";
            if ($notif['type'] == 'level_up') $icon = "⚡";
            if ($notif['type'] == 'mission') $icon = "🎯";
        ?>
            <div class="notif-card <?php echo $notif['is_read'] === 'f' ? 'unread' : ''; ?>">
                <div class="notif-icon"><?php echo $icon; ?></div>
                <div class="notif-content">
                    <div class="notif-msg"><?php echo htmlspecialchars($notif['message']); ?></div>
                    <div class="notif-time"><?php echo date("M d, H:i", strtotime($notif['created_at'])); ?></div>
                </div>
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

</body>
</html>
