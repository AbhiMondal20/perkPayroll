<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || ($_SESSION['super_admin'] ?? false) !== true) {
    header("Location: index");
    exit;
}

require_once '../db_conn.php';
include "header.php";

if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection (\$master) not found. Check db_conn.php");
}

if (!function_exists('e')) {
    function e($str)
    {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

$msg = "";
$msgType = "info";
$swal = null;

/* =========================
   HELPERS
========================= */

function connectClientDb($host, $user, $pass, $dbname, $port = 3306)
{
    $conn = @new mysqli($host, $user, $pass, $dbname, (int)$port);
    if ($conn->connect_errno) {
        return null;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

function databaseExists(mysqli $master, $dbName)
{
    $stmt = $master->prepare("
        SELECT SCHEMA_NAME
        FROM INFORMATION_SCHEMA.SCHEMATA
        WHERE SCHEMA_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $dbName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function createDatabaseIfNotExists(mysqli $master, $dbName)
{
    $dbName = trim($dbName);

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        return "Invalid DB name. Use only letters, numbers and underscore.";
    }

    if (databaseExists($master, $dbName)) {
        return true;
    }

    $sql = "CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!$master->query($sql)) {
        return "DB Create failed: " . $master->error;
    }

    return true;
}

function createClientTables(mysqli $clientDb)
{
    $queries = [];

    $queries[] = "
        CREATE TABLE IF NOT EXISTS companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_code VARCHAR(100) NOT NULL UNIQUE,
            client_name VARCHAR(200) NULL,
            logo VARCHAR(255) NULL,
            phone VARCHAR(30) NULL,
            email VARCHAR(150) NULL,
            website VARCHAR(255) NULL,
            address TEXT NULL,
            letter_head_type ENUM('type1','type2','type3') NULL,
            latter_head TEXT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            name VARCHAR(200) NOT NULL,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(150) NULL,
            phone VARCHAR(30) NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(255) NOT NULL DEFAULT 'staff',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS user_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            module_key VARCHAR(100) NOT NULL,
            page_name VARCHAR(120) NOT NULL,
            can_view TINYINT(1) NOT NULL DEFAULT 0,
            can_add TINYINT(1) NOT NULL DEFAULT 0,
            can_edit TINYINT(1) NOT NULL DEFAULT 0,
            can_delete TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            client_id INT NOT NULL,
            UNIQUE KEY uniq_user_page (user_id, module_key, page_name),
            CONSTRAINT fk_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS log_activity (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(200) NOT NULL,
            description TEXT NULL,
            ip_address VARCHAR(60) NULL,
            browser VARCHAR(255) NULL,
            device VARCHAR(255) NULL,
            page VARCHAR(255) NULL,
            log_date VARCHAR(255) NULL,
            status ENUM('success','failure') NOT NULL DEFAULT 'success',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    foreach ($queries as $sql) {
        if (!$clientDb->query($sql)) {
            return $clientDb->error;
        }
    }

    return true;
}

function seedClientData(mysqli $clientDb, $clientCode, $moduleKey)
{
    $defaultName = strtoupper($clientCode);

    $stmt = $clientDb->prepare("
        INSERT INTO companies (client_code, client_name, status)
        VALUES (?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            client_name = VALUES(client_name),
            status = 'active'
    ");
    if (!$stmt) {
        return "Prepare failed for companies: " . $clientDb->error;
    }

    $stmt->bind_param("ss", $clientCode, $defaultName);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        return "Companies insert failed: " . $err;
    }
    $stmt->close();

    $companyId = 0;
    $stmt = $clientDb->prepare("SELECT id FROM companies WHERE client_code = ? LIMIT 1");
    if (!$stmt) {
        return "Prepare failed for company fetch: " . $clientDb->error;
    }

    $stmt->bind_param("s", $clientCode);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $companyId = (int)$row['id'];
    }
    $stmt->close();

    if ($companyId <= 0) {
        return "Unable to fetch company id.";
    }

    $adminUser = "admin";
    $adminName = "Admin";
    $passHash  = password_hash("Admin@123", PASSWORD_BCRYPT);

    $stmt = $clientDb->prepare("
        INSERT INTO users (client_id, name, username, password_hash, role, status)
        SELECT ?, ?, ?, ?, 'admin', 'active'
        WHERE NOT EXISTS (
            SELECT 1 FROM users WHERE username = ? LIMIT 1
        )
    ");
    if (!$stmt) {
        return "Prepare failed for users: " . $clientDb->error;
    }

    $stmt->bind_param("issss", $companyId, $adminName, $adminUser, $passHash, $adminUser);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        return "Admin insert failed: " . $err;
    }
    $stmt->close();

    $adminId = 0;
    $stmt = $clientDb->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        return "Prepare failed for admin fetch: " . $clientDb->error;
    }

    $stmt->bind_param("s", $adminUser);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $adminId = (int)$row['id'];
    }
    $stmt->close();

    if ($adminId <= 0) {
        return "Unable to fetch admin user id.";
    }

    $pages = [
        'dashboard',
        'company_settings',
        'users',
        'users_access_master',
        'log_activity',
        'settings'
    ];

    $stmt = $clientDb->prepare("
        INSERT IGNORE INTO user_access
        (user_id, module_key, page_name, can_view, can_add, can_edit, can_delete, client_id)
        VALUES (?, ?, ?, 1, 1, 1, 1, ?)
    ");
    if (!$stmt) {
        return "Prepare failed for access insert: " . $clientDb->error;
    }

    foreach ($pages as $page) {
        $stmt->bind_param("issi", $adminId, $moduleKey, $page, $companyId);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return "Access insert failed: " . $err;
        }
    }

    $stmt->close();
    return true;
}

/* =========================
   LOAD EDIT DATA
========================= */

$id   = (int)($_GET['id'] ?? 0);
$edit = $id > 0;

$formData = [
    'id'          => '',
    'client_id'   => '',
    'client_code' => '',
    'module_key'  => '',
    'db_host'     => 'localhost',
    'db_name'     => '',
    'db_user'     => '',
    'db_pass'     => '',
    'port'        => '3306',
    'status'      => 'active',
];

if ($edit) {
    $stmt = $master->prepare("
        SELECT id, client_id, client_code, module_key, db_host, db_name, db_user, db_pass, port, status
        FROM client_databases
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        die("Prepare failed: " . e($master->error));
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        die("Record not found!");
    }

    $formData = $row;
}

/* =========================
   FETCH CLIENTS
========================= */

$clients = [];
$stmt = $master->prepare("
    SELECT id, client_name
    FROM clients
    WHERE status = 'active'
    ORDER BY client_name ASC
");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $clients[] = $r;
    }
    $stmt->close();
}

/* =========================
   FETCH MODULES
========================= */

$modules = [];
$stmt = $master->prepare("
    SELECT module_key, module_name
    FROM modules
    WHERE status = 'active'
    ORDER BY module_name ASC
");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $modules[] = $r;
    }
    $stmt->close();
}

/* =========================
   SAVE LOGIC
========================= */

if (isset($_POST['save_client'])) {
    $client_id   = (int)($_POST['client_id'] ?? 0);
    $client_code = strtolower(trim($_POST['client_code'] ?? ''));
    $module_key  = strtolower(trim($_POST['module_key'] ?? ''));
    $db_host     = trim($_POST['db_host'] ?? '');
    $db_name     = trim($_POST['db_name'] ?? '');
    $db_user     = trim($_POST['db_user'] ?? '');
    $db_pass     = trim($_POST['db_pass'] ?? '');
    $port        = (int)($_POST['port'] ?? 3306);
    $status      = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $createDb     = isset($_POST['create_db']) ? 1 : 0;
    $createTables = isset($_POST['create_tables']) ? 1 : 0;
    $seedData     = isset($_POST['seed_data']) ? 1 : 0;

    $formData = [
        'id'          => $id,
        'client_id'   => $client_id,
        'client_code' => $client_code,
        'module_key'  => $module_key,
        'db_host'     => $db_host,
        'db_name'     => $db_name,
        'db_user'     => $db_user,
        'db_pass'     => $db_pass,
        'port'        => $port,
        'status'      => $status,
    ];

    if (
        $client_id <= 0 ||
        $client_code === '' ||
        $module_key === '' ||
        $db_host === '' ||
        $db_name === '' ||
        $db_user === ''
    ) {
        $msg = "All required fields must be filled.";
        $msgType = "error";
    } elseif ($port <= 0) {
        $msg = "Invalid port number.";
        $msgType = "error";
    } elseif (!preg_match('/^[a-z0-9_]+$/', $client_code)) {
        $msg = "Client code should contain only lowercase letters, numbers and underscore.";
        $msgType = "error";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
        $msg = "Invalid DB name. Use only letters, numbers and underscore.";
        $msgType = "error";
    }

    if ($msg === "") {
        $chkClient = $master->prepare("SELECT id FROM clients WHERE id = ? LIMIT 1");
        if (!$chkClient) {
            $msg = "Client validation prepare failed: " . $master->error;
            $msgType = "error";
        } else {
            $chkClient->bind_param("i", $client_id);
            $chkClient->execute();
            $chkClient->store_result();
            if ($chkClient->num_rows === 0) {
                $msg = "Selected client not found.";
                $msgType = "error";
            }
            $chkClient->close();
        }
    }

    if ($msg === "" && $seedData && !$createTables) {
        $msg = "Please check 'Create tables' before seeding default data.";
        $msgType = "error";
    }

    if ($msg === "") {
        if ($edit) {
            $chk = $master->prepare("
                SELECT id
                FROM client_databases
                WHERE client_id = ? AND client_code = ? AND module_key = ? AND id != ?
                LIMIT 1
            ");
            if ($chk) {
                $chk->bind_param("issi", $client_id, $client_code, $module_key, $id);
            }
        } else {
            $chk = $master->prepare("
                SELECT id
                FROM client_databases
                WHERE client_id = ? AND client_code = ? AND module_key = ?
                LIMIT 1
            ");
            if ($chk) {
                $chk->bind_param("iss", $client_id, $client_code, $module_key);
            }
        }

        if (!$chk) {
            $msg = "Duplicate check prepare failed: " . $master->error;
            $msgType = "error";
        } else {
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $msg = "This client database entry already exists.";
                $msgType = "error";
            }
            $chk->close();
        }
    }

    if ($msg === "") {
        if ($edit) {
            $stmt = $master->prepare("
                UPDATE client_databases
                SET client_id = ?, client_code = ?, module_key = ?, db_host = ?, db_name = ?, db_user = ?, db_pass = ?, port = ?, status = ?, updated_at = NOW()
                WHERE id = ?
                LIMIT 1
            ");
            if (!$stmt) {
                $msg = "Update prepare failed: " . $master->error;
                $msgType = "error";
            } else {
                $stmt->bind_param(
                    "issssssisi",
                    $client_id,
                    $client_code,
                    $module_key,
                    $db_host,
                    $db_name,
                    $db_user,
                    $db_pass,
                    $port,
                    $status,
                    $id
                );

                if (!$stmt->execute()) {
                    $msg = "Update failed: " . $stmt->error;
                    $msgType = "error";
                }
                $stmt->close();
            }
        } else {
            $stmt = $master->prepare("
                INSERT INTO client_databases
                (client_id, client_code, module_key, db_host, db_name, db_user, db_pass, port, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            if (!$stmt) {
                $msg = "Insert prepare failed: " . $master->error;
                $msgType = "error";
            } else {
                $stmt->bind_param(
                    "issssssis",
                    $client_id,
                    $client_code,
                    $module_key,
                    $db_host,
                    $db_name,
                    $db_user,
                    $db_pass,
                    $port,
                    $status
                );

                if (!$stmt->execute()) {
                    $msg = "Insert failed: " . $stmt->error;
                    $msgType = "error";
                }
                $stmt->close();
            }
        }
    }

    if ($msg === "" && $createDb) {
        $result = createDatabaseIfNotExists($master, $db_name);
        if ($result !== true) {
            $msg = $result;
            $msgType = "error";
        }
    }

    if ($msg === "" && ($createTables || $seedData)) {
        if (!databaseExists($master, $db_name)) {
            $msg = "Database not found. Create DB first.";
            $msgType = "error";
        } else {
            $clientDb = connectClientDb($db_host, $db_user, $db_pass, $db_name, $port);

            if (!$clientDb) {
                $msg = "Cannot connect to client DB using given host/user/password/port.";
                $msgType = "error";
            } else {
                if ($createTables) {
                    $result = createClientTables($clientDb);
                    if ($result !== true) {
                        $msg = "Table creation failed: " . $result;
                        $msgType = "error";
                    }
                }

                if ($msg === "" && $seedData) {
                    $result = seedClientData($clientDb, $client_code, $module_key);
                    if ($result !== true) {
                        $msg = "Default data insert failed: " . $result;
                        $msgType = "error";
                    }
                }

                $clientDb->close();
            }
        }
    }

    if ($msg === "") {
        $swal = [
            'icon'     => 'success',
            'title'    => $edit ? 'Updated!' : 'Saved!',
            'text'     => $edit ? 'Client database updated successfully.' : 'Client database added successfully.',
            'redirect' => 'client_databases'
        ];
    } else {
        $swal = [
            'icon'  => 'error',
            'title' => 'Error',
            'text'  => $msg
        ];
    }
}

$selectedModule = $formData['module_key'] ?? '';
?>

<div class="content-wrapper">
    <div class="container-full">

        <div class="content-header">
            <a href="client_databases"><i class="fa fa-arrow-left"></i> Back</a>
        </div>

        <section class="content">
            <div class="box">
                <div class="card-header bg-primary text-white" style="padding:12px 16px;">
                    <i class="fa-solid fa-database me-1"></i>
                    <?= $edit ? 'Edit Client Database' : 'Add Client Database' ?>
                </div>

                <div class="box-body" style="padding:16px;">

                    <?php if ($msg !== "" && !$swal): ?>
                        <div class="alert alert-<?= $msgType === 'error' ? 'danger' : 'info' ?>">
                            <?= e($msg) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#tab_db" role="tab">Database</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tab_tables" role="tab">Tables</a>
                            </li>
                        </ul>

                        <div class="tab-content" style="border:1px solid #dee2e6; border-top:none; padding:15px;">

                            <div class="tab-pane fade show active" id="tab_db" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label>Client <span class="text-danger">*</span></label>
                                        <select name="client_id" class="form-control" required>
                                            <option value="">-- Select Client --</option>
                                            <?php foreach ($clients as $c): ?>
                                                <option value="<?= (int)$c['id'] ?>"
                                                    <?= ((int)($formData['client_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                                                    <?= e($c['client_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label>Client Code <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            name="client_code"
                                            class="form-control"
                                            value="<?= e($formData['client_code']) ?>"
                                            placeholder="abc_clinic"
                                            required
                                        >
                                        <small class="text-muted">Use lowercase letters, numbers and underscore only.</small>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label>Module Key <span class="text-danger">*</span></label>
                                        <select name="module_key" class="form-control" required>
                                            <option value="">-- Select Module --</option>
                                            <?php foreach ($modules as $m): ?>
                                                <option value="<?= e($m['module_key']) ?>"
                                                    <?= $selectedModule === $m['module_key'] ? 'selected' : '' ?>>
                                                    <?= e($m['module_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="active" <?= ($formData['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= ($formData['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label>DB Host <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            name="db_host"
                                            class="form-control"
                                            value="<?= e($formData['db_host']) ?>"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label>Port <span class="text-danger">*</span></label>
                                        <input
                                            type="number"
                                            name="port"
                                            class="form-control"
                                            value="<?= e($formData['port']) ?>"
                                            min="1"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label>DB Name <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            name="db_name"
                                            class="form-control"
                                            value="<?= e($formData['db_name']) ?>"
                                            placeholder="abc_clinic_db"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label>DB User <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            name="db_user"
                                            class="form-control"
                                            value="<?= e($formData['db_user']) ?>"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label>DB Password</label>
                                        <input
                                            type="password"
                                            name="db_pass"
                                            class="form-control"
                                            value="<?= e($formData['db_pass']) ?>"
                                            placeholder="Enter DB password"
                                        >
                                    </div>
                                </div>

                                <div class="form-check mt-2">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="create_db"
                                        value="1"
                                        id="create_db"
                                        <?= !empty($_POST['create_db']) ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="create_db">
                                        Create database automatically if not exists
                                    </label>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tab_tables" role="tabpanel">
                                <p class="text-muted mb-2">Create required tables inside the client database.</p>

                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="create_tables"
                                        value="1"
                                        id="create_tables"
                                        <?= !empty($_POST['create_tables']) ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="create_tables">
                                        Create tables: companies, users, user_access, log_activity
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="seed_data"
                                        value="1"
                                        id="seed_data"
                                        <?= !empty($_POST['seed_data']) ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="seed_data">
                                        Insert default data (admin user + access)
                                    </label>
                                    <div class="text-muted" style="font-size:12px;">
                                        Default admin login: <b>admin</b> / <b>Admin@123</b>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="mt-3">
                            <button type="submit" name="save_client" class="btn btn-primary">
                                <?= $edit ? 'Update Client Database' : 'Save Client Database' ?>
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </section>

    </div>
</div>

<?php include "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if ($swal): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: <?= json_encode($swal['icon']) ?>,
        title: <?= json_encode($swal['title']) ?>,
        text: <?= json_encode($swal['text']) ?>,
        confirmButtonText: 'OK'
    }).then(() => {
        <?php if (!empty($swal['redirect'])): ?>
        window.location.href = <?= json_encode($swal['redirect']) ?>;
        <?php endif; ?>
    });
});
</script>
<?php endif; ?>