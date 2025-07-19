<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
check_admin_login();

// Filtres
$filter_statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$filter_trajet = isset($_GET['trajet']) ? intval($_GET['trajet']) : 0;
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Construction de la requête avec filtres
$where_conditions = [];
$params = [];
$types = '';

if ($filter_statut) {
    $where_conditions[] = "r.statut = ?";
    $params[] = $filter_statut;
    $types .= 's';
}

if ($filter_trajet) {
    $where_conditions[] = "r.trajet_id = ?";
    $params[] = $filter_trajet;
    $types .= 'i';
}

if ($filter_date) {
    $where_conditions[] = "DATE(r.date_reservation) = ?";
    $params[] = $filter_date;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$reservations_query = "
    SELECT r.*, 
           e.nom AS etu_nom, e.prenom AS etu_prenom, e.matricule, e.email,
           t.nom_trajet, t.point_depart, t.point_arrivee, t.date_depart, t.heure_depart, t.prix
    FROM reservations r
    JOIN etudiants e ON r.etudiant_id = e.id
    JOIN trajets t ON r.trajet_id = t.id
    {$where_clause}
    ORDER BY t.date_depart DESC, t.heure_depart DESC, r.date_reservation DESC
";

if (!empty($params)) {
    $stmt = execute_query($reservations_query, $params, $types);
    $reservations = $stmt->get_result();
} else {
    $reservations = $conn->query($reservations_query);
}

// Liste des trajets pour le filtre
$trajets_filter = $conn->query("SELECT id, nom_trajet FROM trajets ORDER BY date_depart DESC");

// Statistiques
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
$stats['reserve'] = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE statut = 'reserve'")->fetch_assoc()['count'];
$stats['valide'] = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE statut = 'valide'")->fetch_assoc()['count'];
$stats['annule'] = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE statut = 'annule'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservations - UCB Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-speedometer2 me-2"></i>Admin UCB Transport
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-arrow-left me-1"></i>Retour au dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">
                                    <i class="bi bi-ticket-perforated text-primary me-2"></i>
                                    Gestion des réservations
                                </h2>
                                <p class="text-muted mb-0">Consulter et gérer toutes les réservations</p>
                            </div>
                            <div>
                                <a href="validation.php" class="btn btn-primary">
                                    <i class="bi bi-qr-code-scan me-1"></i>Scanner QR Code
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-ticket-perforated display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                        <p class="mb-0">Total</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['reserve']; ?></h3>
                        <p class="mb-0">Réservées</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-shield-check display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['valide']; ?></h3>
                        <p class="mb-0">Validées</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-danger text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-x-circle display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['annule']; ?></h3>
                        <p class="mb-0">Annulées</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="statut" class="form-label">Statut</label>
                                <select name="statut" id="statut" class="form-select">
                                    <option value="">Tous les statuts</option>
                                    <option value="reserve" <?php echo $filter_statut === 'reserve' ? 'selected' : ''; ?>>Réservé</option>
                                    <option value="valide" <?php echo $filter_statut === 'valide' ? 'selected' : ''; ?>>Validé</option>
                                    <option value="annule" <?php echo $filter_statut === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                                    <option value="utilise" <?php echo $filter_statut === 'utilise' ? 'selected' : ''; ?>>Utilisé</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="trajet" class="form-label">Trajet</label>
                                <select name="trajet" id="trajet" class="form-select">
                                    <option value="">Tous les trajets</option>
                                    <?php while ($t = $trajets_filter->fetch_assoc()): ?>
                                        <option value="<?php echo $t['id']; ?>" <?php echo $filter_trajet === $t['id'] ? 'selected' : ''; ?>>
                                            <?php echo safe_output($t['nom_trajet']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Date de réservation</label>
                                <input type="date" name="date" id="date" class="form-control" value="<?php echo $filter_date; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-funnel me-1"></i>Filtrer
                                </button>
                                <a href="reservations.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des réservations -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-list text-primary me-2"></i>
                            Liste des réservations
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($reservations && $reservations->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-primary">
                                        <tr>
                                            <th><i class="bi bi-person me-1"></i>Étudiant</th>
                                            <th><i class="bi bi-geo-alt me-1"></i>Trajet</th>
                                            <th><i class="bi bi-calendar me-1"></i>Voyage</th>
                                            <th><i class="bi bi-ticket me-1"></i>Billet</th>
                                            <th><i class="bi bi-clock me-1"></i>Réservé le</th>
                                            <th><i class="bi bi-flag me-1"></i>Statut</th>
                                            <th><i class="bi bi-currency-dollar me-1"></i>Prix</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($r = $reservations->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo safe_output($r['etu_prenom'] . ' ' . $r['etu_nom']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="bi bi-person-badge me-1"></i>
                                                            <?php echo safe_output($r['matricule']); ?>
                                                        </small>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="bi bi-envelope me-1"></i>
                                                            <?php echo safe_output($r['email']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo safe_output($r['nom_trajet']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo safe_output($r['point_depart']); ?>
                                                            <i class="bi bi-arrow-right mx-1"></i>
                                                            <?php echo safe_output($r['point_arrivee']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo format_date_fr($r['date_depart']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="bi bi-clock me-1"></i>
                                                            <?php echo substr($r['heure_depart'], 0, 5); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?php echo safe_output($r['numero_billet']); ?></code>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y H:i', strtotime($r['date_reservation'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo get_status_badge($r['statut']); ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo number_format($r['prix'], 0, ',', ' '); ?> FC</strong>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-ticket-perforated text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 text-muted">Aucune réservation trouvée</h4>
                                <p class="text-muted">Aucune réservation ne correspond aux critères sélectionnés.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>