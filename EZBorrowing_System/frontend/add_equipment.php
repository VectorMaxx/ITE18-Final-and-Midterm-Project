<?php
session_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['custodian', 'faculty'])) {
    header("Location: equipment_list.php");
    exit();
}

$page_title = 'Add New Equipment';
$firstname = $_SESSION['firstname'];
$profile_pic = $_SESSION['profile_pic'] ?? null;
$profile_path = $profile_pic ? "../" . $profile_pic : "../uploads/images/default-avatar.png";

$error = $success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_clean = trim($_POST['name'] ?? '');
    $name = ucfirst(strtolower($name_clean));
    $description = trim($_POST['description'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);
    $status = $_POST['status'] ?? 'available';
    if ($name === '' || $quantity <= 0) {
        $error = "Equipment name and quantity are required.";
    } else {
        $name_search = strtolower($name_clean);
        $stmt = $conn->prepare("SELECT id, quantity FROM equipment WHERE LOWER(name) = ?");
        $stmt->bind_param("s", $name_search);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $old_quantity);
            $stmt->fetch();
            $new_quantity = $old_quantity + $quantity;
            $update = $conn->prepare("UPDATE equipment SET quantity = ?, description = ?, status = ?, name = ? WHERE id = ?");
            $update->bind_param("isssi", $new_quantity, $description, $status, $name, $id);
            if ($update->execute()) {
                $success = "Equipment quantity updated successfully!";
            } else {
                $error = "Failed to update equipment.";
            }
            $update->close();
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO equipment (name, description, quantity, status) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssis", $name, $description, $quantity, $status);
            if ($stmt_insert->execute()) {
                $success = "Equipment added successfully!";
            } else {
                $error = "Failed to add equipment: " . $conn->error;
            }
            $stmt_insert->close();
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Equipment - EZBorrowing System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/dashboards.css">
    <link rel="stylesheet" href="../assets/add_equipment.css">
</head>
<body>

<div class="container">

    <!-- ✅ ADDED: overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar">

        <!-- ✅ ADDED: close icon -->
        <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">✕</button>

        <h2 class="logo">EZBorrow</h2>
        <ul class="menu">
            <li><a href="dashboard.php">🏠 Dashboard</a></li>
            <li><a href="borrow_items.php">📦 Borrow Items</a></li>
            <li><a href="return_items.php">↩️ Return Items</a></li>
            <li><a href="equipment_list.php">🧾 Equipment List</a></li>
            <li class="active"><a href="add_equipment.php">➕ Add Equipment</a></li>
            <li><a href="history.php">📚 History</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="#" onclick="confirmLogout(event)">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">

                <!-- ✅ existing hamburger -->
                <button class="menu-toggle" id="menuToggle">☰</button>

                <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
                <div class="user-profile">
                    <img src="<?= htmlspecialchars($profile_path) ?>" class="user-avatar" alt="Profile">
                    <span class="user-name"><?= htmlspecialchars($firstname) ?></span>
                </div>
            </div>
        </header>

        <section class="add-equipment-page">
            <h2>Add New Equipment</h2>
            <p class="page-subtitle">
                Enter details below to add a new item to the inventory.<br>
                If the equipment name already exists, its quantity will be increased.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php elseif ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post" class="add-equipment-form">
                <label class="field-label">Equipment Name:</label>
                <input type="text" name="name" required>

                <label class="field-label">Description:</label>
                <input type="text" name="description">

                <label class="field-label">Quantity:</label>
                <input type="number" name="quantity" min="1" required>

                <label class="field-label">Status:</label>
                <select name="status">
                    <option value="available">Available</option>
                    <option value="borrowed">Borrowed</option>
                    <option value="unavailable">Unavailable</option>
                </select>

                <button type="submit" name="add_equipment_form" class="btn-primary">Add Equipment</button>
            </form>

            <a href="equipment_list.php" class="btn-secondary" style="margin-top: 20px;">← Back to Equipment List</a>
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

/* ✅ Sidebar toggle + close (same as other pages) */
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
