<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// 🔐 Super admin protection
if (!isset($_SESSION['login']) || ($_SESSION['super_admin'] ?? false) !== true) {
    header("Location: index");
    exit;
}

include "header.php";

// ✅ Ensure master connection ($master should come from header/db_conn)
if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection (\$master) not found. Check db_conn");
}

/** Escape helper */
if (!function_exists('e')) {
    function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
}

$msg = "";
$msgType = "info"; // success | error | info
$dbExists = false;

// ================= EDIT LOAD =================
$id   = (int)($_GET['id'] ?? 0);
$edit = ($id > 0);

$deptData = [
    'id'          => '',
    'client_code' => '',
    'module_key'  => '',
    'db_host'     => 'localhost',
    'db_name'     => '',
    'db_user'     => '',
    'db_pass'     => '',
    'status'      => 'active',
];

if ($edit) {
    $stmt = $master->prepare("
        SELECT id, client_code, module_key, db_host, db_name, db_user, db_pass, status
        FROM client_databases
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) die("Prepare failed: " . $master->error);

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) die("Record not found!");
    $deptData = $row;
}

// ================= FETCH MODULES (active) =================
$modules = [];
$stmt = $master->prepare("SELECT module_key, module_name FROM modules WHERE status='active' ORDER BY module_name ASC");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $modules[] = $r;
    $stmt->close();
}

function connectClientDb($host, $user, $pass, $dbname) {
    $conn = @new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_errno) return null;
    $conn->set_charset("utf8mb4");
    return $conn;
}

function createClientTables(mysqli $clientDb) {
    // companies
    $sql1 = "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    // users
    $sql2 = "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    // user_access
    $sql3 = "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    // log_activity
    $sql4 = "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    if (!$clientDb->query($sql1)) return "companies table create failed: " . $clientDb->error;
    if (!$clientDb->query($sql2)) return "users table create failed: " . $clientDb->error;
    if (!$clientDb->query($sql3)) return "user_access table create failed: " . $clientDb->error;
    if (!$clientDb->query($sql4)) return "log_activity table create failed: " . $clientDb->error;

    return true;
}

