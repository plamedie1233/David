<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/phpqrcode/qrlib.php'; // Inclure la lib PHP QR Code

check_student_login();

$etudiant_id = $_SESSION['etudiant_id'];
$etudiant_nom = $_SESSION['etudiant_nom'];

// Récupérer les réservations de l’étudiant
$reservations = $conn->query("
    SELECT r.*, t.nom_trajet, t.date_depart, t.heure_depart
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    WHERE r.etudiant_id = $etudiant_id
    ORDER BY t.date_depart DESC, t.heure_depart DESC
");

// Créer le dossier QR s’il n’existe pas
$qr_dir = __DIR__ . '/qr/';
if (!is_dir($qr_dir)) {
    mkdir($qr_dir, 0755, true);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Mes Réservations - UCB Transport</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h3>📜 Historique de vos Réservations</h3>
        <p>Étudiant : <strong><?= htmlspecialchars($etudiant_nom) ?></strong></p>

        <?php if ($reservations->num_rows > 0): ?>
            <table class="table table-bordered table-striped mt-3">
                <thead class="table-light">
                    <tr>
                        <th>Trajet</th>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Date de réservation</th>
                        <th>Statut</th>
                        <th>QR Code</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $reservations->fetch_assoc()):
                    $qr_file = $qr_dir . 'res_' . $row['id'] . '.png';
                    $qr_text = "UCB|RESERVATION|{$row['id']}|{$etudiant_id}|{$row['trajet_id']}";
                    if (!file_exists($qr_file)) {
                        QRcode::png($qr_text, $qr_file, QR_ECLEVEL_L, 4);
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nom_trajet']) ?></td>
                        <td><?= $row['date_depart'] ?></td>
                        <td><?= substr($row['heure_depart'], 0, 5) ?></td>
                        <td><?= $row['date_reservation'] ?></td>
                        <td>
                            <?php if ($row['statut'] === 'réservé'): ?>
                                <span class="badge bg-success">Réservé</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Annulé</span>
                            <?php endif; ?>
                        </td>
                        <td><img src="qr/res_<?= $row['id'] ?>.png" alt="QR Code" width="80" /></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">Aucune réservation effectuée.</p>
        <?php endif; ?>

        <a href="dashboard.php" class="btn btn-primary mt-3">⬅️ Retour au tableau de bord</a>
    </div>
</body>
</html>
