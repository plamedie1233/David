<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
check_student_login();

// Récupérer les infos de l'étudiant
$etudiant_nom = $_SESSION['etudiant_nom'];
$etudiant_id = $_SESSION['etudiant_id'];

// Récupérer les trajets disponibles
$sql = "SELECT * FROM trajets WHERE date_depart >= CURDATE() ORDER BY date_depart ASC, heure_depart ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord - UCB Transport</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand">🚍 UCB Transport</span>
            <div class="ms-auto">
                <span class="text-white me-3">Bienvenue, <?php echo $etudiant_nom; ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h4>📅 Trajets disponibles</h4>
        <p>Choisissez un trajet à réserver.</p>

        <?php if ($result->num_rows > 0): ?>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>Nom du trajet</th>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Capacité</th>
                    <th>Réserver</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($trajet = $result->fetch_assoc()): ?>
                <?php
                    // Vérifier le nombre de réservations déjà faites
                    $tid = $trajet['id'];
                    $count = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE trajet_id = $tid AND statut = 'réservé'")->fetch_assoc()['total'];
                    $places_restantes = $trajet['capacite'] - $count;
                ?>
                <tr>
                    <td><?php echo $trajet['nom_trajet']; ?></td>
                    <td><?php echo $trajet['date_depart']; ?></td>
                    <td><?php echo substr($trajet['heure_depart'], 0, 5); ?></td>
                    <td><?php echo $places_restantes . '/' . $trajet['capacite']; ?></td>
                    <td>
                        <?php if ($places_restantes > 0): ?>
                            <a href="reserver.php?trajet_id=<?php echo $tid; ?>" class="btn btn-sm btn-success">Réserver</a>
                        <?php else: ?>
                            <span class="text-danger">Complet</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-muted">Aucun trajet disponible actuellement.</p>
        <?php endif; ?>

        <a href="historique.php" class="btn btn-outline-secondary mt-3">📜 Voir mes réservations</a>
    </div>
</body>
</html>
