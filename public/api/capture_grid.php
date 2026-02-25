<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error"]);
    exit;
}

if (!isset($_SESSION["last_capture"])) {
    $_SESSION["last_capture"] = 0;
}

if (time() - $_SESSION["last_capture"] < 2) {
    echo json_encode(["status" => "cooldown"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["grid_x"]) || !isset($data["grid_y"])) {
    echo json_encode(["status" => "error"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$grid_x = intval($data["grid_x"]);
$grid_y = intval($data["grid_y"]);

$_SESSION["last_capture"] = time();

$result = pg_query_params(
    $conn,
    "SELECT owner_id, strength FROM grids WHERE grid_x=$1 AND grid_y=$2",
    [$grid_x, $grid_y]
);

if (pg_num_rows($result) == 0) {
    pg_query_params(
        $conn,
        "INSERT INTO grids (grid_x, grid_y, owner_id, strength)
         VALUES ($1,$2,$3,1)",
        [$grid_x, $grid_y, $user_id]
    );

    echo json_encode(["status" => "captured", "strength" => 1]);
    exit;
}

$row = pg_fetch_assoc($result);

$current_owner = $row["owner_id"];
$current_strength = intval($row["strength"]);

if ($current_owner == $user_id) {

    $new_strength = $current_strength + 1;

    pg_query_params(
        $conn,
        "UPDATE grids SET strength=$1 WHERE grid_x=$2 AND grid_y=$3",
        [$new_strength, $grid_x, $grid_y]
    );

    echo json_encode(["status" => "reinforced", "strength" => $new_strength]);
    exit;
}

$new_strength = $current_strength - 1;

if ($new_strength <= 0) {

    pg_query_params(
        $conn,
        "UPDATE grids
         SET owner_id=$1, strength=1
         WHERE grid_x=$2 AND grid_y=$3",
        [$user_id, $grid_x, $grid_y]
    );

    echo json_encode(["status" => "taken", "strength" => 1]);
    exit;
}

pg_query_params(
    $conn,
    "UPDATE grids SET strength=$1 WHERE grid_x=$2 AND grid_y=$3",
    [$new_strength, $grid_x, $grid_y]
);

echo json_encode(["status" => "attacked", "strength" => $new_strength]);