function seedClientData(mysqli $clientDb, $client_code, $module_key) {
    // Insert client row (if not exists)
    $stmt = $clientDb->prepare("INSERT IGNORE INTO companies (client_code, client_name, status) VALUES (?, ?, 'active')");
    $defaultName = strtoupper($client_code);
    $stmt->bind_param("ss", $client_code, $defaultName);
    if (!$stmt->execute()) { $err = $stmt->error; $stmt->close(); return "companies insert failed: $err"; }
    $stmt->close();

    // Create default admin user (username: admin) if not exists
    // password: Admin@123 (hash) -> user should change after login
    $adminUser = "admin";
    $adminName = "Admin";
    $client_id = 1; // assuming single client row, or you can fetch the actual client_id after insert
    $passHash  = password_hash("Admin@123", PASSWORD_BCRYPT);

    $stmt = $clientDb->prepare("
        INSERT INTO users (client_id, name, username, password_hash, role, status)
        SELECT ?, ?, ?, ?, 'admin', 'active'
        WHERE NOT EXISTS (SELECT 1 FROM users WHERE username=? LIMIT 1)
    ");
    $stmt->bind_param("issss", $client_id, $adminName, $adminUser, $passHash, $adminUser);
    if (!$stmt->execute()) { $err = $stmt->error; $stmt->close(); return "users insert failed: $err"; }
    $stmt->close();

    // give full access to admin (basic example pages)
    $adminId = 0;
    $res = $clientDb->query("SELECT id FROM users WHERE username='admin' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) $adminId = (int)$row['id'];

    if ($adminId > 0) {
        $pages = [
            'dashboard',
            'company_settings',
            'users',
            'users_access_master',
            'log_activity',
            'settings'
        ];
        $stmt = $clientDb->prepare("
            INSERT IGNORE INTO user_access (user_id, module_key, page_name, can_view, can_add, can_edit, can_delete, client_id)
            VALUES (?, ?, ?, 1, 1, 1, 1, ?)
        ");
        foreach ($pages as $p) {
            $stmt->bind_param("issi", $adminId, $module_key, $p, $client_id);
            if (!$stmt->execute()) { $err = $stmt->error; $stmt->close(); return "user_access insert failed: $err"; }
        }
        $stmt->close();
    }

    return true;
}

// ================= INSERT / UPDATE LOGIC =================
if (isset($_POST['save_client'])) {

    $client_code = strtolower(trim($_POST['client_code'] ?? ''));
    $module_key  = strtolower(trim($_POST['module_key'] ?? ''));
    $db_host     = trim($_POST['db_host'] ?? '');
    $db_name     = trim($_POST['db_name'] ?? '');
    $db_user     = trim($_POST['db_user'] ?? '');
    $db_pass     = trim($_POST['db_pass'] ?? '');
    $status      = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $createDb       = isset($_POST['create_db']) ? 1 : 0;
    $createTables   = isset($_POST['create_tables']) ? 1 : 0;
    $seedData       = isset($_POST['seed_data']) ? 1 : 0;

    // required validation
    if ($client_code === '' || $module_key === '' || $db_host === '' || $db_name === '' || $db_user === '') {
        $msg = "❌ All required fields must be filled";
        $msgType = "error";
    }

    // safe db name if creating/tables/seed
    if ($msg === "" && ($createDb || $createTables || $seedData)) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
            $msg = "❌ Invalid DB name. Use only letters, numbers, underscore.";
            $msgType = "error";
        }
    }

    // ✅ DB exists check + create DB (optional)
    if ($msg === "" && $createDb) {
        $dbExists = false;

        $checkDb = $master->prepare("
            SELECT SCHEMA_NAME
            FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME = ?
        ");
        if (!$checkDb) die("Prepare failed: " . $master->error);

        $checkDb->bind_param("s", $db_name);
        $checkDb->execute();
        $checkDb->store_result();

        if ($checkDb->num_rows > 0) $dbExists = true;
        $checkDb->close();

        if ($dbExists) {
            echo "<script>
                Swal.fire({
                    icon: 'info',
                    title: 'Database Exists',
                    text: 'Database already exists. Using existing database.',
                    timer: 1400,
                    showConfirmButton: false
                });
            </script>";
        } else {
            $sqlCreate = "CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            if (!$master->query($sqlCreate)) {
                $msg = "❌ DB Create failed: " . $master->error . " (Need CREATE privilege)";
                $msgType = "error";
            } else {
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Database Created',
                        text: 'New database created successfully.',
                        timer: 1200,
                        showConfirmButton: false
                    });
                </script>";
            }
        }
    }

    // ✅ Create tables + Seed (optional)
    if ($msg === "" && ($createTables || $seedData)) {

        // ensure db exists (even if createDb unchecked)
        $checkDb = $master->prepare("
            SELECT SCHEMA_NAME
            FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME = ?
        ");
        $checkDb->bind_param("s", $db_name);
        $checkDb->execute();
        $checkDb->store_result();
        $exists = ($checkDb->num_rows > 0);
        $checkDb->close();

        if (!$exists) {
            $msg = "❌ Database not found. Please create DB first (tick Create database).";
            $msgType = "error";
        } else {
            $clientDb = connectClientDb($db_host, $db_user, $db_pass, $db_name);
            if (!$clientDb) {
                $msg = "❌ Cannot connect to client DB using given credentials. Check host/user/pass.";
                $msgType = "error";
            } else {

                if ($createTables) {
                    $r = createClientTables($clientDb);
                    if ($r !== true) {
                        $msg = "❌ Table setup failed: " . $r;
                        $msgType = "error";
                    }
                }

                if ($msg === "" && $seedData) {
                    $r = seedClientData($clientDb, $client_code, $module_key);
                    if ($r !== true) {
                        $msg = "❌ Default data insert failed: " . $r;
                        $msgType = "error";
                    } else {
                        echo "<script>
                            Swal.fire({
                                icon: 'success',
                                title: 'Tables + Data Ready',
                                text: 'Tables created and default data inserted (admin/Admin@123).',
                                timer: 1800,
                                showConfirmButton: false
                            });
                        </script>";
                    }
                }

                $clientDb->close();
            }
        }
    }

    // ✅ Duplicate check + Insert/Update in MASTER
    if ($msg === "") {

        if ($edit) {
            $chk = $master->prepare("
                SELECT id FROM client_databases
                WHERE client_code = ? AND module_key = ? AND id != ?
                LIMIT 1
            ");
            $chk->bind_param("ssi", $client_code, $module_key, $id);
        } else {
            $chk = $master->prepare("
                SELECT id FROM client_databases
                WHERE client_code = ? AND module_key = ?
                LIMIT 1
            ");
            $chk->bind_param("ss", $client_code, $module_key);
        }

        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $msg = "❌ Client Code + Module already exists";
            $msgType = "error";
            $chk->close();
        } else {
            $chk->close();

            if ($edit) {
                $stmt = $master->prepare("
                    UPDATE client_databases
                    SET client_code=?, module_key=?, db_host=?, db_name=?, db_user=?, db_pass=?, status=?, updated_at=NOW()
                    WHERE id=?
                    LIMIT 1
                ");
                $stmt->bind_param("sssssssi", $client_code, $module_key, $db_host, $db_name, $db_user, $db_pass, $status, $id);

                if ($stmt->execute()) {
                    echo "<script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Updated!',
                          text: 'Client database updated successfully',
                          timer: 1200,
                          showConfirmButton: false
                        }).then(() => window.location.href = 'client_databases');
                    </script>";
                    exit;
                } else {
                    $msg = "❌ Update failed: " . $stmt->error;
                    $msgType = "error";
                }
                $stmt->close();

            } else {
                $stmt = $master->prepare("
                    INSERT INTO client_databases
                    (client_code, module_key, db_host, db_name, db_user, db_pass, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->bind_param("sssssss", $client_code, $module_key, $db_host, $db_name, $db_user, $db_pass, $status);

                if ($stmt->execute()) {
                    echo "<script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success!',
                          text: 'Client database added successfully',
                          timer: 1200,
                          showConfirmButton: false
                        }).then(() => window.location.href = 'client_databases');
                    </script>";
                    exit;
                } else {
                    $err = e($stmt->error);
                    echo "<script>
                        Swal.fire({
                          icon: 'error',
                          title: 'Error!',
                          text: 'Insert failed: {$err}',
                          showConfirmButton: true
                        });
                    </script>";
                    exit;
                }
                $stmt->close();
            }
        }
    }

    // refill on error
    $deptData = [
        'id'          => $id,
        'client_code' => $client_code,
        'module_key'  => $module_key,
        'db_host'     => $db_host,
        'db_name'     => $db_name,
        'db_user'     => $db_user,
        'db_pass'     => $db_pass,
        'status'      => $status,
    ];
}

