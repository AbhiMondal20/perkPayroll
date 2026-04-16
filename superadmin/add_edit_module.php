<?php

if (!isset($_SESSION['login']) || ($_SESSION['super_admin'] ?? false) !== true) {
    header("Location: index");
    exit;
}

include "header.php";

$date = date("Y-m-d H:i:s");
// ✅ Ensure master DB
if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection (\$master) not found. Check db_conn");
}

$id   = (int)($_GET['id'] ?? 0);
$edit = ($id > 0);

$msg = "";

// Default data
$data = [
    'module_key'  => '',
    'module_name' => '',
    'status'      => 'active',
];

// Load existing in edit mode
if ($edit) {
    $stmt = $master->prepare("SELECT id, module_key, module_name, status FROM modules WHERE id=? LIMIT 1");
    if (!$stmt) die("Prepare failed: " . $master->error);

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        die("Module not found!");
    }
    $data = $row;
}

// Save (insert/update)
if (isset($_POST['save_module'])) {

    $module_key  = strtolower(trim($_POST['module_key'] ?? ''));
    $module_name = trim($_POST['module_name'] ?? '');
    $status      = (($_POST['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';

    if ($module_key === '' || $module_name === '') {
        $msg = "❌ Module Key and Module Name are required";
    } else {
        // Duplicate check (module_key unique)
        if ($edit) {
            $chk = $master->prepare("SELECT id FROM modules WHERE module_key=? AND id!=? LIMIT 1");
            $chk->bind_param("si", $module_key, $id);
        } else {
            $chk = $master->prepare("SELECT id FROM modules WHERE module_key=? LIMIT 1");
            $chk->bind_param("s", $module_key);
        }

        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $msg = "❌ Module Key already exists";
            $chk->close();
        } else {
            $chk->close();
            if ($edit) {
                $stmt = $master->prepare("
                    UPDATE modules
                    SET module_key=?, module_name=?, status=?
                    WHERE id=?
                    LIMIT 1
                ");
                if (!$stmt) die("Update prepare failed: " . $master->error);
                $stmt->bind_param("sssi", $module_key, $module_name, $status, $id);
                if ($stmt->execute()) {
                    echo "
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Updated!',
                          text: 'Module updated successfully',
                          timer: 1000,
                          showConfirmButton: false
                        }).then(() => {
                          window.location.href = 'model_key';
                        });
                      </script>";
                    exit;
                } else {
                    echo "
                      <script>
                        Swal.fire({
                          icon: 'error',
                          title: 'Error!',
                          text: 'Module update failed: <?= e($stmt->error) ?>',
                          timer: 1000,
                          showConfirmButton: false
                        })
                      </script>";
                    exit;
                }
                $stmt->close();
            } else {
                $stmt = $master->prepare("
                    INSERT INTO modules (module_key, module_name, status, created_at)
                    VALUES (?, ?, ?, ?)
                ");
                if (!$stmt) die("Insert prepare failed: " . $master->error);
                $stmt->bind_param("ssss", $module_key, $module_name, $status, $date);
               if ($stmt->execute()) {
                  echo "
                    <script>
                      Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Module added successfully',
                        timer: 1000,
                        showConfirmButton: false
                      }).then(() => {
                        window.location.href = 'model_key';
                      });
                    </script>";
                  exit;
                  } else {
                    $msg = "❌ Insert failed: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    // refill on error
    $data['module_key']  = $module_key;
    $data['module_name'] = $module_name;
    $data['status']      = $status;
}


?>

<div class="content-wrapper">
  <div class="container-full">

    <div class="content-header">
      <div class="d-flex align-items-center">
        <div class="me-auto">
           <a href="#" onclick="history.back()" aria-label="Go back">
                <i class="fa fa-arrow-left"></i> Back
            </a>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="row">
        <div class="box">
          <div class="card-header bg-primary text-white">
            <i class="fa-solid fa-clipboard-list me-1"></i>
            <?= $edit ? 'Edit Module' : 'Add Module' ?>
          </div>

          <div class="box-body">

            <?php if ($msg): ?>
              <div class="alert alert-info"><?= e($msg) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
              <div class="row">
                <div class="col-md-4">
                  <label>Module Key *</label>
                  <input type="text"
                         name="module_key"
                         class="form-control"
                         placeholder="payroll"
                         value="<?= e($data['module_key']) ?>"
                         required>
                  <small class="text-muted">lowercase, no space (example: payroll_run)</small>
                </div>

                <div class="col-md-5">
                  <label>Module Name *</label>
                  <input type="text"
                         name="module_name"
                         class="form-control"
                         placeholder="Payroll Run"
                         value="<?= e($data['module_name']) ?>"
                         required>
                </div>

                <div class="col-md-3">
                  <label>Status</label>
                  <select name="status" class="form-control">
                    <option value="active" <?= ($data['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($data['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                  </select>
                </div>
              </div>

              <br>
              <button type="submit" name="save_module" class="btn btn-primary">
                <?= $edit ? 'Update Module' : 'Save Module' ?>
              </button>

              <a href="modules.php" class="btn btn-secondary">Cancel</a>
            </form>

          </div>
        </div>
      </div>
    </section>

  </div>
</div>

<?php include "footer.php"; ?>