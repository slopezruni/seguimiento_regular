<?php
session_start();
echo "<h1>¡Bienvenido al sistema, " . ($_SESSION['nombre'] ?? 'Usuario') . "!</h1>";
echo "<p>La sesión está funcionando correctamente.</p>";
echo "<a href='../../login.php'>Cerrar sesión / Volver</a>";
?>