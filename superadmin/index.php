<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

// ✅ .env is inside superadmin folder
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

function showError(string $msg): void {
    $_SESSION['error'] = $msg;
    header("Location: index");
    exit;
}

function getRedirectPage(): string {
    // ✅ success ke baad superadmin/client_databases.php open hoga
    return 'client_databases';
}

if (isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '') showError("Please enter username");
    if ($password === '') showError("Please enter password");

    $envUser = $_ENV['SUPER_ADMIN_USER'] ?? '';
    $envHash = $_ENV['SUPER_ADMIN_PASS_HASH'] ?? '';

    if ($envUser === '' || $envHash === '') {
        showError("Super admin env credentials not configured");
    }

    // ✅ Timing-safe username compare
    $okUser = hash_equals((string)$envUser, (string)$username);

    // ✅ Secure password check (bcrypt hash)
    $okPass = password_verify($password, (string)$envHash);

    if (!$okUser || !$okPass) {
        showError("Invalid super admin credentials");
    }

    // ✅ Session set
    $_SESSION['login']        = true;
    $_SESSION['super_admin']  = true;
    $_SESSION['username']     = $username;
    $_SESSION['role']         = 'super_admin';

    $_SESSION['redirect_url'] = getRedirectPage();
    $_SESSION['success']      = "Login successful! Welcome {$username}";

    header("Location: " . $_SESSION['redirect_url']);
    exit;
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="IE=edge" http-equiv="X-UA-Compatible">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rhythm Payroll  - Super Admin Login</title>

<!-- ✅ Because file is /superadmin/index.php -->
<link href="../payroll/admin/assets/css/vendors_css.css" rel="stylesheet">
<link href="../payroll/admin/assets/css/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
<link href="../payroll/admin/assets/images/favicon.ico" rel="icon">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>

<style>
*,
*::before,
*::after {
  box-sizing: border-box;
}

#particles-js {
  position: fixed;
  inset: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #0f172a, #111827, #1e293b);
  z-index: -1;
}

body {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  font-family: "Poppins", sans-serif;
  margin: 0;
  padding: 20px;
}

.login-logo {
  width: 140px;
  display: block;
  margin: 0 auto 15px;
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0% { transform: translateY(0); }
  50% { transform: translateY(-12px); }
  100% { transform: translateY(0); }
}

.login-card {
  width: 100%;
  max-width: 420px;
  padding: 40px 35px;
  background: rgba(255,255,255,0.10);
  border: 1px solid rgba(255,255,255,0.16);
  border-radius: 20px;
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 10px 40px rgba(0,0,0,0.35);
  animation: slideUp .8s ease-out;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(50px); }
  to { opacity: 1; transform: translateY(0); }
}

.text-logo {
  font-size: 32px;
  font-weight: 700;
  letter-spacing: 1px;
  color: #ffffff;
  text-align: center;
  margin-bottom: 6px;
}

