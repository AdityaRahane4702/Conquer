<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$user = pg_query_params(
    $conn,
    "SELECT id, username, xp, level, total_distance, created_at
     FROM users
     WHERE id = $1",
    [$user_id]
);

$user = pg_fetch_assoc($user);
$total_distance = $user["total_distance"];

$grid_count = pg_fetch_result(
    pg_query_params(
        $conn,
        "SELECT COUNT(*) FROM grids WHERE owner_id = $1",
        [$user_id]
    ),
    0, 0
);

// Optimize Rank Calculation: count users with more XP
$rank = pg_fetch_result(
    pg_query_params(
        $conn,
        "SELECT COUNT(*) + 1 FROM users WHERE xp > $1",
        [$user["xp"]]
    ),
    0, 0
);

// --- FETCH WEEKLY DATA FOR CHART (Last 7 Days) ---
$weekly_data_res = pg_query_params(
    $conn,
    "SELECT CAST(start_time AS DATE) as date, SUM(distance_km) as total_dist
     FROM user_sessions
     WHERE user_id = $1 
       AND start_time >= CURRENT_DATE - INTERVAL '6 days'
     GROUP BY CAST(start_time AS DATE)
     ORDER BY date ASC",
    [$user_id]
);

$chart_data = [];
$labels = [];

// Fill last 7 days including today
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $display_date = date('d M', strtotime("-$i days"));
    $labels[] = $display_date;
    $chart_data[$date] = 0;
}

while ($row = pg_fetch_assoc($weekly_data_res)) {
    $chart_data[$row['date']] = round(floatval($row['total_dist']), 2);
}

$chart_values = array_values($chart_data);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile - Conquer</title>
    <style>
        body { font-family: Arial; }
        .card {
            max-width: 400px;
            margin: 40px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
        }
        .stat { margin: 10px 0; }
    </style>
    <link rel="stylesheet" href="/assets/css/profile.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

</head>
<body>

<div class="container" style="padding-bottom: 120px;">
    <div class="header-actions">
        <a href="dashboard.php" class="back-btn">← Back to Map</a>
    </div>

    <div class="profile-card">
        <div class="profile-icon-container">
            👤
        </div>
        
        <h2><?php echo htmlspecialchars($user["username"]); ?></h2>
        <div class="rank-badge">Global Rank #<?php echo $rank; ?></div>

        <div class="stats-grid">
            <div class="stat-item">
                <span class="stat-value"><?php echo $user["level"]; ?></span>
                <span class="stat-label">Level</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo number_format($user["xp"]); ?></span>
                <span class="stat-label">Total XP</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo round($total_distance, 1); ?></span>
                <span class="stat-label">KM Walked</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo $grid_count; ?></span>
                <span class="stat-label">Grids Captured</span>
            </div>
        </div>

        <div class="member-since">
            Enlisted on <?php echo date("M d, Y", strtotime($user["created_at"])); ?>
        </div>

        <div class="profile-actions">
            <form action="logout.php" method="POST" style="width: 100%;">
                <button type="submit" class="logout-btn">Sign Out</button>
            </form>
        </div>
    </div>

    <!-- Performance Chart Section -->
    <div class="performance-section">
        <h3 class="section-title">Weekly Progress</h3>
        <div class="chart-container">
            <div id="performanceChart" style="width: 100%;"></div>
        </div>
    </div>

    <!-- Recent Runs Section -->
    <div class="recent-runs">
        <h3 class="section-title">Recent Missions</h3>
        <?php
        $sessions = pg_query_params(
            $conn,
            "SELECT start_time, distance_km, xp_gain, grids_captured, status
             FROM user_sessions
             WHERE user_id = $1
             ORDER BY start_time DESC
             LIMIT 5",
            [$user_id]
        );

        if (pg_num_rows($sessions) > 0):
            while ($session = pg_fetch_assoc($sessions)):
        ?>
            <div class="run-card">
                <div class="run-header">
                    <span class="run-date"><?php echo date("M d, H:i", strtotime($session["start_time"])); ?></span>
                    <span class="run-status <?php echo $session["status"]; ?>"><?php echo ucfirst($session["status"]); ?></span>
                </div>
                <div class="run-stats">
                    <div class="run-stat">
                        <span class="val"><?php echo round($session["distance_km"], 2); ?></span>
                        <span class="lbl">KM</span>
                    </div>
                    <div class="run-stat">
                        <span class="val">+<?php echo $session["xp_gain"]; ?></span>
                        <span class="lbl">XP</span>
                    </div>
                    <div class="run-stat">
                        <span class="val"><?php echo $session["grids_captured"]; ?></span>
                        <span class="lbl">Grids</span>
                    </div>
                </div>
            </div>
        <?php 
            endwhile;
        else:
        ?>
            <div class="no-runs">No missions recorded yet. Get out there, soldier!</div>
        <?php endif; ?>
    </div>
</div>

<div class="bottom-nav">
    <a href="dashboard.php"><span>📍</span> Map</a>
    <a href="leaderboard.php"><span>🏆</span> Leaders</a>
    <a href="notifications.php" class="nav-item">
        <span>🔔</span> Alerts
        <span id="notif-badge" class="notif-badge">0</span>
    </a>
    <a href="profile.php" class="active"><span>👤</span> Profile</a>
    <?php if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]): ?>
        <a href="admin.php" style="color: #f87171;"><span>🛡️</span> Admin</a>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var options = {
        series: [{
            name: 'Distance',
            data: <?php echo json_encode($chart_values); ?>
        }],
        chart: {
            type: 'bar',
            height: 250,
            toolbar: { show: false },
            animations: { enabled: true, easing: 'easeinout', speed: 800 }
        },
        plotOptions: {
            bar: {
                borderRadius: 8,
                columnWidth: '60%',
                distributed: true,
                dataLabels: { position: 'top' }
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function (val) { return val > 0 ? val + "km" : ""; },
            offsetY: -20,
            style: { fontSize: '10px', colors: ["#fff"] }
        },
        colors: ['#22d3ee', '#0ea5e9', '#38bdf8', '#7dd3fc', '#06b6d4', '#0891b2', '#0e7490'],
        xaxis: {
            categories: <?php echo json_encode($labels); ?>,
            position: 'bottom',
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: '#64748b' } }
        },
        yaxis: {
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { show: false }
        },
        grid: { show: false },
        tooltip: { theme: 'dark' },
        legend: { show: false }
    };

    var chart = new ApexCharts(document.querySelector("#performanceChart"), options);
    chart.render();

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
    setInterval(checkNotifications, 3000);
    checkNotifications();
});
</script>
</body>
</html>