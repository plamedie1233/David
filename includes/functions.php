<?php
session_start();

// Vérifie si l’étudiant est connecté
function check_student_login() {
    if (!isset($_SESSION['etudiant_id'])) {
        header("Location: index.php");
        exit();
    }
}

// Vérifie si l’admin est connecté
function check_admin_login() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Affiche une alerte bootstrap
function alert($type, $message) {
    return "<div class='alert alert-$type'>$message</div>";
}
?>
