<?php if(session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Conquer</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
</head>
<body>

<header>
    <h1>Conquer 🌍</h1>
    <?php if(isset($_SESSION['username'])): ?>
        <p>Welcome, <?php echo $_SESSION['username']; ?></p>
    <?php endif; ?>
</header>