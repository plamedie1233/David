<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
check_user_login();

if (!isset($_GET['id'])) {
    die("Billet invalide.");
}

$res_id = intval($_GET['id']);
$etudiant_id = $_SESSION['etudiant_id'];

$res = $conn->query("
    SELECT r.*, t.nom_trajet, t.date_depart, t.heure_depart 
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    WHERE r.id = $res_id AND r.etudiant_id = $etudiant_id
");

if ($res->num_rows !== 1) {
    die("Aucune réservation trouvée.");
}

$row = $res->fetch_assoc();
$qr_file = "qr/res_" . $row['id'] . ".png";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Billet de réservation</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body class="container mt-5">
    <div class="card p-4 shadow">
        <h2>🎫 Billet de Réservation - UCB Transport</h2>
        <p><strong>Nom du trajet :</strong> <?= htmlspecialchars($row['nom_trajet']) ?></p>
        <p><strong>Date départ :</strong> <?= $row['date_depart'] ?> à <?= substr($row['heure_depart'], 0, 5) ?></p>
        <p><strong>Date réservation :</strong> <?= $row['date_reservation'] ?></p>
        <p><strong>Statut :</strong> <?= ucfirst($row['statut']) ?></p>
        <hr>
        <h5>🎟️ QR Code de validation</h5>
        <img src="<?= $qr_file ?>" width="180" alt="QR Code de réservation">
        <hr>
        <a href="dashboard.php" class="btn btn-secondary">⬅ Retour</a>
        <button onclick="window.print()" class="btn btn-primary float-end">🖨️ Imprimer</button>
    </div>
</body>
</html>
            