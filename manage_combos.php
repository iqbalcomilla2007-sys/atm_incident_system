<?php
require_once __DIR__ . '/init.php';

Auth::requirePermission('manage_problem_master');

$message = '';
$masterObj = new MasterData();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ---------- ADD PROBLEM ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_problem'])) {
    $result = $masterObj->saveProblem('add', $_POST);
    if ($result['success']) {
        header("Location: manage_combos.php?success=1");
        exit;
    } else {
        $message = $result['error'];
    }
}

/* ---------- UPDATE PROBLEM ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_problem'])) {
    $result = $masterObj->saveProblem('update', $_POST);
    if ($result['success']) {
        header("Location: manage_combos.php?updated=1");
        exit;
    } else {
        $message = $result['error'];
    }
}

/* ---------- DELETE PROBLEM ---------- */
if (isset($_GET['delete'])) {
    $masterObj->deleteProblem((int)$_GET['delete']);
    header("Location: manage_combos.php?deleted=1");
    exit;
}

if (isset($_GET['success'])) $message = "Problem added successfully!";
if (isset($_GET['updated'])) $message = "Problem updated successfully!";
if (isset($_GET['deleted'])) $message = "Problem deleted successfully!";

/* ---------- FETCH PROBLEMS ---------- */
$result = $masterObj->getAllProblems();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Problem Master</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">

    <div class="hero-header">
        <div>
            <h1>Manage Problem Master</h1>
            <p>Add, update and manage responsible vendor type for each problem</p>
        </div>

        <div class="hero-actions">
            <?php if (Auth::hasPermission('add_incident')) { ?>

            <?php } ?>
            <?php if (Auth::hasPermission('manage_atm_master')) { ?>

            <?php } ?>
            <?php if (Auth::hasPermission('manage_penalty')) { ?>

            <?php } ?>
</div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="form-card">
            <strong><?php echo h($message); ?></strong>
        </div>
    <?php } ?>

    <?php if (isset($_GET['success'])) { ?>
        <div class="form-card">
            <strong style="color:green;">Problem added successfully.</strong>
        </div>
    <?php } ?>

    <?php if (isset($_GET['updated'])) { ?>
        <div class="form-card">
            <strong style="color:green;">Problem updated successfully.</strong>
        </div>
    <?php } ?>

    <?php if (isset($_GET['deleted'])) { ?>
        <div class="form-card">
            <strong style="color:green;">Problem deleted successfully.</strong>
        </div>
    <?php } ?>

    <div class="form-card">
        <h2>Add New Problem</h2>

        <form method="POST">
            <input type="hidden" name="add_problem" value="1">

            <div class="form-grid">
                <div>
                    <label>Problem Name</label>
                    <input type="text" name="problem_name" required>
                </div>

                <div>
                    <label>Responsible Vendor Type</label>
                    <select name="responsible_vendor_type" required>
                        <option value="ATM">ATM</option>
                        <option value="UPS">UPS</option>
                        <option value="NONE">NONE</option>
<option value="NMD">NMD</option>
<option value="ED">ED</option>
<option value="Branch">Branch</option>
<option value="CCTV Vendor">CCTV Vendor</option>
<option value="Outsource">Outsource</option>

                    </select>
                </div>

                <div class="full-width">
                    <button type="submit" class="btn">Add Problem</button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h2>Problem List</h2>

        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Problem Name</th>
                        <th>Responsible Vendor Type</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($result->num_rows > 0) { ?>
                        <?php while ($row = $result->fetch_assoc()) { ?>
                            <tr>
                                <form method="POST">
                                    <input type="hidden" name="update_problem" value="1">
                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">

                                    <td><?php echo (int)$row['id']; ?></td>

                                    <td>
                                        <input type="text" name="problem_name" value="<?php echo h($row['problem_name']); ?>" required>
                                    </td>

                                    <td>
                                        <select name="responsible_vendor_type">
                                            <option value="ATM" <?php if (($row['responsible_vendor_type'] ?? 'ATM') === 'ATM') echo 'selected'; ?>>ATM</option>
                                            <option value="UPS" <?php if (($row['responsible_vendor_type'] ?? '') === 'UPS') echo 'selected'; ?>>UPS</option>
                                            <option value="NONE" <?php if (($row['responsible_vendor_type'] ?? '') === 'NONE') echo 'selected'; ?>>NONE</option>
<option value="NMD" <?php if (($row['responsible_vendor_type'] ?? '') === 'NMD') echo 'selected'; ?>>NMD</option>
<option value="ED" <?php if (($row['responsible_vendor_type'] ?? '') === 'ED') echo 'selected'; ?>>ED</option>
<option value="Branch" <?php if (($row['responsible_vendor_type'] ?? '') === 'Branch') echo 'selected'; ?>>Branch</option>
<option value="CCTV Vendor" <?php if (($row['responsible_vendor_type'] ?? '') === 'CCTV Vendor') echo 'selected'; ?>>CCTV Vendor</option>
<option value="Outsource" <?php if (($row['responsible_vendor_type'] ?? '') === 'Outsource') echo 'selected'; ?>>Outsource</option>

                                        </select>
                                    </td>

                                    <td class="action-cell">
                                        <button type="submit" class="btn">Update</button>
                                        <a class="link-btn delete-btn"
                                           href="manage_combos.php?delete=<?php echo (int)$row['id']; ?>"
                                           onclick="return confirm('Do you want to delete problem: <?php echo addslashes($row['problem_name']); ?>?')">
                                           Delete
                                        </a>
                                    </td>
                                </form>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="4" class="text-center">No problem found.</td>
                        </tr>
                    <?php } ?>
                </tbody>

            </table>
        </div>
    </div>

</div>

</body>
</html>