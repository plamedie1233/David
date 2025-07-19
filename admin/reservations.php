<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
check_admin_login();

$reservations = $conn->query("
    SELECT r.id, r.date_reservation, r.statut,
           e.nom AS etu_nom, e.prenom AS etu_prenom, e.matricule,
           t.nom_trajet, t.date_depart, t.heure_depart
    FROM reservations r
    JOIN etudiants e ON r.etudiant_id = e.id
    JOIN trajets t ON r.trajet_id = t.id
    ORDER BY t.date_depart DESC, t.heure_depart DESC, r.date_reservation DESC
");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réservations - Admin UCB Transport</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h3>🎫 Liste des Réservations</h3>

        <?php if ($reservations->num_rows > 0): ?>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>Étudiant</th>
                    <th>Matricule</th>
                    <th>Trajet</th>
                    <th>Date départ</th>
                    <th>Heure départ</th>
                    <th>Date réservation</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = $reservations->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['etu_nom'] . ' ' . $r['etu_prenom']); ?></td>
                    <td><?php echo htmlspecialchars($r['matricule']); ?></td>
                    <td><?php echo htmlspecialchars($r['nom_trajet']); ?></td>
                    <td><?php echo $r['date_depart']; ?></td>
                    <td><?php echo substr($r['heure_depart'], 0, 5); ?></td>
                    <td><?php echo $r['date_reservation']; ?></td>
                    <td>
                        <?php if ($r['statut'] === 'réservé'): ?>
                            <span class="badge bg-success">Réservé</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Annulé</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>Aucune réservation pour le moment.</p>
        <?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary mt-3">⬅️ Retour au dashboard</a>
    </div>
</body>
</html>
