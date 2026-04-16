<?php
session_start();
require_once 'db_conn.php';
require_once 'auth.php';

function showError(string $msg) {
    $_SESSION['error'] = $msg;
    header("Location: index");
    exit;
}

function hasAccess(string $key): bool {
    return in_array(strtolower($key), $_SESSION['user_access'] ?? [], true);
}

function getRedirectPage(): string {
    if (hasAccess('dashboard')) return 'payroll/admin/index';
    $access = $_SESSION['user_access'] ?? [];
    return !empty($access) ? 'payroll/admin/' . $access[0] : 'payroll/admin/index';
}

// ---------------- LOGIN PROCESS ----------------
if (isset($_POST['login'])) {
    // ✅ Read inputs
    $client_code = strtolower(trim($_POST['client_code'] ?? ''));
    $module_key  = strtolower(trim($_POST['module_key'] ?? 'payroll'));
    $username    = trim($_POST['username'] ?? '');
    $password    = trim($_POST['password'] ?? '');

    if ($client_code === '') showError("Please enter Client Code");
    if ($username === '') showError("Please enter username");
    if ($password === '') showError("Please enter password");

    // 1) Find client DB from app_master.client_databases
    $stmt = mysqli_prepare($master, "
        SELECT db_host, db_name, db_user, db_pass
        FROM client_databases
        WHERE client_code = ? AND module_key = ? AND status='active'
        LIMIT 1
    ");

    if (!$stmt) showError("Master query prepare failed");

    mysqli_stmt_bind_param($stmt, "ss", $client_code, $module_key);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $db  = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    // ✅ Invalid Client Code
    if (!$db) {
        // Clear wrong saved client code cookie
        setcookie("client_code", "", time() - 3600, "/");
        showError("Invalid Client Code. Please check and try again.");
    }

    // 2) Connect to client DB (auth + work DB)
    $conn = mysqli_connect($db['db_host'], $db['db_user'], $db['db_pass'], $db['db_name']);
    if (!$conn) showError("Client DB connection failed");
    mysqli_set_charset($conn, "utf8mb4");

    // 3) Login from client DB users table
    $u = mysqli_prepare($conn, "
        SELECT id, username, password_hash, role, status, client_id
        FROM users
        WHERE username = ?
        LIMIT 1
    ");
    if (!$u) showError("User query prepare failed");

    mysqli_stmt_bind_param($u, "s", $username);
    mysqli_stmt_execute($u);
    $ures = mysqli_stmt_get_result($u);
    $user = mysqli_fetch_assoc($ures);
    mysqli_stmt_close($u);

    if (!$user) showError("User not found");
    if (($user['status'] ?? '') !== 'active') showError("User inactive");
    if (!password_verify($password, $user['password_hash'])) showError("Wrong password");

    // 4) Load access from SAME client DB
    $accStmt = mysqli_prepare($conn, "
        SELECT page_name
        FROM user_access
        WHERE user_id = ?
          AND (can_view=1 OR can_add=1 OR can_edit=1 OR can_delete=1)
    ");

    if (!$accStmt) showError("Access query prepare failed");

    mysqli_stmt_bind_param($accStmt, "i", $user['id']);
    mysqli_stmt_execute($accStmt);
    $accRes = mysqli_stmt_get_result($accStmt);

    $user_access = [];
    while ($row = mysqli_fetch_assoc($accRes)) {
        $user_access[] = strtolower($row['page_name']);
    }
    mysqli_stmt_close($accStmt);

    // 5) Session set
    $_SESSION['login']        = true;
    $_SESSION['user_id']      = (int)$user['id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['role']         = $user['role'] ?? 'user';
    $_SESSION['client_code']  = $client_code;
    $_SESSION['module_key']   = $module_key;
    $_SESSION['client_db']    = $db['db_name'];
    $_SESSION['user_access']  = $user_access;
    $_SESSION['client_id']    = (int)$user['client_id'];

    // 6) Update last login in client DB
    $up = mysqli_prepare($conn, "UPDATE users SET last_login_at=NOW() WHERE id=?");
    if ($up) {
        mysqli_stmt_bind_param($up, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
    }

    // ✅ Save client_code cookie (30 days) BEFORE redirect
    $client_code_cookie = (string)($client_code ?? '');
    if ($client_code_cookie !== '') {
        setcookie(
            "client_code",
            $client_code_cookie,
            time() + (30 * 24 * 60 * 60),
            "/",
            "",
            false,
            true
        );
    }

    // ✅ Redirect
    $_SESSION['redirect_url'] = getRedirectPage();
    $_SESSION['success']      = "Login successful! Welcome {$user['username']}";

    header("Location: " . $_SESSION['redirect_url']);
    exit;
}

// ---------------- COOKIE PREFILL ----------------
$savedClientCode = $_COOKIE['client_code'] ?? '';
$savedClientCode = is_string($savedClientCode) ? $savedClientCode : '';

?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="IE=edge" http-equiv="X-UA-Compatible">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rhythm E-Clinic Solutions - Login</title>
<link href="payroll/admin/css/vendors_css.css" rel="stylesheet">
<link href="payroll/admin/css/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
<link href="payroll/images/favicon.ico" rel="icon">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<style>
#particles-js { position: fixed; width: 100%; height: 100%; background: #0f172a; z-index: -1; }
body { display:flex; justify-content:center; align-items:center; min-height:100vh; font-family:"Poppins",sans-serif; margin:0; }
.login-logo { width:140px; display:block; margin:0 auto 15px; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%{transform:translateY(0)} 50%{transform:translateY(-12px)} 100%{transform:translateY(0)} }
.login-card { width:95%; max-width:420px; padding:40px 35px; background:rgba(255,255,255,0.12); border-radius:20px; backdrop-filter: blur(14px); box-shadow:0 10px 40px rgba(0,0,0,0.35); animation: slideUp .8s ease-out; }
@keyframes slideUp { from{opacity:0; transform:translateY(50px)} to{opacity:1; transform:translateY(0)} }
.login-title { text-align:center; font-size:28px; font-weight:800; background:linear-gradient(135deg,#1dbfc2,#3246d3); -webkit-background-clip:text; color:white; }
.premium-input { position:relative; margin-bottom:18px; }
.premium-input input, .premium-input select { width:100%; padding:15px 50px 15px 18px; font-size:15px; border-radius:12px; border:none; background:rgba(255,255,255,0.25); color:#fff; outline:none; backdrop-filter: blur(4px); }
.premium-input input::placeholder { color:#e0e7ff; }
.premium-input select { padding-right: 18px; }
.premium-input i { position:absolute; right:18px; top:14px; font-size:18px; cursor:pointer; color:#fff; }
.premium-btn { width:100%; padding:14px; font-size:16px; font-weight:700; background:linear-gradient(135deg,#3246d3,#1dbfc2); border:none; color:white; border-radius:12px; margin-top:10px; cursor:pointer; box-shadow:0 6px 20px rgba(50,70,211,0.4); transition:.3s; }
.premium-btn:hover { transform:translateY(-3px); background:linear-gradient(135deg,#1dbfc2,#3246d3); box-shadow:0 10px 25px rgba(29,191,194,.45); }
.forgot-link { text-align:center; display:block; margin-top:18px; color:#e0e7ff; }
.forgot-link:hover { text-decoration: underline; }
.login-footer{ font-size:13px; color:#aaa; text-align:center; }
.footer-link{ color:#8ab4f8; text-decoration:none; }
.footer-link:hover{ text-decoration:underline; }
.text-logo{ font-size:32px; font-weight:700; letter-spacing:1px; color:#ffffff; text-align:center; margin-bottom:10px; }
.text-logo::after{ content:""; display:block; width:60px; height:3px; margin:6px auto 0; background:linear-gradient(135deg,#3246d3,#1dbfc2); border-radius:10px; }
.spinner{ display:none; width:18px; height:18px; border:3px solid rgba(255,255,255,0.4); border-top:3px solid #fff; border-radius:50%; animation:spin 0.8s linear infinite; margin:auto; }
@keyframes spin{ 0%{transform:rotate(0)} 100%{transform:rotate(360deg)} }
.premium-btn.loading{ pointer-events:none; opacity:0.85; }
.premium-btn.loading .btn-text{ display:none; }
.premium-btn.loading .spinner{ display:block; }
</style>

</head>

<body>
<div id="particles-js"></div>

<div class="login-card">
    <img src="payroll/images/logo-letter.png" class="login-logo" alt="Logo">
    <div class="text-logo">Rhythm</div>

    <h5 class="login-title">Welcome Back 👋</h5>
    <?php
        $savedClientCode = $_COOKIE['client_code'] ?? '';
    ?>

<form method="POST">
    <div class="premium-input">
        <input type="text"
               name="client_code"
               placeholder="Enter Company Code"
               value="<?php echo htmlspecialchars($savedClientCode); ?>"
               required>
    </div>

    <input type="hidden" name="module_key" value="payroll">

    <div class="premium-input">
        <input type="text" name="username" placeholder="Username" required>
        <i class="fa fa-user"></i>
    </div>

    <div class="premium-input">
        <input type="password" name="password" id="password" placeholder="Password" required>
        <i class="fa fa-eye" id="togglePassword"></i>
    </div>

    <button name="login" class="premium-btn" id="loginBtn">
        <span class="btn-text">Login</span>
        <span class="spinner"></span>
    </button>
</form>
    <a href="forgot_password" class="forgot-link">Forgot Password?</a>
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
    $url = $_SESSION['redirect_url'] ?? 'payroll/admin/index';
    $msg = addslashes((string)$_SESSION['success']);
    echo "<script>
        Swal.fire({icon:'success',title:'Success',text:'{$msg}',timer:2000,showConfirmButton:false});
        setTimeout(()=>location.href='{$url}',2200);
    </script>";
    unset($_SESSION['success'], $_SESSION['redirect_url']);
}

?>
<script src="payroll/admin/js/vendors.min.js"></script>
<script src="payroll/admin/js/netCheck.js"></script>
</body>
</html>