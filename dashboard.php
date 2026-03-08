<?php
require_once 'core/init.php';
$page = require 'core/router.php';
?>
<!DOCTYPE html>
<html lang="bg" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

        <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>

<body class="app-body">

    <?php include 'partials/header.php'; ?>

    <main class="app-content">
        <?php include "pages/$page.php"; ?>
    </main>

    <?php include 'partials/bottom_nav.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/theme.js"></script>
<script src="assets/js/general.js"></script>
<script src="assets/js/geo_movement.js"></script>
<script src="assets/js/get_geo_data.js"></script>
</body>
</html>