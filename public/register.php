<?php
session_start();
require_once "../config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $color = trim($_POST["color"]);

    if (empty($username) || empty($password) || empty($color)) {
        $error = "All fields are required.";
    } else {

        $check = pg_query_params(
            $conn,
            "SELECT id FROM users WHERE username=$1",
            [$username]
        );

        if (pg_num_rows($check) > 0) {
            $error = "Username already exists.";
        } else {

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            pg_query_params(
                $conn,
                "INSERT INTO users (username, password, color)
                 VALUES ($1,$2,$3)",
                [$username, $hashed, $color]
            );

            header("Location: login.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Register - Conquer</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body {
    margin: 0;
    font-family: sans-serif;
    background: linear-gradient(135deg,#0f172a,#1e293b);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

.card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    padding: 40px;
    width: 320px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    color: white;
}

.card h2 {
    text-align: center;
    margin-bottom: 25px;
}

input[type="text"],
input[type="password"] {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 8px;
    border: none;
    outline: none;
    background: rgba(255,255,255,0.1);
    color: white;
}

input:focus {
    background: rgba(255,255,255,0.2);
}

.color-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.color-row span {
    font-size: 14px;
    color: #cbd5e1;
}

.color-row input[type="color"] {
    width: 45px;
    height: 45px;
    border: none;
    padding: 0;
    background: none;
    cursor: pointer;
}

button {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: none;
    background: #22d3ee;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    background: #06b6d4;
}

.error {
    color: #f87171;
    text-align: center;
    margin-bottom: 15px;
}

.link {
    text-align: center;
    margin-top: 20px;
}

.link a {
    color: #22d3ee;
    text-decoration: none;
}
</style>
</head>
<body>

<div class="card">
    <h2>Create Account</h2>

    <?php if ($error) echo "<div class='error'>$error</div>"; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>

        <div class="color-row">
            <span>Choose Color</span>
            <input type="color" name="color" value="#22d3ee" required>
        </div>

        <button type="submit">Create Account</button>
    </form>

    <div class="link">
        <a href="login.php">Already have an account?</a>
    </div>
</div>

</body>
</html>