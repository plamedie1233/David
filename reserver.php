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

// Récupérer les trajets disponibles avec correction de la requête
try {
    $trajets_query = "
        SELECT t.*, 
               (t.capacite - COALESCE(COUNT(r.id), 0)) as places_restantes,
               COALESCE(COUNT(r.id), 0) as reservations_count
        FROM trajets t 
        LEFT JOIN reservations r ON t.id = r.trajet_id AND r.statut IN ('reserve', 'valide')
        WHERE t.date_depart >= CURDATE() AND t.statut = 'actif'
        GROUP BY t.id, t.nom_trajet, t.point_depart, t.point_arrivee, t.date_depart, t.heure_depart, t.capacite, t.prix, t.description, t.statut, t.date_creation
        HAVING places_restantes > 0
        ORDER BY t.date_depart ASC, t.heure_depart ASC
    ";
    
    $trajets_result = $conn->query($trajets_query);
    
    if (!$trajets_result) {
        throw new Exception("Erreur lors de la récupération des trajets : " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Erreur requête trajets : " . $e->getMessage());
    $trajets_result = false;
    $message = alert('warning', 'Erreur lors du chargement des trajets disponibles.');
}
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
    <style>
        .trajet-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .trajet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-color: #0d6efd;
        }
        .trajet-card.selected {
            border-color: #0d6efd;
            background-color: #f8f9ff;
        }
        .price-badge {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .loading-spinner {
            display: none;
        }
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .step.active {
            background: #0d6efd;
            color: white;
        }
        .step.completed {
            background: #198754;
            color: white;
        }
        .step-line {
            width: 50px;
            height: 2px;
            background: #e9ecef;
            margin-top: 19px;
        }
        .step-line.completed {
            background: #198754;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-bus-front me-2"></i>UCB Transport
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house me-1"></i>Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reserver.php">
                            <i class="bi bi-plus-circle me-1"></i>Réserver
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="historique.php">
                            <i class="bi bi-clock-history me-1"></i>Mes réservations
                        </a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo safe_output($_SESSION['etudiant_prenom'] . ' ' . $_SESSION['etudiant_nom']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profil.php">
                                <i class="bi bi-person me-2"></i>Mon profil
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4" id="app">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- En-tête -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body text-center py-4">
                        <h2 class="mb-2">
                            <i class="bi bi-plus-circle text-primary me-2"></i>
                            Nouvelle réservation
                        </h2>
                        <p class="text-muted mb-0">Sélectionnez votre trajet et confirmez votre réservation</p>
                    </div>
                </div>

                <!-- Indicateur d'étapes -->
                <div class="step-indicator">
                    <div class="step active" id="step1">1</div>
                    <div class="step-line" id="line1"></div>
                    <div class="step" id="step2">2</div>
                    <div class="step-line" id="line2"></div>
                    <div class="step" id="step3">3</div>
                </div>

                <?php if ($message) echo $message; ?>

                <!-- Étape 1: Sélection du trajet -->
                <div class="form-step active" id="step-1">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white py-3">
                            <h4 class="mb-0">
                                <i class="bi bi-geo-alt me-2"></i>
                                Étape 1: Choisissez votre trajet
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($trajets_result && $trajets_result->num_rows > 0): ?>
                                <div class="row" id="trajets-container">
                                    <?php while ($trajet = $trajets_result->fetch_assoc()): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card trajet-card h-100 <?php echo ($trajet['id'] == $trajet_preselected) ? 'selected' : ''; ?>" 
                                                 data-trajet='<?php echo json_encode($trajet); ?>'
                                                 onclick="selectTrajet(this, <?php echo $trajet['id']; ?>)">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <h5 class="card-title text-primary mb-0">
                                                            <?php echo safe_output($trajet['nom_trajet']); ?>
                                                        </h5>
                                                        <span class="badge bg-primary price-badge">
                                                            <?php echo number_format($trajet['prix'], 0, ',', ' '); ?> FC
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="bi bi-geo-alt text-success me-2"></i>
                                                            <small class="text-muted">Départ:</small>
                                                        </div>
                                                        <p class="mb-2 fw-bold"><?php echo safe_output($trajet['point_depart']); ?></p>
                                                        
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                                                            <small class="text-muted">Arrivée:</small>
                                                        </div>
                                                        <p class="mb-3 fw-bold"><?php echo safe_output($trajet['point_arrivee']); ?></p>
                                                    </div>
                                                    
                                                    <div class="row text-center">
                                                        <div class="col-6">
                                                            <div class="border-end">
                                                                <i class="bi bi-calendar text-primary d-block mb-1"></i>
                                                                <small class="text-muted d-block">Date</small>
                                                                <strong><?php echo format_date_fr($trajet['date_depart']); ?></strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <i class="bi bi-clock text-primary d-block mb-1"></i>
                                                            <small class="text-muted d-block">Heure</small>
                                                            <strong><?php echo substr($trajet['heure_depart'], 0, 5); ?></strong>
                                                        </div>
                                                    </div>
                                                    
                                                    <hr>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-people me-1"></i>
                                                            <?php echo $trajet['places_restantes']; ?> places
                                                        </span>
                                                        <small class="text-muted">
                                                            <?php echo $trajet['reservations_count']; ?> réservé(s)
                                                        </small>
                                                    </div>
                                                    
                                                    <?php if ($trajet['description']): ?>
                                                        <div class="mt-3">
                                                            <small class="text-muted">
                                                                <i class="bi bi-info-circle me-1"></i>
                                                                <?php echo safe_output($trajet['description']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="button" class="btn btn-primary btn-lg" id="nextStep1" disabled onclick="nextStep(2)">
                                        <i class="bi bi-arrow-right me-2"></i>Continuer
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-calendar-x text-muted" style="font-size: 4rem;"></i>
                                    <h4 class="mt-3 text-muted">Aucun trajet disponible</h4>
                                    <p class="text-muted">Les nouveaux trajets seront affichés ici dès qu'ils seront programmés.</p>
                                    <a href="dashboard.php" class="btn btn-primary">
                                        <i class="bi bi-arrow-left me-2"></i>Retour au tableau de bord
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Étape 2: Confirmation des détails -->
                <div class="form-step" id="step-2">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-success text-white py-3">
                            <h4 class="mb-0">
                                <i class="bi bi-check-circle me-2"></i>
                                Étape 2: Vérifiez les détails
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            <div id="trajet-details" class="mb-4">
                                <!-- Les détails seront remplis par JavaScript -->
                            </div>
                            
                            <div class="alert alert-info">
                                <h6 class="alert-heading">
                                    <i class="bi bi-info-circle me-2"></i>Informations importantes
                                </h6>
                                <ul class="mb-0">
                                    <li>Présentez-vous au point de départ <strong>15 minutes avant l'heure</strong></li>
                                    <li>Munissez-vous de votre <strong>carte d'étudiant</strong></li>
                                    <li>Votre QR code sera généré après confirmation</li>
                                    <li>Vous pouvez annuler jusqu'à <strong>2 heures avant le départ</strong></li>
                                </ul>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary btn-lg" onclick="previousStep(1)">
                                    <i class="bi bi-arrow-left me-2"></i>Retour
                                </button>
                                <button type="button" class="btn btn-success btn-lg" onclick="nextStep(3)">
                                    <i class="bi bi-arrow-right me-2"></i>Confirmer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Étape 3: Conditions et validation finale -->
                <div class="form-step" id="step-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark py-3">
                            <h4 class="mb-0">
                                <i class="bi bi-shield-check me-2"></i>
                                Étape 3: Conditions et validation
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" id="reservationForm">
                                <input type="hidden" name="trajet_id" id="selected_trajet_id">
                                
                                <div class="card bg-light mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title">Conditions d'utilisation</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="text-primary">1. Réservation</h6>
                                                <p class="small">Chaque étudiant ne peut réserver qu'une seule place par trajet. Les réservations sont confirmées dans l'ordre d'arrivée.</p>
                                                
                                                <h6 class="text-primary">2. Annulation</h6>
                                                <p class="small">Les réservations peuvent être annulées jusqu'à 2 heures avant le départ du trajet.</p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-primary">3. Validation</h6>
                                                <p class="small">Vous devez présenter votre QR code et votre carte d'étudiant lors de l'embarquement.</p>
                                                
                                                <h6 class="text-primary">4. Responsabilité</h6>
                                                <p class="small">L'université n'est pas responsable des retards ou annulations dus à des circonstances exceptionnelles.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="acceptConditions" required>
                                    <label class="form-check-label fw-bold" for="acceptConditions">
                                        J'accepte les conditions d'utilisation et confirme que les informations fournies sont exactes.
                                    </label>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-outline-secondary btn-lg" onclick="previousStep(2)">
                                        <i class="bi bi-arrow-left me-2"></i>Retour
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-lg" id="submitReservation">
                                        <span class="loading-spinner spinner-border spinner-border-sm me-2" role="status"></span>
                                        <i class="bi bi-check-circle me-2"></i>Confirmer la réservation
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedTrajetData = null;
        let currentStep = 1;

        // Sélectionner un trajet
        function selectTrajet(card, trajetId) {
            // Retirer la sélection précédente
            document.querySelectorAll('.trajet-card').forEach(c => c.classList.remove('selected'));
            
            // Sélectionner le nouveau trajet
            card.classList.add('selected');
            selectedTrajetData = JSON.parse(card.dataset.trajet);
            
            // Activer le bouton suivant
            document.getElementById('nextStep1').disabled = false;
            
            // Mettre à jour l'ID du trajet sélectionné
            document.getElementById('selected_trajet_id').value = trajetId;
        }

        // Passer à l'étape suivante
        function nextStep(step) {
            if (step === 2 && selectedTrajetData) {
                showTrajetDetails();
            }
            
            // Masquer l'étape actuelle
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}`).classList.add('completed');
            
            if (currentStep < step) {
                document.getElementById(`line${currentStep}`).classList.add('completed');
            }
            
            // Afficher la nouvelle étape
            currentStep = step;
            document.getElementById(`step-${currentStep}`).classList.add('active');
            document.getElementById(`step${currentStep}`).classList.add('active');
            
            // Scroll vers le haut
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Revenir à l'étape précédente
        function previousStep(step) {
            // Masquer l'étape actuelle
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}`).classList.remove('active');
            
            // Afficher l'étape précédente
            currentStep = step;
            document.getElementById(`step-${currentStep}`).classList.add('active');
            document.getElementById(`step${currentStep}`).classList.add('active');
            
            // Mettre à jour les indicateurs
            for (let i = currentStep + 1; i <= 3; i++) {
                document.getElementById(`step${i}`).classList.remove('active', 'completed');
                if (i > 1) {
                    document.getElementById(`line${i-1}`).classList.remove('completed');
                }
            }
            
            // Scroll vers le haut
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Afficher les détails du trajet sélectionné
        function showTrajetDetails() {
            if (!selectedTrajetData) return;
            
            const detailsContainer = document.getElementById('trajet-details');
            detailsContainer.innerHTML = `
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title text-primary mb-3">
                            <i class="bi bi-bus-front me-2"></i>
                            ${selectedTrajetData.nom_trajet}
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong><i class="bi bi-geo-alt text-success me-2"></i>Départ:</strong>
                                    <p class="mb-1">${selectedTrajetData.point_depart}</p>
                                </div>
                                <div class="mb-3">
                                    <strong><i class="bi bi-geo-alt-fill text-danger me-2"></i>Arrivée:</strong>
                                    <p class="mb-1">${selectedTrajetData.point_arrivee}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong><i class="bi bi-calendar text-primary me-2"></i>Date:</strong>
                                    <p class="mb-1">${formatDate(selectedTrajetData.date_depart)}</p>
                                </div>
                                <div class="mb-3">
                                    <strong><i class="bi bi-clock text-primary me-2"></i>Heure:</strong>
                                    <p class="mb-1">${selectedTrajetData.heure_depart.substring(0, 5)}</p>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong><i class="bi bi-currency-dollar text-success me-2"></i>Prix:</strong>
                                    <span class="badge bg-success fs-6">${parseInt(selectedTrajetData.prix).toLocaleString()} FC</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong><i class="bi bi-people text-info me-2"></i>Places restantes:</strong>
                                    <span class="badge bg-info fs-6">${selectedTrajetData.places_restantes}</span>
                                </div>
                            </div>
                        </div>
                        ${selectedTrajetData.description ? `
                            <div class="mt-3">
                                <strong><i class="bi bi-info-circle text-warning me-2"></i>Description:</strong>
                                <p class="text-muted mb-0">${selectedTrajetData.description}</p>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        // Formater la date
        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                weekday: 'long'
            };
            return date.toLocaleDateString('fr-FR', options);
        }

        // Gestion de la soumission du formulaire
        document.getElementById('reservationForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitReservation');
            const spinner = submitBtn.querySelector('.loading-spinner');
            
            // Afficher le spinner
            spinner.style.display = 'inline-block';
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement en cours...';
        });

        // Pré-sélectionner un trajet si spécifié dans l'URL
        <?php if ($trajet_preselected > 0): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const preselectedCard = document.querySelector(`[onclick*="${<?php echo $trajet_preselected; ?>}"]`);
                if (preselectedCard) {
                    selectTrajet(preselectedCard, <?php echo $trajet_preselected; ?>);
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>