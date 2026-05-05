<?php
session_start();
if (!isset($_SESSION['login']) || ($_SESSION['super_admin'] ?? false) !== true) {
    header("Location: index");
    exit;
}
include "header.php";

if (
    isset($_POST['action']) &&
    $_POST['action'] === 'delete_client_db'
) {
    header('Content-Type: application/json');

    // 🔐 Super admin check
    if (!isset($_SESSION['login']) || ($_SESSION['super_admin'] ?? false) !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $deleteId = (int)($_POST['id'] ?? 0);

    if ($deleteId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }

    $stmt = $master->prepare("DELETE FROM client_databases WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $deleteId);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Deleted successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Delete failed'
        ]);
    }
}


?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="container-full">

            <!-- Header -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <a href="#" onclick="history.back()" aria-label="Go back">
                            <i class="fa fa-arrow-left"></i> Back
                        </a>
                    </div>
                    <div>
                        <a href="add_edit_client_databases" class="btn btn-info btn-sm waves-effect waves-light">
                            <i class="fa-solid fa-circle-plus"></i> Add New
                        </a>
                    </div>
                </div>
            </div>
            <!-- ADD / EDIT FORM -->
            <section class="content">
                <div class="row">
                    <div class="box">
                        <div class="card-header bg-primary text-white">
                        <i class="fa-solid fa-clipboard-list me-1"></i> Client Databases
                        </div>
                        <div class="box-body">
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table id="example" class="table table-hover display nowrap w-p100">
                                    <thead>
                                        <tr>
                                            <th>Sl. No.</th>
                                            <th>Client Code</th>
                                            <th>Module Key</th>
                                            <th>Host</th>
                                            <th>DB Name</th>
                                            <th>User Name</th>
                                            <th>Password</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($_POST['search'])) {
                                                    $sql = "SELECT `id`, `client_code`, `module_key`, `db_host`, `db_name`, `db_user`, `db_pass`, `status`, `created_at`, `updated_at` FROM `client_databases` WHERE client_code LIKE '%" . $master->real_escape_string($_POST['search_term']) . "%' OR module_key LIKE '%" . $master->real_escape_string($_POST['search_term']) . "%'";
                                                } else {
                                                    $sql = "SELECT id, client_code, module_key, db_host, db_name, db_user, db_pass, status, created_at, updated_at FROM client_databases";
                                                }
                                                $stmt = mysqli_query($master, $sql);
                                                if ($stmt === false) {
                                                    die("Error executing query: " . mysqli_error($master));
                                                }
                                                while ($row = mysqli_fetch_array($stmt)) {
                                                    $id = $row['id'];
                                                    $client_code = $row['client_code'];
                                                    $module_key = $row['module_key'];
                                                    $db_host = $row['db_host'];
                                                    $db_name = $row['db_name'];
                                                    $db_user = $row['db_user'];
                                                    $db_pass = $row['db_pass'];
                                                    $status = $row['status'];

                                                    ?>
                                        <tr>
                                            <td><?php echo $id; ?></td>
                                            <td><?php echo $client_code; ?></td>
                                            <td><?php echo $module_key; ?></td>
                                            <td><?php echo $db_host; ?></td>
                                            <td><?php echo $db_name; ?></td>
                                            <td><?php echo $db_user; ?></td>
                                        <td>
                                            <span id="pass_<?php echo $id; ?>">****</span>
                                            <button type="button"
                                                onclick="togglePass('<?php echo addslashes($db_pass); ?>','pass_<?php echo $id; ?>', this)"
                                                style="border:none;background:none;cursor:pointer;">
                                                👁️
                                            </button>
                                        </td>
                                            <td><?php echo $status; ?></td>
                                            <td class="text-center">
                                                <a href="add_edit_client_databases?id=<?php echo $id; ?>"
                                                    class="btn btn-sm btn-info">
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                                </a>
                                                <a href="#" class="btn btn-sm btn-danger"
                                                onclick="deleteClientDatabase(<?= (int)$id ?>); return false;">
                                                <i class="fa-solid fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr><?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
function deleteClientDatabase(id) {
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
          action: 'delete_client_db',
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
            window.location.href = window.location.href; // 🔄 Refresh page;
          });
        } else {
          Swal.fire('Error', data.message, 'error');
        }
      });
    }
  });
}
</script>


<script>
function togglePass(realPass, spanId, btn) {
    const span = document.getElementById(spanId);

    if (span.innerText === '****') {
        span.innerText = realPass;
        btn.innerText = '🙈';
    } else {
        span.innerText = '****';
        btn.innerText = '👁️';
    }
}
</script>
<?php include "footer.php"; ?>