<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
check_admin_login();

$message = '';

if (isset($_GET['code'])) {
    $code = $_GET['code']; // Format attendu : UCB|RESERVATION|<id>|<etudiant_id>|<trajet_id>
    $parts = explode('|', $code);

    if (count($parts) === 5 && $parts[0] === 'UCB' && $parts[1] === 'RESERVATION') {
        $res_id = intval($parts[2]);
        $etudiant_id = intval($parts[3]);
        $trajet_id = intval($parts[4]);

        // Vérifier réservation valide
        $res = $conn->query("SELECT * FROM reservations WHERE id = $res_id AND etudiant_id = $etudiant_id AND trajet_id = $trajet_id AND statut = 'réservé'");

        if ($res->num_rows === 1) {
            $message = '<div class="alert alert-success">✅ Réservation valide. Accès autorisé.</div>';
            // Optionnel : Marquer la réservation comme « utilisée »
            // $conn->query("UPDATE reservations SET statut = 'utilisé' WHERE id = $res_id");
        } else {
            $message = '<div class="alert alert-danger">❌ Réservation invalide ou annulée.</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">⚠️ Code QR invalide.</div>';
    }
} else {
    $message = '<div class="alert alert-info">Veuillez scanner un QR code pour valider la réservation.</div>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Validation QR Code - Admin UCB</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" />
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h3>🔍 Validation de réservation par QR Code</h3>
        <form method="GET" class="mb-3">
            <label for="code" class="form-label">Entrez le contenu du QR Code :</label>
            <input type="text" name="code" id="code" class="form-control" placeholder="UCB|RESERVATION|123|456|789" required />
            <button type="submit" class="btn btn-primary mt-2">Valider</button>
        </form>

        <?php echo $message; ?>

        <a href="dashboard.php" class="btn btn-secondary mt-3">⬅️ Retour au dashboard</a>
    </div>
</body>
</html>