.text-logo::after {
  content: "";
  display: block;
  width: 60px;
  height: 3px;
  margin: 8px auto 0;
  background: linear-gradient(135deg,#3246d3,#1dbfc2);
  border-radius: 10px;
}

.login-title {
  text-align: center;
  font-size: 24px;
  font-weight: 700;
  background: linear-gradient(135deg,#1dbfc2,#8ab4f8);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 24px;
}

.premium-input {
  position: relative;
  margin-bottom: 18px;
}

.premium-input input {
  width: 100%;
  padding: 15px 50px 15px 18px;
  font-size: 15px;
  border-radius: 12px;
  border: 1px solid rgba(255,255,255,0.18);
  background: rgba(255,255,255,0.14);
  color: #fff;
  outline: none;
  backdrop-filter: blur(8px);
  transition: all .25s ease;
}

.premium-input input::placeholder {
  color: #dbeafe;
}

.premium-input input:focus {
  border-color: #1dbfc2;
  box-shadow: 0 0 0 3px rgba(29,191,194,.18);
  background: rgba(255,255,255,0.18);
}

.premium-input i {
  position: absolute;
  right: 18px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
  cursor: pointer;
  color: #000;
}

.premium-btn {
  width: 100%;
  min-height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 14px;
  font-size: 16px;
  font-weight: 700;
  background: linear-gradient(135deg,#3246d3,#1dbfc2);
  border: none;
  color: white;
  border-radius: 12px;
  margin-top: 10px;
  cursor: pointer;
  box-shadow: 0 6px 20px rgba(50,70,211,0.4);
  transition: .3s ease;
}

.premium-btn:hover {
  transform: translateY(-3px);
  background: linear-gradient(135deg,#1dbfc2,#3246d3);
  box-shadow: 0 10px 25px rgba(29,191,194,.45);
}

.login-footer {
  font-size: 13px;
  color: #cbd5e1;
  text-align: center;
  margin-top: 18px;
}

.footer-link {
  color: #8ab4f8;
  text-decoration: none;
}

.footer-link:hover {
  text-decoration: underline;
}

.spinner {
  display: none;
  width: 18px;
  height: 18px;
  border: 3px solid rgba(255,255,255,0.35);
  border-top: 3px solid #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0); }
  100% { transform: rotate(360deg); }
}

.premium-btn.loading {
  pointer-events: none;
  opacity: 0.9;
}

.premium-btn.loading .btn-text {
  display: none;
}

.premium-btn.loading .spinner {
  display: block;
}

@media (max-width: 480px) {
  .login-card {
    padding: 28px 22px;
    border-radius: 16px;
  }

  .text-logo {
    font-size: 28px;
  }

  .login-title {
    font-size: 21px;
    margin-bottom: 20px;
  }
}
</style>
</head>

<body>
<div id="particles-js"></div>

<div class="login-card">
    <img src="../payroll/admin/assets/images/logo-letter.png" class="login-logo" alt="Logo">
    <div class="text-logo">Rhythm</div>

    <form method="POST" autocomplete="off">
        <div class="premium-input">
            <input type="text" name="username" placeholder="Super Admin Username" required>
            <i class="fa fa-user"></i>
        </div>

        <div class="premium-input">
            <input type="password" name="password" id="password" placeholder="Password" required>
            <i class="fa fa-eye" id="togglePassword"></i>
        </div>

        <button name="login" class="premium-btn" id="loginBtn" type="submit">
            <span class="btn-text">Login</span>
            <span class="spinner"></span>
        </button>
    </form>

    <div class="login-footer">
        Powered by ©
        <strong>
            <a href="https://abhitechbot.in/" class="footer-link" target="_blank">AbhiTechBot</a>
        </strong>
    </div>
</div>

<script>
document.querySelector("form").addEventListener("submit", () => {
    const btn = document.getElementById("loginBtn");
    btn.classList.add("loading");
});
</script>

<script>
const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("password");
togglePassword.addEventListener("click", () => {
    const isPassword = passwordInput.type === "password";
    passwordInput.type = isPassword ? "text" : "password";
    togglePassword.classList.toggle("fa-eye");
    togglePassword.classList.toggle("fa-eye-slash");
});
</script>

<script>
particlesJS("particles-js", {
  "particles": {
    "number": { "value": 65 },
    "size": { "value": 3 },
    "move": { "speed": 1.2 },
    "color": { "value": "#1dbfc2" },
    "line_linked": { "enable": true, "color": "#1dbfc2", "opacity": 0.4 }
  }
});
</script>

<?php
if (isset($_SESSION['error'])) {
    $err = addslashes((string)$_SESSION['error']);
    echo "<script>Swal.fire('Error','{$err}','error');</script>";
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $url = $_SESSION['redirect_url'] ?? 'client_databases';
    $msg = addslashes((string)$_SESSION['success']);
    echo "<script>
        Swal.fire({icon:'success',title:'Success',text:'{$msg}',timer:1200,showConfirmButton:false});
        setTimeout(()=>location.href='{$url}',1300);
    </script>";
    unset($_SESSION['success'], $_SESSION['redirect_url']);
}
?>

<script src="../payroll/admin/assets/js/vendors.min.js"></script>
<script src="../payroll/admin/assets/js/netCheck.js"></script>
</body>
</html>