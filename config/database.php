<?php
// config/database.php
$host = 'localhost';
$dbname = 'sistema_distal';
$username = 'root';
$password = ''; // Ajustar si es necesario

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>
