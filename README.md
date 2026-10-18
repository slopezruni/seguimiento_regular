# Seguimiento Regular con MySQL

## Instalacion
1. Copia la carpeta en `C:\xampp\htdocs\seguimiento-regular-mysql`.
2. Inicia Apache y MySQL en XAMPP.
3. Abre `http://localhost/phpmyadmin`.
4. En phpMyAdmin entra a **Importar**, selecciona `database/seguimiento_regular.sql` y ejecuta.
5. Abre `http://localhost/seguimiento-regular/login.php`.

## Conexion
La conexion esta en `config/database.php`. En XAMPP normalmente se usa usuario `root` sin contrasena. Si tu configuracion es diferente, modifica `DB_USER` y `DB_PASS`.

## Ajustes encontrados
1. Agregar filtros de busqueda
2. Revisar las fechas de las fichas
3. PERMITIR MAYUS Y MINIS
4. ajustar grafico de ejecucion 
5. Solicitar el login al iniciar 
6. Poder editar las fechas de la ficha de distribución
7. Revisar los FIC, me sale que ya esta en la lista de fic pero no veo, y si estuviera tampoco me lo esta cancelando en el segumiento  
8.
