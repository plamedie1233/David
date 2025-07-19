<?php
$host = "localhost";
$user = "root";
$pass = "1234";
$dbname = "ucb_transport";

// Connexion MySQL
$conn = new mysqli($host, $user, $pass, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}
?>
