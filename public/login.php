<?php
session_start();
require_once "../config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $result = pg_query_params(
        $conn,
        "SELECT id, password, is_admin FROM users WHERE username=$1",
        [$username]
    );

    if (pg_num_rows($result) == 1) {

        $user = pg_fetch_assoc($result);

        if (password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $username;
            $_SESSION["is_admin"] = ($user["is_admin"] === 't'); // PostgreSQL boolean to PHP
            header("Location: dashboard.php");
            exit;
        }
    }

    $error = "Invalid username or password.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login - Conquer</title>
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

input {
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
    margin-bottom: 10px;
}

.link {
    text-align: center;
    margin-top: 15px;
}

.link a {
    color: #22d3ee;
    text-decoration: none;
}
</style>
</head>
<body>

<div class="card">
    <h2>Conquer</h2>

    <?php if ($error) echo "<div class='error'>$error</div>"; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>

    <div class="link">
        <a href="register.php">Create account</a>
    </div>
</div>

</body>
</html>