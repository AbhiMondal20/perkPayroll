<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo "<script>location.href='../index';</script>";
    exit;
}
include("header.php");

// =================== EDIT LOAD ===================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$client_code = $client_name = $phone = $email = $website = $address = "";
$logo = $latter_head = $letter_head_type = "";
$status = "active";

if ($id > 0) {
    $stmt = $master->prepare("
        SELECT id, client_code, client_name, logo, phone, email, website, address,
               letter_head_type, latter_head, status
        FROM clients
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) die("Prepare failed: " . $master->error);

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        die("Client not found!");
    }

    $client_code      = $row['client_code'];
    $client_name      = $row['client_name'];
    $phone            = $row['phone'];
    $email            = $row['email'];
    $website          = $row['website'];
    $address          = $row['address'];
    $logo             = $row['logo'];
    $letter_head_type = $row['letter_head_type'];
    $latter_head      = $row['latter_head'];
    $status           = $row['status'] ?: "active";
}

// =================== SAVE (INSERT/UPDATE) ===================
if (isset($_POST['save'])) {

    $client_code = trim($_POST['client_code'] ?? '');
    $client_name = trim($_POST['client_name'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $website     = trim($_POST['website'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $letter_head_type = trim($_POST['letter_head_type'] ?? '');
    $status      = (($_POST['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';

    // Basic validation
    if ($client_code === '' || $client_name === '' || $phone === '' || $address === '') {
        echo "<script>
            Swal.fire('Error!', 'Client Code, Client Name, Phone and Address are required.', 'error');
        </script>";
    } else {
        // ✅ Duplicate check for client_code
        if ($id > 0) {
            $chk = $master->prepare("SELECT id FROM clients WHERE client_code=? AND id!=? LIMIT 1");
            $chk->bind_param("si", $client_code, $id);
        } else {
            $chk = $master->prepare("SELECT id FROM clients WHERE client_code=? LIMIT 1");
            $chk->bind_param("s", $client_code);
        }

        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $chk->close();
            echo "<script>
                Swal.fire('Duplicate!', 'Client Code already exists.', 'warning');
            </script>";
        } else {
            $chk->close();

            // Uploads folder
            $uploadDir = "upload/";
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            // ✅ Upload Logo (keep old if not uploaded)
            if (!empty($_FILES['logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $allowed = ['png', 'jpg', 'jpeg', 'webp'];
                if (!in_array($ext, $allowed)) {
                    echo "<script>Swal.fire('Error!','Logo must be png/jpg/jpeg/webp','error');</script>";
                } else {
                    $newLogo = $uploadDir . "logo_" . time() . "_" . rand(100,999) . "." . $ext;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $newLogo)) {
                        $logo = $newLogo;
                    }
                }
            }

            // ✅ Upload Letter Head (keep old if not uploaded)
            if (!empty($_FILES['latter_head']['name'])) {
                $ext = strtolower(pathinfo($_FILES['latter_head']['name'], PATHINFO_EXTENSION));
                $allowed = ['png', 'jpg', 'jpeg', 'webp'];
                if (!in_array($ext, $allowed)) {
                    echo "<script>Swal.fire('Error!','Letter Head must be png/jpg/jpeg/webp','error');</script>";
                } else {
                    $newLH = $uploadDir . "lh_" . time() . "_" . rand(100,999) . "." . $ext;
                    if (move_uploaded_file($_FILES['latter_head']['tmp_name'], $newLH)) {
                        $latter_head = $newLH;
                    }
                }
            }

            $now = date("Y-m-d H:i:s");

            // ✅ UPDATE
            if ($id > 0) {
                $stmt = $master->prepare("
                    UPDATE clients SET
                        client_code=?, client_name=?, logo=?, phone=?, email=?, website=?, address=?,
                        letter_head_type=?, latter_head=?, status=?, updated_at=?
                    WHERE id=?
                    LIMIT 1
                ");
                if (!$stmt) die("Update prepare failed: " . $master->error);

                $stmt->bind_param(
                    "sssssssssssi",
                    $client_code, $client_name, $logo, $phone, $email, $website, $address,
                    $letter_head_type, $latter_head, $status, $now, $id
                );

                if ($stmt->execute()) {
                    echo "<script>
                        Swal.fire('Updated!', 'Client updated successfully.', 'success')
                        .then(()=>location.href='clients');
                    </script>";
                    exit;
                } else {
                    echo "<script>Swal.fire('Error!', " . json_encode($stmt->error) . ", 'error');</script>";
                }
                $stmt->close();
            }
            // ✅ INSERT
            else {
                $stmt = $master->prepare("
                    INSERT INTO clients
                        (client_code, client_name, logo, phone, email, website, address, letter_head_type, latter_head, status, created_at)
                    VALUES
                        (?,?,?,?,?,?,?,?,?,?,?)
                ");
                if (!$stmt) die("Insert prepare failed: " . $master->error);

                $stmt->bind_param(
                    "sssssssssss",
                    $client_code, $client_name, $logo, $phone, $email, $website, $address,
                    $letter_head_type, $latter_head, $status, $now
                );

                if ($stmt->execute()) {
                    echo "<script>
                        Swal.fire('Saved!', 'Client added successfully.', 'success')
                        .then(()=>location.href='clients');
                    </script>";
                    exit;
                } else {
                    echo "<script>Swal.fire('Error!', " . json_encode($stmt->error) . ", 'error');</script>";
                }
                $stmt->close();
            }
        }
    }
}
?>

<style>
.preview-img{
    height:80px;
    border:1px solid #ddd;
    padding:3px;
    border-radius:6px;
}
</style>

<div class="content-wrapper">
    <div class="container-full">

        <div class="content-header">
            <a href="client_master"><i class="fa fa-arrow-left"></i> Back</a>
        </div>

        <section class="content">
            <div class="box">
                <div class="card-header bg-primary text-white">
                    <?= $id ? "Edit Client" : "Add Client" ?>
                </div>

                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" autocomplete="off">
                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Code *</label>
                                <input type="text" class="form-control" name="client_code" required
                                       value="<?= htmlspecialchars($client_code) ?>">
                                <small class="text-muted">Example: abc_clinic</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Name *</label>
                                <input type="text" class="form-control" name="client_name" required
                                       value="<?= htmlspecialchars($client_name) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="text" class="form-control" name="phone" maxlength="15" required
                                       value="<?= htmlspecialchars($phone) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email"
                                       value="<?= htmlspecialchars($email) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Website</label>
                                <input type="text" class="form-control" name="website"
                                       value="<?= htmlspecialchars($website) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active"   <?= $status=='active'?'selected':'' ?>>Active</option>
                                    <option value="inactive" <?= $status=='inactive'?'selected':'' ?>>Inactive</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Logo</label>
                                <input type="file" class="form-control" name="logo" accept="image/*">
                                <?php if (!empty($logo)) : ?>
                                    <img src="<?= htmlspecialchars($logo) ?>" class="preview-img mt-2">
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Letter Head Type</label>
                                <select name="letter_head_type" class="form-select select2">
                                    <option value="">Select Letter Head</option>
                                    <optgroup label="A-Series Sizes">
                                        <option value="A4-Portrait" <?= $letter_head_type=='A4-Portrait'?'selected':'' ?>>A4 Portrait – 210 × 297 mm</option>
                                        <option value="A4-Landscape" <?= $letter_head_type=='A4-Landscape'?'selected':'' ?>>A4 Landscape – 297 × 210 mm</option>
                                        <option value="A5-Portrait" <?= $letter_head_type=='A5-Portrait'?'selected':'' ?>>A5 Portrait – 148 × 210 mm</option>
                                        <option value="A5-Landscape" <?= $letter_head_type=='A5-Landscape'?'selected':'' ?>>A5 Landscape – 210 × 148 mm</option>
                                    </optgroup>
                                    <optgroup label="US Standard Sizes">
                                        <option value="Letter-Portrait" <?= $letter_head_type=='Letter-Portrait'?'selected':'' ?>>US Letter Portrait – 216 × 279 mm</option>
                                        <option value="Letter-Landscape" <?= $letter_head_type=='Letter-Landscape'?'selected':'' ?>>US Letter Landscape – 279 × 216 mm</option>
                                        <option value="Legal-Portrait" <?= $letter_head_type=='Legal-Portrait'?'selected':'' ?>>US Legal Portrait – 216 × 356 mm</option>
                                        <option value="Legal-Landscape" <?= $letter_head_type=='Legal-Landscape'?'selected':'' ?>>US Legal Landscape – 356 × 216 mm</option>
                                    </optgroup>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Letter Head Image</label>
                                <input type="file" class="form-control" name="latter_head" accept="image/*">
                                <?php if (!empty($latter_head)) : ?>
                                    <img src="<?= htmlspecialchars($latter_head) ?>" class="preview-img mt-2">
                                <?php endif; ?>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address *</label>
                                <textarea class="form-control" name="address" rows="3" required><?= htmlspecialchars($address) ?></textarea>
                            </div>

                        </div>

                        <button type="submit" class="btn btn-primary btn-sm" name="save">
                            <i class="fa fa-save"></i> <?= $id ? "Update" : "Save" ?>
                        </button>
                        <a href="client_master" class="btn btn-secondary btn-sm">Cancel</a>

                    </form>
                </div>

            </div>
        </section>

    </div>
</div>

<?php include("footer.php"); ?>