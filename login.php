<?php
session_start();
require_once 'config/database.php';

// Si ya inició sesión, redirigir al panel principal
if (isset($_SESSION['user_id'])) {
    header('Location: /seguimiento-regular/index.php');
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = strtolower(trim($_POST['login_input'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!empty($login_input) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo_sena = ? OR usuario = ? LIMIT 1");
        $stmt->execute([$login_input, $login_input]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && (password_verify($password, $usuario['password']) || $password === $usuario['password'])) {
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre_completo']; 
            $_SESSION['email'] = $usuario['correo_sena'];
            $_SESSION['rol'] = $usuario['rol'];
            
            if ($usuario['rol'] === 'admin') {
                $_SESSION['apoyo_usuario'] = null;
                $_SESSION['apoyo_activo_id'] = 1;
            } else {
                $_SESSION['apoyo_usuario'] = (int)($usuario['apoyo_id'] ?? 1);
                $_SESSION['apoyo_activo_id'] = (int)($usuario['apoyo_id'] ?? 1);
            }

            if ($password === $usuario['password']) {
                $nuevo_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtHash = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmtHash->execute([$nuevo_hash, $usuario['id']]);
            }

            header('Location: /seguimiento-regular/index.php');
            exit;
        } else {
            $error = "Credenciales incorrectas. Verifica tu usuario/correo y contraseña.";
        }
    } else {
        $error = "Por favor ingresa tu usuario y contraseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Apoyos Socioeconómicos SENA</title>
    <link rel="icon" type="image/webp" href="/seguimiento-regular/assets/img/logo_sena.webp">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top left, #00324D 0%, #001926 55%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-wrapper {
            max-width: 900px;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            background: #ffffff;
        }
        .brand-side {
            background: linear-gradient(145deg, #39A900 0%, #00324D 100%);
            color: #ffffff;
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }
        .form-side {
            padding: 3rem 2.8rem;
        }
        .form-control {
            padding: 0.65rem 0.9rem;
            border-radius: 10px;
        }
        .form-control:focus {
            border-color: #39A900;
            box-shadow: 0 0 0 0.2rem rgba(57, 169, 0, 0.2);
        }
        .btn-sena {
            background-color: #39A900;
            border-color: #39A900;
            color: #fff;
            border-radius: 10px;
            padding: 0.7rem;
            transition: all 0.2s;
        }
        .btn-sena:hover {
            background-color: #2e8800;
            color: #fff;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="login-wrapper row g-0">
    <!-- Panel Institucional Izquierdo -->
    <div class="col-lg-5 brand-side d-none d-lg-flex">
        <div>
            <div class="bg-white rounded-3 p-2 d-inline-flex align-items-center justify-content-center mb-4 shadow-sm">
                <img src="/seguimiento-regular/assets/img/logo_sena.webp" alt="SENA" style="width: 48px; height: auto;">
            </div>
            <h3 class="fw-extrabold mb-2">Sistema de Apoyos Socioeconómicos</h3>
            <p class="text-white-50 small mb-0">
                Plataforma centralizada para el control presupuestal, seguimiento académico, gestión de Ciberbanco y emisión de planillas de pago.
            </p>
        </div>

        <div class="border-top border-light border-opacity-25 pt-3 small">
            <div class="fw-bold"><i class="bi bi-geo-alt-fill me-1"></i> Regional Valle del Cauca</div>
            <div class="text-white-50" style="font-size: 0.78rem;">Centro de Electricidad y Automatización Industrial (CEAI)</div>
        </div>
    </div>

    <!-- Panel de Formulario Derecho -->
    <div class="col-lg-7 form-side">
        <div class="d-lg-none text-center mb-4">
            <img src="/seguimiento-regular/assets/img/logo_sena.webp" alt="SENA" style="width: 50px; height: auto;" class="mb-2">
            <h5 class="fw-bold text-dark mb-0">Gestión de Apoyos SENA</h5>
        </div>

        <div class="mb-4">
            <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1 rounded-pill mb-2">Acceso Institucional</span>
            <h4 class="fw-bold text-dark mb-1">Bienvenido de nuevo</h4>
            <p class="text-muted small mb-0">Ingresa tu usuario o correo institucional para acceder a tus módulos asignados.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger border-0 shadow-sm py-2 small fw-bold d-flex align-items-center">
                <i class="bi bi-exclamation-octagon-fill fs-5 me-2"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary">Usuario o Correo SENA</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person-check text-success"></i></span>
                    <input type="text" name="login_input" class="form-control border-start-0" placeholder="Ej: slopezr o usuario@sena.edu.co" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold text-secondary">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-shield-lock text-success"></i></span>
                    <input type="password" name="password" id="inputPassword" class="form-control border-start-0 border-end-0" placeholder="••••••••" required>
                    <button class="btn btn-light border border-start-0 text-secondary" type="button" id="togglePassword" title="Mostrar / Ocultar contraseña">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-sena w-100 fw-bold shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar a la Plataforma
            </button>
        </form>

        <div class="text-center mt-4 pt-2 border-top small text-muted" style="font-size: 0.75rem;">
            Servicio Nacional de Aprendizaje SENA &copy; <?= date('Y') ?> &bull; Bienestar al Aprendiz
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const passInput = document.getElementById('inputPassword');
    const icon = document.getElementById('eyeIcon');
    if (passInput.type === 'password') {
        passInput.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        passInput.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});
</script>
</body>
</html>