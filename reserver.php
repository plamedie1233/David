<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/phpqrcode.php'; // Lib QR

check_user_login();

$etudiant_id = $_SESSION['etudiant_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trajet_id'])) {
    $trajet_id = intval($_POST['trajet_id']);

    // Vérifier si une réservation existe déjà
    $check = $conn->query("SELECT * FROM reservations WHERE etudiant_id = $etudiant_id AND trajet_id = $trajet_id AND statut = 'réservé'");
    if ($check->num_rows > 0) {
        $message = "⚠️ Vous avez déjà réservé ce trajet.";
    } else {
        // Insertion
        $stmt = $conn->prepare("INSERT INTO reservations (etudiant_id, trajet_id, date_reservation, statut) VALUES (?, ?, NOW(), 'réservé')");
        $stmt->bind_param("ii", $etudiant_id, $trajet_id);
        $stmt->execute();

        $reservation_id = $conn->insert_id;

        // Génération QR
        $qr_text = "UCB|RESERVATION|$reservation_id|$etudiant_id|$trajet_id";
        $qr_dir = __DIR__ . '/qr/';
        if (!is_dir($qr_dir)) mkdir($qr_dir, 0755, true);
        $qr_file = $qr_dir . 'res_' . $reservation_id . '.png';
        QRcode::png($qr_text, $qr_file, QR_ECLEVEL_L, 4);

        // Rediriger vers le billet
        header("Location: billet.php?id=$reservation_id");
        exit();
    }
}
?>

<!-- Affichage HTML du formulaire -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réserver un trajet</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body class="container mt-5">
    <h2>📅 Réservation de place</h2>
    <?php if ($message): ?>
        <div class="alert alert-warning"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="trajet_id" class="form-label">Choisissez un trajet :</label>
        <select name="trajet_id" id="trajet_id" class="form-select" required>
            <option value="">-- Sélectionner --</option>
            <?php
            $res = $conn->query("SELECT * FROM trajets ORDER BY date_depart ASC");
            while ($row = $res->fetch_assoc()):
            ?>
                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nom_trajet']) ?> - <?= $row['date_depart'] ?> à <?= substr($row['heure_depart'], 0, 5) ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-success mt-3">Réserver</button>
    </form>
</body>
</html>
