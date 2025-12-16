<?php
session_start();
include '../backend/db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'custodian' && $_SESSION['role'] !== 'faculty')) {
    header("Location: equipment_list.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: equipment_list.php"); exit(); }

$error = '';
$success = '';
$stmt = $conn->prepare("SELECT name, description, quantity, status FROM equipment WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($name, $description, $quantity, $status);
if (!$stmt->fetch()) {
    $stmt->close();
    $conn->close();
    header("Location: equipment_list.php");
    exit();
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name'] ?? '');
    $new_desc = trim($_POST['description'] ?? '');
    $new_status = $_POST['status'] ?? 'available';

    if ($new_status === 'unavailable') {
        $new_quantity = 0;
    } else {
        $new_quantity = intval($_POST['quantity'] ?? 0);
        if ($new_quantity <= 0) {
            $error = "Quantity must be at least 1 for Available or Borrowed items.";
        }
    }

    if ($new_name === '') {
        $error = "Name is required.";
    }

    if (!$error) {
        $update = $conn->prepare("UPDATE equipment SET name=?, description=?, quantity=?, status=? WHERE id=?");
        $update->bind_param("ssisi", $new_name, $new_desc, $new_quantity, $new_status, $id);
        if ($update->execute()) {
            header("Location: equipment_list.php?success=updated");
            exit();
        } else {
            $error = "Update failed. Please try again.";
        }
        $update->close();
    }
}
$conn->close();
$page_title = "Edit Equipment";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Equipment - EZBorrowing System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/dashboards.css">
    <link rel="stylesheet" href="../assets/add_equipment.css">
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var statusSelect = document.getElementsByName('status')[0];
        var quantityInput = document.getElementsByName('quantity')[0];

        function handleStatusChange() {
            if (statusSelect.value === 'unavailable') {
                quantityInput.min = 0;
                quantityInput.required = false;
                quantityInput.value = '';
                quantityInput.disabled = true;
            } else {
                quantityInput.min = 1;
                quantityInput.required = true;
                quantityInput.disabled = false;
            }
        }
        statusSelect.addEventListener('change', handleStatusChange);
        handleStatusChange();
    });
    </script>
</head>
<body>

<div class="container">

    <!-- ✅ ADDED: overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar">

        <!-- ✅ ADDED: close icon -->
        <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">✕</button>

        <h2 class="logo">EZBorrow</h2>
        <ul class="menu">
            <li><a href="dashboard.php">🏠 Dashboard</a></li>
            <li><a href="borrow_items.php">📦 Borrow Items</a></li>
            <li><a href="return_items.php">↩️ Return Items</a></li>
            <li class="active"><a href="equipment_list.php">🧾 Equipment List</a></li>
            <li><a href="history.php">📚 History</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="#" onclick="confirmLogout(event)">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">

                <!-- existing hamburger -->
                <button class="menu-toggle" id="menuToggle">☰</button>

                <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
                <div class="user-profile">
                    <img src="<?= htmlspecialchars($_SESSION['profile_pic'] ?? '../uploads/images/default-avatar.png') ?>" class="user-avatar" alt="Profile">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['firstname']) ?></span>
                </div>
            </div>
        </header>

        <section class="add-equipment-page">
            <h2>Edit Equipment</h2>
            <p class="page-subtitle">Modify the details of this equipment item.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" class="add-equipment-form">
                <label class="field-label">Equipment Name:</label>
                <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>

                <label class="field-label">Description:</label>
                <input type="text" name="description" value="<?= htmlspecialchars($description) ?>">

                <label class="field-label">Quantity:</label>
                <input type="number" name="quantity"
                    min="<?= $status==='unavailable' ? '0' : '1' ?>"
                    value="<?= (int)$quantity ?>"
                    <?= $status==='unavailable' ? 'disabled' : 'required' ?>>

                <label class="field-label">Status:</label>
                <select name="status">
                    <option value="available" <?= $status==='available' ? 'selected' : '' ?>>Available</option>
                    <option value="borrowed" <?= $status==='borrowed' ? 'selected' : '' ?>>Borrowed</option>
                    <option value="unavailable" <?= $status==='unavailable' ? 'selected' : '' ?>>Unavailable</option>
                </select>

                <button type="submit" class="btn-primary">Save Changes</button>
            </form>

            <a href="equipment_list.php" class="btn-secondary" style="margin-top:20px;">← Back to Equipment List</a>
        </section>
    </main>
</div>

<script>
function confirmLogout(event) {
    event.preventDefault();
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = "logout.php";
    }
}

/* ✅ Sidebar toggle + close */
const menuToggle = document.getElementById("menuToggle");
const sidebar = document.querySelector(".sidebar");
const overlay = document.getElementById("sidebarOverlay");
const sidebarClose = document.getElementById("sidebarClose");

if (menuToggle && sidebar && overlay) {
    menuToggle.addEventListener("click", () => {
        sidebar.classList.toggle("sidebar-open");
        overlay.classList.toggle("active");
    });

    overlay.addEventListener("click", () => {
        sidebar.classList.remove("sidebar-open");
        overlay.classList.remove("active");
    });
}

if (sidebarClose && sidebar && overlay) {
    sidebarClose.addEventListener("click", () => {
        sidebar.classList.remove("sidebar-open");
        overlay.classList.remove("active");
    });
}
</script>

</body>
</html>
