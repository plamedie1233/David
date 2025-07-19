<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
check_admin_login();

$message = '';
$edit_mode = false;
$trajet = ['id' => '', 'nom_trajet' => '', 'date_depart' => '', 'heure_depart' => '', 'capacite' => ''];

// Ajouter ou modifier un trajet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $nom_trajet = $conn->real_escape_string($_POST['nom_trajet']);
    $date_depart = $_POST['date_depart'];
    $heure_depart = $_POST['heure_depart'];
    $capacite = intval($_POST['capacite']);

    if ($id > 0) {
        // Modifier
        $sql = "UPDATE trajets SET nom_trajet='$nom_trajet', date_depart='$date_depart', heure_depart='$heure_depart', capacite=$capacite WHERE id=$id";
        if ($conn->query($sql)) {
            $message = alert('success', "Trajet modifié avec succès.");
        } else {
            $message = alert('danger', "Erreur lors de la modification.");
        }
    } else {
        // Ajouter
        $sql = "INSERT INTO trajets (nom_trajet, date_depart, heure_depart, capacite) VALUES ('$nom_trajet', '$date_depart', '$heure_depart', $capacite)";
        if ($conn->query($sql)) {
            $message = alert('success', "Trajet ajouté avec succès.");
        } else {
            $message = alert('danger', "Erreur lors de l'ajout.");
        }
    }
}

// Supprimer un trajet
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $conn->query("DELETE FROM trajets WHERE id = $del_id");
    header("Location: trajets.php");
    exit();
}

// Passer en mode édition
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM trajets WHERE id = $edit_id");
    if ($res->num_rows === 1) {
        $trajet = $res->fetch_assoc();
        $edit_mode = true;
    }
}

// Liste des trajets
$trajets = $conn->query("SELECT * FROM trajets ORDER BY date_depart DESC, heure_depart DESC");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des trajets - Admin UCB</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h3>🚌 Gestion des trajets</h3>
        <?php if ($message) echo $message; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($trajet['id']); ?>">
                    <div class="col-md-4">
                        <label for="nom_trajet" class="form-label">Nom du trajet</label>
                        <input type="text" name="nom_trajet" class="form-control" required value="<?php echo htmlspecialchars($trajet['nom_trajet']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_depart" class="form-label">Date de départ</label>
                        <input type="date" name="date_depart" class="form-control" required value="<?php echo htmlspecialchars($trajet['date_depart']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="heure_depart" class="form-label">Heure de départ</label>
                        <input type="time" name="heure_depart" class="form-control" required value="<?php echo htmlspecialchars($trajet['heure_depart']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="capacite" class="form-label">Capacité</label>
                        <input type="number" name="capacite" class="form-control" required min="1" value="<?php echo htmlspecialchars($trajet['capacite']); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-<?php echo $edit_mode ? 'warning' : 'primary'; ?>">
                            <?php echo $edit_mode ? 'Modifier' : 'Ajouter'; ?> trajet
                        </button>
                        <?php if ($edit_mode): ?>
                            <a href="trajets.php" class="btn btn-secondary">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <h5>Liste des trajets</h5>
        <?php if ($trajets->num_rows > 0): ?>
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Nom du trajet</th>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Capacité</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($t = $trajets->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['nom_trajet']); ?></td>
                            <td><?php echo $t['date_depart']; ?></td>
                            <td><?php echo substr($t['heure_depart'], 0, 5); ?></td>
                            <td><?php echo $t['capacite']; ?></td>
                            <td>
                                <a href="trajets.php?edit=<?php echo $t['id']; ?>" class="btn btn-sm btn-warning">Modifier</a>
                                <a href="trajets.php?delete=<?php echo $t['id']; ?>" onclick="return confirm('Supprimer ce trajet ?');" class="btn btn-sm btn-danger">Supprimer</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucun trajet trouvé.</p>
        <?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary mt-3">⬅️ Retour au dashboard</a>
    </div>
</body>
</html>