$selectedModule = $deptData['module_key'] ?? '';
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
          <?php if ($msg): ?>
            <div class="alert alert-<?= ($msgType === 'error') ? 'danger' : 'info' ?>">
              <?= e($msg) ?>
            </div>
          <?php endif; ?>

          <form method="POST" autocomplete="off">

            <!-- Tabs -->
            <ul class="nav nav-tabs" role="tablist">
              <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tab_db" role="tab">Database</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab_tables" role="tab">Tables</a>
              </li>
            </ul>

            <div class="tab-content" style="border:1px solid #dee2e6;border-top:none;padding:15px;">

              <!-- TAB 1: Database -->
              <div class="tab-pane fade show active" id="tab_db" role="tabpanel">

                <div class="row">
                  <div class="col-md-4 mb-3">
                    <label>Client Code <span class="text-danger">*</span></label>
                    <input type="text" name="client_code" class="form-control"
                           value="<?= e($deptData['client_code']) ?>" placeholder="abc_clinic" required>
                  </div>

                  <div class="col-md-4 mb-3">
                    <label>Module Key <span class="text-danger">*</span></label>
                    <select name="module_key" class="form-control select2" required>
                      <option value="">-- Select Module --</option>
                      <?php foreach ($modules as $m): ?>
                        <option value="<?= e($m['module_key']) ?>"
                          <?= ($selectedModule === $m['module_key']) ? 'selected' : '' ?>>
                          <?= e($m['module_name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-4 mb-3">
                    <label>Status</label>
                    <select name="status" class="form-control">
                      <option value="active"   <?= ($deptData['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                      <option value="inactive" <?= ($deptData['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                  </div>
                </div>

                <hr>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label>DB Host <span class="text-danger">*</span></label>
                    <input type="text" name="db_host" class="form-control"
                           value="<?= e($deptData['db_host'] ?? 'localhost') ?>" required>
                  </div>

                  <div class="col-md-6 mb-3">
                    <label>DB Name <span class="text-danger">*</span></label>
                    <input type="text" name="db_name" class="form-control"
                           value="<?= e($deptData['db_name']) ?>" placeholder="abc_clinic_db" required>

                    <div class="form-check mt-2">
                      <input class="form-check-input" type="checkbox" name="create_db" value="1" id="create_db"
                        <?= (!empty($_POST['create_db'])) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="create_db">
                        Create database automatically (if not exists)
                      </label>
                    </div>
                    <small class="text-muted">Allowed DB name: letters, numbers, underscore only.</small>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label>DB User <span class="text-danger">*</span></label>
                    <input type="text" name="db_user" class="form-control"
                           value="<?= e($deptData['db_user']) ?>" required>
                  </div>

                  <div class="col-md-6 mb-3">
                    <label>DB Password</label>
                    <input type="text" name="db_pass" class="form-control"
                           value="<?= e($deptData['db_pass']) ?>" placeholder="(optional)">
                  </div>
                </div>

              </div>

              <!-- TAB 2: Tables -->
              <div class="tab-pane fade" id="tab_tables" role="tabpanel">
                <p class="text-muted mb-2">Create required tables inside the client database.</p>

                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="create_tables" value="1" id="create_tables"
                    <?= (!empty($_POST['create_tables'])) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="create_tables">
                    Create tables: companies, users, user_access, log_activity
                  </label>
                </div>

                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="seed_data" value="1" id="seed_data"
                    <?= (!empty($_POST['seed_data'])) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="seed_data">
                    Insert default data (admin user + access)
                  </label>
                  <div class="text-muted" style="font-size:12px;">
                    Default admin: <b>admin</b> / <b>Admin@123</b> (please change after login)
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