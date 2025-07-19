<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
check_admin_login();

$admin_name = $_SESSION['admin_name'];

// Statistiques simples
$total_trajets = $conn->query("SELECT COUNT(*) AS total FROM trajets")->fetch_assoc()['total'];
$total_reservations = $conn->query("SELECT COUNT(*) AS total FROM reservations")->fetch_assoc()['total'];
$total_etudiants = $conn->query("SELECT COUNT(*) AS total FROM etudiants")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - UCB Transport</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand">🎛️ Admin - UCB Transport</span>
            <div class="ms-auto">
                <span class="text-white me-3">Connecté : <?php echo $admin_name; ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h3>📊 Tableau de bord</h3>

        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card border-primary shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">🚌 Trajets</h5>
                        <p class="card-text display-6"><?php echo $total_trajets; ?></p>
                        <a href="trajets.php" class="btn btn-primary">Gérer</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-success shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">🎫 Réservations</h5>
                        <p class="card-text display-6"><?php echo $total_reservations; ?></p>
                        <a href="reservations.php" class="btn btn-success">Voir</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-info shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">🎓 Étudiants</h5>
                        <p class="card-text display-6"><?php echo $total_etudiants; ?></p>
                        <a href="#" class="btn btn-info disabled">À venir</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
