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

$total_users = pg_fetch_result(
    pg_query($conn, "SELECT COUNT(*) FROM users"),
    0, 0
);

$percentile = 0;
if ($total_users > 1) {
    $percentile = round((($total_users - $rank) / ($total_users - 1)) * 100);
} else {
    $percentile = 100;
}

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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=JetBrains+Mono:wght@400;800&display=swap">
    <link rel="stylesheet" href="/assets/css/profile.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

</head>
<body>

<div class="container" style="padding-bottom: 120px;">
    <!-- HERO SECTION -->
    <div class="profile-hero">
        <div class="profile-icon-container">👤</div>
        <div class="hero-info">
            <h2><?php echo htmlspecialchars($user["username"]); ?></h2>
            <div class="member-since">Active since <?php echo date("M Y", strtotime($user["created_at"])); ?></div>
        </div>
        <form action="logout.php" method="POST">
            <button type="submit" class="logout-icon-btn" title="Sign Out">🚪</button>
        </form>
    </div>

    <!-- STATS GRID -->
    <div class="stats-dashboard-grid">
        <div class="stat-tile rank-tile">
            <div class="tile-label">Global Rank</div>
            <div class="tile-value">#<?php echo $rank; ?></div>
            <div class="tile-footer">Top <?php echo 100 - $percentile; ?>%</div>
        </div>
        <div class="stat-tile">
            <div class="tile-label">Level</div>
            <div class="tile-value"><?php echo $user["level"]; ?></div>
            <div class="tile-footer">Soldier</div>
        </div>
        <div class="stat-tile">
            <div class="tile-label">Distance</div>
            <div class="tile-value"><?php echo round($total_distance, 1); ?></div>
            <div class="tile-footer">Kms</div>
        </div>
        <div class="stat-tile">
            <div class="tile-label">Grids</div>
            <div class="tile-value"><?php echo $grid_count; ?></div>
            <div class="tile-footer">Captured</div>
        </div>
    </div>

    <!-- PROGRESS SECTION -->
    <div class="progress-section-card">
        <div class="progress-header">
            <span>XP Progress</span>
            <span class="xp-count"><?php echo $user["xp"]; ?> Total XP</span>
        </div>
        <?php 
            $current_xp = $user["xp"];
            $xp_for_current_level = $current_xp % 20;
            $progress_percent = ($xp_for_current_level / 20) * 100;
        ?>
        <div class="xp-progress-container">
            <div class="xp-progress-bar" style="width: <?php echo $progress_percent; ?>%;"></div>
            <div class="xp-text"><?php echo $xp_for_current_level; ?> / 20 to Lvl <?php echo $user["level"] + 1; ?></div>
        </div>
        
        <div class="percentile-track">
            <div class="track-label">Global Dominance: <?php echo $percentile; ?>%</div>
            <div class="track-bar">
                <div class="track-fill" style="width: <?php echo $percentile; ?>%;"></div>
            </div>
        </div>
    </div>

    <!-- CHART SECTION -->
    <div class="performance-section">
        <h3 class="section-title">Consistency (Last 7 Days)</h3>
        <div class="chart-container">
            <div id="performanceChart" style="width: 100%;"></div>
        </div>
    </div>

    <!-- MISSIONS SECTION -->
    <div class="recent-runs">
        <h3 class="section-title">Recent Intelligence</h3>
        <?php
        $sessions = pg_query_params(
            $conn,
            "SELECT id, start_time, distance_km, xp_gain, grids_captured, status
             FROM user_sessions
             WHERE user_id = $1
             ORDER BY start_time DESC
             LIMIT 5",
            [$user_id]
        );

        if (pg_num_rows($sessions) > 0):
            while ($session = pg_fetch_assoc($sessions)):
        ?>
            <div class="run-card" id="mission-<?php echo $session['id']; ?>">
                <div class="run-header">
                    <span class="run-date"><?php echo date("d M, H:i", strtotime($session["start_time"])); ?></span>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="run-status <?php echo $session["status"]; ?>"><?php echo ucfirst($session["status"]); ?></span>
                        <button onclick="deleteMission(<?php echo $session['id']; ?>)" class="delete-mission-btn">&times;</button>
                    </div>
                </div>
                <div class="run-stats-row">
                    <span>⚡ +<?php echo $session["xp_gain"]; ?> XP</span>
                    <span>📍 <?php echo round($session["distance_km"], 2); ?> KM</span>
                    <span>🗺️ <?php echo $session["grids_captured"]; ?> Grids</span>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="no-runs">No active mission data found.</div>
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
            name: 'Distance Walked',
            data: <?php echo json_encode($chart_values); ?>
        }],
        chart: {
            type: 'line',
            height: 250,
            toolbar: { show: false },
            animations: { enabled: true, easing: 'easeinout', speed: 800 },
            dropShadow: {
                enabled: true,
                top: 5,
                left: 0,
                blur: 3,
                opacity: 0.3
            }
        },
        stroke: {
            curve: 'smooth',
            width: 4
        },
        markers: {
            size: 6,
            colors: ['#0f172a'],
            strokeColors: '#22d3ee',
            strokeWidth: 3,
            hover: { size: 8 }
        },
        dataLabels: {
            enabled: true,
            formatter: function (val) { return val > 0 ? val + "km" : ""; },
            offsetY: -10,
            background: {
                enabled: true,
                foreColor: '#fff',
                padding: 4,
                borderRadius: 2,
                borderWidth: 0,
                borderColor: '#22d3ee',
                backgroundColor: '#22d3ee'
            },
            style: { fontSize: '10px' }
        },
        colors: ['#22d3ee'],
        xaxis: {
            categories: <?php echo json_encode($labels); ?>,
            position: 'bottom',
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: '#64748b' } }
        },
        yaxis: {
            title: { text: "KM Walked", style: { color: "#64748b", fontWeight: "bold" } },
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: { colors: '#64748b' },
                formatter: function(val) { return val.toFixed(1); }
            }
        },
        grid: {
            borderColor: 'rgba(255, 255, 255, 0.1)',
            strokeDashArray: 4,
        },
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

function deleteMission(id) {
    if (!confirm("Are you sure you want to delete this mission?")) return;

    fetch('/api/delete_mission.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const card = document.getElementById('mission-' + id);
            if (card) {
                card.style.transform = 'scale(0.9)';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
            }
        } else {
            alert("Failed to delete mission.");
        }
    });
}
</script>
</body>
</html>