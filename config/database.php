<?php
$host = '127.0.0.1';
$port = '3307';      // Aquí especificamos el puerto 3307 que tienes en XAMPP
$dbname = 'seguimiento_regular';
$username = 'root';
$password = '';      // Sin contraseña

try {
    // Agregamos el parámetro de puerto en la cadena de conexión DSN
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>