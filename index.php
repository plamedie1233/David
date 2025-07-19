<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $matricule = mysqli_real_escape_string($conn, $_POST['matricule']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM etudiants WHERE matricule = '$matricule'";
    $result = $conn->query($sql);

    if ($result->num_rows === 1) {
        $etudiant = $result->fetch_assoc();
        if (password_verify($password, $etudiant['password'])) {
            $_SESSION['etudiant_id'] = $etudiant['id'];
            $_SESSION['etudiant_nom'] = $etudiant['nom'];
            header("Location: dashboard.php");
            exit();
        } else {
            $message = alert("danger", "Mot de passe incorrect.");
        }
    } else {
        $message = alert("danger", "Matricule non trouvé.");
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion Étudiant - UCB Transport</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <h3 class="text-center mb-4">🎓 Connexion Étudiant</h3>
                <?php if ($message) echo $message; ?>
                <div class="card shadow">
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="matricule" class="form-label">Matricule</label>
                                <input type="text" name="matricule" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                        </form>
                    </div>
                </div>
                <p class="mt-3 text-center"><small>© UCB Transport - 2025</small></p>
            </div>
        </div>
    </div>
</body>
</html>
