<?php
session_start();
include '../backend/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$active = 'equipment';
$page_title = 'Equipment List';
$firstname = $_SESSION['firstname'];
$profile_pic = $_SESSION['profile_pic'] ?? null;
$profile_path = $profile_pic ? "../" . $profile_pic : "../uploads/images/default-avatar.png";

$error = $success = "";

if (($role === 'custodian' || $role === 'faculty') && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_equipment_form'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);
    $status = $_POST['status'] ?? 'available';

    if ($name === '' || $quantity <= 0) {
        $error = "Equipment name and quantity are required.";
    } else {
        $name_lower = mb_strtolower($name);
        $stmt = $conn->prepare("SELECT id, quantity FROM equipment WHERE LOWER(name) = ?");
        $stmt->bind_param("s", $name_lower);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $old_quantity);
            $stmt->fetch();
            $new_quantity = $old_quantity + $quantity;
            $update = $conn->prepare("UPDATE equipment SET quantity = ?, description = ?, status = ? WHERE id = ?");
            $update->bind_param("issi", $new_quantity, $description, $status, $id);
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

$equipmentList = [];
$sql = "SELECT 
            eq.id, 
            eq.name, 
            eq.description, 
            eq.quantity, 
            eq.status,
            (SELECT IFNULL(SUM(br.quantity), 0) FROM borrow_records br WHERE br.equipment_id = eq.id AND br.status IN ('pending','borrowed')) AS borrowed_quantity
        FROM equipment eq
        ORDER BY eq.name ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $equipmentList[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Equipment List - EZBorrowing System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/dashboards.css">
    <link rel="stylesheet" href="../assets/equipment_list.css">
    <link rel="stylesheet" href="../assets/add_equipment.css">
</head>
<body>
<div class="container">

    <!-- ✅ ADDED: overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar">

        <!-- ✅ ADDED: Close icon/button (mobile sidebar hide) -->
        <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">✕</button>

        <h2 class="logo">EZBorrow</h2>
        <ul class="menu">
            <li><a href="dashboard.php">🏠 Dashboard</a></li>
            <li><a href="borrow_items.php">📦 Borrow Items</a></li>
            <li><a href="return_items.php">↩️ Return Items</a></li>
            <li class="active"><a href="equipment_list.php">🧾 Equipment List</a></li>

            <?php if ($_SESSION['role'] === 'custodian'): ?>
                <li><a href="add_equipment.php">➕ Add Equipment</a></li>
                <li><a href="marked_summary.php">📦 Marked Summary</a></li>
            <?php endif; ?>

            <li><a href="history.php">📚 History</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="#" onclick="confirmLogout(event)">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">

                <!-- ✅ ADDED: hamburger button (mobile only, controlled by dashboards.css) -->
                <button class="menu-toggle" id="menuToggle">☰</button>

                <div class="page-title"><?= htmlspecialchars($page_title) ?></div>

                <div class="user-profile">
                    <img src="<?= htmlspecialchars($profile_path) ?>" class="user-avatar" alt="Profile">
                    <span class="user-name"><?= htmlspecialchars($firstname) ?></span>
                </div>
            </div>
        </header>
        
        <?php if (isset($_GET['success']) && $_GET['success'] === 'marked'): ?>
            <div class="alert alert-success" style="margin:14px 0 18px 0;">
                ✅ Equipment marked successfully!
            </div>
        <?php endif; ?>

        <section class="page-header" style="position: relative;">
            <div>
                <h2>Equipment List</h2>
                <p class="page-subtitle">View all available equipment in the system.</p>
            </div>
            <div class="equipment-filters right-align">
                <div class="search-bar-container">
                    <span class="search-icon">
                        <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                            <circle cx="10" cy="10" r="7" stroke="#388e3c" stroke-width="2"/>
                            <line x1="16" y1="16" x2="21" y2="21" stroke="#388e3c" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <input type="search" id="searchInput" placeholder="Search equipment...">
                </div>
                <select id="statusFilter">
                    <option value="all">All statuses</option>
                    <option value="available">Available</option>
                    <option value="borrowed">Borrowed</option>
                    <option value="unavailable">Unavailable</option>
                </select>
            </div>
        </section>

        <section class="equipment-section">
            <h3 class="section-title">Equipment Overview</h3>
            <div class="table-wrapper">
                <table class="equipment-table" id="equipmentTable">
                    <thead>
                    <tr>
                        <th>Equipment Name</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <?php if ($role === 'custodian'): ?>
                            <th>Edit</th>
                            <th>Mark</th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>

                    <?php if (empty($equipmentList)): ?>
                        <tr>
                            <td colspan="<?= ($role === 'custodian') ? 6 : 4 ?>" class="empty-row">No equipment found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($equipmentList as $item): ?>
                            <tr data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>"
                                data-status="<?= htmlspecialchars($item['status']) ?>">

                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td><?= (int)$item['quantity'] ?></td>
                                <td>
                                    <?php if ($item['status'] === 'available'): ?>
                                        <span class="status-pill status-available">Available</span>
                                    <?php elseif ($item['status'] === 'borrowed'): ?>
                                        <span class="status-pill status-borrowed">Borrowed</span>
                                    <?php else: ?>
                                        <span class="status-pill status-unavailable">Unavailable</span>
                                    <?php endif; ?>
                                </td>

                                <?php if ($role === 'custodian'): ?>
                                    <td>
                                        <a class="btn-edit" href="edit_equipment.php?id=<?= $item['id'] ?>">Edit</a>
                                    </td>

                                    <!-- NEW MARK SYSTEM -->
                                    <td>
                                        <form method="post" action="../backend/mark_process.php" style="display:flex; align-items:center; gap:8px;">
                                            <input type="hidden" name="equipment_id" value="<?= $item['id'] ?>">

                                            <select name="condition" class="mark-select">
                                                <option value="Damaged">Damaged</option>
                                                <option value="Repairable">Repairable</option>
                                                <option value="Waste">Waste</option>
                                            </select>

                                            <button type="submit" class="btn-mark">Mark</button>
                                        </form>
                                    </td>
                                <?php endif; ?>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
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

/* ✅ Sidebar toggle logic */
const menuToggle = document.getElementById("menuToggle");
const sidebar = document.querySelector(".sidebar");
const overlay = document.getElementById("sidebarOverlay");

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

/* ✅ Close icon click hides sidebar */
const sidebarClose = document.getElementById("sidebarClose");
if (sidebarClose && sidebar && overlay) {
    sidebarClose.addEventListener("click", () => {
        sidebar.classList.remove("sidebar-open");
        overlay.classList.remove("active");
    });
}

/* ✅ ADDED: Equipment filters (Search + Status) */
const searchInput = document.getElementById("searchInput");
const statusFilter = document.getElementById("statusFilter");
const tableRows = document.querySelectorAll("#equipmentTable tbody tr");

function filterEquipmentRows() {
    const term = (searchInput?.value || "").toLowerCase().trim();
    const status = (statusFilter?.value || "all").toLowerCase();

    tableRows.forEach(row => {
        // ignore empty-row or rows without dataset
        if (!row.dataset.name) return;

        const name = row.dataset.name || "";
        const rowStatus = (row.dataset.status || "").toLowerCase();

        const matchesName = name.includes(term);
        const matchesStatus = (status === "all" || rowStatus === status);

        row.style.display = (matchesName && matchesStatus) ? "" : "none";
    });
}

if (searchInput) {
    searchInput.addEventListener("input", filterEquipmentRows);
    searchInput.addEventListener("search", filterEquipmentRows); // handles clear (x) on mobile
}

if (statusFilter) {
    statusFilter.addEventListener("change", filterEquipmentRows);
}
</script>

</body>
</html>
