<?php
session_start();
// 🔐 Super admin protection
if (!isset($_SESSION['login']) || ($_SESSION['super_admin'] ?? false) !== true) {
    header("Location: index");
    exit;
}

include "header.php";

if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection (\$master) not found. Check db_conn");
}

// ================= DELETE =================
if (isset($_POST['action']) && $_POST['action'] === 'delete_module') {
    header('Content-Type: application/json; charset=utf-8');

    // 🔐 Super admin check
    if (!isset($_SESSION['login']) || ($_SESSION['super_admin'] ?? false) !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    // ✅ Get ID from POST (not GET)
    $deleteId = (int)($_POST['id'] ?? 0);

    if ($deleteId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }

    $del = $master->prepare("DELETE FROM modules WHERE id = ? LIMIT 1");
    if (!$del) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: '.$master->error]);
        exit;
    }

    $del->bind_param("i", $deleteId);

    if ($del->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Delete failed: '.$del->error]);
    }

    $del->close();
    exit; // ✅ very important
}


// ================= LIST =================
$stmt = $master->prepare("
   SELECT `id`, `module_key`, `module_name`, `status` FROM `modules`
");
if (!$stmt) {
    die("List prepare failed: " . $master->error);
}
$stmt->execute();
$res = $stmt->get_result();

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
                <div>
                    <a href="add_edit_module" class="btn btn-info btn-sm waves-effect waves-light">
                        <i class="fa-solid fa-circle-plus"></i> Add New
                    </a>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="row">
                <div class="box">
                    <div class="card-header bg-primary text-white">
                       <i class="fa-solid fa-clipboard-list me-1"></i> Module Master
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table class="display margin-top-10 nowrap table table-hover w-p100" id="example">
                                <thead>
                                    <tr>
                                        <th>Sl. No.</th>
                                        <th>Module Key</th>
                                        <th>Module Name</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        $sl = 1;
                                        while ($row = $res->fetch_assoc()):
                                            $id = (int)$row['id'];
                                    ?>
                                    <tr>
                                        <td><?= $sl++; ?></td>
                                        <td><?= htmlspecialchars($row['module_key']); ?></td>
                                        <td><?= htmlspecialchars($row['module_name']); ?></td>
                                        <td>
                                            <?php if (($row['status'] ?? '') === 'active'): ?>
                                            <span class="badge badge-success">active</span>
                                            <?php else: ?>
                                            <span class="badge badge-danger">inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="add_edit_module?id=<?= $id; ?>" class="btn btn-sm btn-info">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </a>
                                            <a href="#" class="btn btn-sm btn-danger"
                                            onclick="deleteModule(<?= (int)$id ?>); return false;">
                                            <i class="fa-solid fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
function deleteModule(id) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'This action cannot be undone!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      fetch('', {   // 👈 SAME PAGE
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          action: 'delete_module',
          id: id
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: data.message,
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
           window.location.href = window.location.href;
          });
        } else {
          Swal.fire('Error', data.message, 'error');
        }
      });
    }
  });
}
</script>


<?php
$stmt->close();
include "footer.php";
?>