<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/phpqrcode/qrlib.php';

check_student_login();

$etudiant_id = $_SESSION['etudiant_id'];
$message = '';
$trajet_preselected = isset($_GET['trajet_id']) ? intval($_GET['trajet_id']) : 0;

// Traitement de la réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trajet_id = intval($_POST['trajet_id']);
    
    try {
        // Vérifications
        if (has_existing_reservation($etudiant_id, $trajet_id)) {
            $message = alert('warning', 'Vous avez déjà réservé ce trajet.');
        } elseif (check_available_seats($trajet_id) <= 0) {
            $message = alert('danger', 'Désolé, ce trajet est complet.');
        } else {
            // Générer le numéro de billet
            $numero_billet = generate_ticket_number($trajet_id, $etudiant_id);
            
            // Insérer la réservation
            $stmt = execute_query(
                "INSERT INTO reservations (etudiant_id, trajet_id, numero_billet, date_reservation, statut) 
                 VALUES (?, ?, ?, NOW(), 'reserve')",
                [$etudiant_id, $trajet_id, $numero_billet],
                'iis'
            );
            
            $reservation_id = $conn->insert_id;
            
            // Générer le QR code
            $qr_dir = __DIR__ . '/qr/';
            if (!is_dir($qr_dir)) {
                mkdir($qr_dir, 0755, true);
            }
            
            $qr_text = "UCB|RESERVATION|{$reservation_id}|{$etudiant_id}|{$trajet_id}|{$numero_billet}";
            $qr_file = $qr_dir . 'res_' . $reservation_id . '.png';
            QRcode::png($qr_text, $qr_file, QR_ECLEVEL_L, 6);
            
            // Mettre à jour le chemin du QR code
            execute_query(
                "UPDATE reservations SET qr_code_path = ? WHERE id = ?",
                ['qr/res_' . $reservation_id . '.png', $reservation_id],
                'si'
            );
            
            log_action('RESERVATION_CREATED', 'ETUDIANT', $etudiant_id, "Trajet ID: {$trajet_id}, Billet: {$numero_billet}");
            
            // Redirection vers la page de confirmation
            header("Location: billet.php?id={$reservation_id}");
            exit();
        }
    } catch (Exception $e) {
        error_log("Erreur réservation : " . $e->getMessage());
        $message = alert('danger', 'Erreur lors de la réservation. Veuillez réessayer.');
    }
}

// Récupérer les trajets disponibles
$trajets_query = "
    SELECT t.*, 
           (t.capacite - COALESCE(COUNT(r.id), 0)) as places_restantes
    FROM trajets t 
    LEFT JOIN reservations r ON t.id = r.trajet_id AND r.statut IN ('reserve', 'valide')
    WHERE t.date_depart >= CURDATE() AND t.statut = 'actif'
    GROUP BY t.id 
    HAVING places_restantes > 0
    ORDER BY t.date_depart ASC, t.heure_depart ASC
";

$trajets_result = $conn->query($trajets_query);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réserver un trajet - UCB Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-bus-front me-2"></i>UCB Transport
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-arrow-left me-1"></i>Retour au tableau de bord
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4" id="app">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-lg">
                    <div class="card-header bg-primary text-white py-3">
                        <h3 class="mb-0">
                            <i class="bi bi-plus-circle me-2"></i>
                            Nouvelle réservation
                        </h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($message) echo $message; ?>

                        <form method="POST" @submit="validateForm" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label for="trajet_id" class="form-label fw-bold">
                                    <i class="bi bi-geo-alt me-2"></i>Sélectionner un trajet
                                </label>
                                <select name="trajet_id" 
                                        id="trajet_id" 
                                        class="form-select form-select-lg" 
                                        v-model="selectedTrajet"
                                        @change="updateTrajetDetails"
                                        required>
                                    <option value="">-- Choisissez votre trajet --</option>
                                    <?php if ($trajets_result && $trajets_result->num_rows > 0): ?>
                                        <?php while ($trajet = $trajets_result->fetch_assoc()): ?>
                                            <option value="<?php echo $trajet['id']; ?>" 
                                                    <?php echo ($trajet['id'] == $trajet_preselected) ? 'selected' : ''; ?>
                                                    data-trajet='<?php echo json_encode($trajet); ?>'>
                                                <?php echo safe_output($trajet['nom_trajet']); ?> - 
                                                <?php echo format_date_fr($trajet['date_depart']); ?> à 
                                                <?php echo substr($trajet['heure_depart'], 0, 5); ?>
                                                (<?php echo $trajet['places_restantes']; ?> places)
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner un trajet.
                                </div>
                            </div>

                            <!-- Détails du trajet sélectionné -->
                            <div v-if="trajetDetails" class="mb-4">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="bi bi-info-circle me-2"></i>Détails du trajet
                                        </h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-2">
                                                    <strong>Départ :</strong> {{ trajetDetails.point_depart }}
                                                </p>
                                                <p class="mb-2">
                                                    <strong>Arrivée :</strong> {{ trajetDetails.point_arrivee }}
                                                </p>
                                                <p class="mb-2">
                                                    <strong>Date :</strong> {{ formatDate(trajetDetails.date_depart) }}
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-2">
                                                    <strong>Heure :</strong> {{ trajetDetails.heure_depart.substring(0, 5) }}
                                                </p>
                                                <p class="mb-2">
                                                    <strong>Prix :</strong> {{ formatPrice(trajetDetails.prix) }} FC
                                                </p>
                                                <p class="mb-2">
                                                    <strong>Places restantes :</strong> 
                                                    <span class="badge bg-success">{{ trajetDetails.places_restantes }}</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div v-if="trajetDetails.description" class="mt-3">
                                            <strong>Description :</strong>
                                            <p class="text-muted mb-0">{{ trajetDetails.description }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Conditions -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="conditions" 
                                           v-model="acceptConditions"
                                           required>
                                    <label class="form-check-label" for="conditions">
                                        J'accepte les <a href="#" data-bs-toggle="modal" data-bs-target="#conditionsModal">conditions d'utilisation</a> 
                                        et confirme que les informations fournies sont exactes.
                                    </label>
                                    <div class="invalid-feedback">
                                        Vous devez accepter les conditions d'utilisation.
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" 
                                        class="btn btn-primary btn-lg"
                                        :disabled="!canSubmit">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Confirmer la réservation
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal des conditions -->
    <div class="modal fade" id="conditionsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conditions d'utilisation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Réservation</h6>
                    <p>Chaque étudiant ne peut réserver qu'une seule place par trajet. Les réservations sont confirmées dans l'ordre d'arrivée.</p>
                    
                    <h6>2. Annulation</h6>
                    <p>Les réservations peuvent être annulées jusqu'à 2 heures avant le départ du trajet.</p>
                    
                    <h6>3. Validation</h6>
                    <p>Vous devez présenter votre QR code et votre carte d'étudiant lors de l'embarquement.</p>
                    
                    <h6>4. Responsabilité</h6>
                    <p>L'université n'est pas responsable des retards ou annulations dus à des circonstances exceptionnelles.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">J'ai compris</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="assets/js/reservation.js"></script>
</body>
</html>