<?php
session_start();
require_once "../../config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status"=>"error"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["lat"]) || !isset($data["lng"])) {
    echo json_encode(["status"=>"error"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$lat = floatval($data["lat"]);
$lng = floatval($data["lng"]);

$last = pg_query_params(
    $conn,
    "SELECT latitude, longitude, recorded_at
     FROM user_movements
     WHERE user_id = $1
     ORDER BY recorded_at DESC
     LIMIT 1",
    [$user_id]
);

function haversine($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

$distance_km = 0;

if ($last && pg_num_rows($last) > 0) {

    $row = pg_fetch_assoc($last);

    $distance_km = haversine(
        $row["latitude"],
        $row["longitude"],
        $lat,
        $lng
    );

    $time_diff = time() - strtotime($row["recorded_at"]);

    if ($time_diff > 0) {
        $speed = ($distance_km / $time_diff) * 3600;

        if ($speed > 15) {
            echo json_encode(["status"=>"speed_violation"]);
            exit;
        }
    }
}

$session_id = isset($data["session_id"]) ? intval($data["session_id"]) : null;

pg_query_params(
    $conn,
    "INSERT INTO user_movements (user_id, latitude, longitude, session_id)
     VALUES ($1, $2, $3, $4)",
    [$user_id, $lat, $lng, $session_id]
);

if ($distance_km > 0) {
    // New logic: 0.5 KM = 1 XP
    $res = pg_query_params($conn, "SELECT total_distance FROM users WHERE id = $1", [$user_id]);
    $row = pg_fetch_assoc($res);
    $total_dist = floatval($row['total_distance']) + $distance_km;
    
    // XP = total_km / 0.5
    $new_xp = floor($total_dist / 0.5);
    
    // Level = floor(xp / 20) + 1
    $new_level = floor($new_xp / 20) + 1;

    pg_query_params(
        $conn,
        "UPDATE users
         SET total_distance = $1,
             xp = $2,
             level = $3
         WHERE id = $4",
        [$total_dist, $new_xp, $new_level, $user_id]
    );

    // Update individual session stats if we have a session_id
    if ($session_id) {
        pg_query_params(
            $conn,
            "UPDATE user_sessions
             SET distance_km = distance_km + $1,
                 xp_gain = xp_gain + $2
             WHERE id = $3 AND user_id = $4",
            [$distance_km, $xp_gain, $session_id, $user_id]
        );
    }
}

echo json_encode(["status"=>"saved"]);