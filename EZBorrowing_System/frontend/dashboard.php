<?php
session_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role    = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];

$active      = 'dashboard';
$page_title  = 'Dashboard';
$firstname   = $_SESSION['firstname'];
$profile_pic = $_SESSION['profile_pic'] ?? null;
$profile_path = $profile_pic ? "../" . $profile_pic : "../uploads/images/default-avatar.png";

/* ---------- STAT CARDS (LOGIC) ---------- */

// Available equipment is global for everyone
$availableQuery = $conn->query("SELECT SUM(quantity) AS total FROM equipment WHERE status = 'available'");
$available = $availableQuery ? ($availableQuery->fetch_assoc()['total'] ?? 0) : 0;

// Borrowed & Pending depend on role
if ($role === 'custodian') {
    // Custodian sees ALL records
    $borrowedQuery = $conn->query("SELECT COUNT(*) AS total FROM borrow_records WHERE status = 'borrowed'");
    $pendingQuery  = $conn->query("SELECT COUNT(*) AS total FROM borrow_records WHERE status = 'pending'");
} else {
    // Students / Faculty see ONLY THEIR OWN records
    $borrowedQuery = $conn->query("SELECT COUNT(*) AS total FROM borrow_records WHERE status = 'borrowed' AND user_id = $user_id");
    $pendingQuery  = $conn->query("SELECT COUNT(*) AS total FROM borrow_records WHERE status = 'pending' AND user_id = $user_id");
}

$borrowed = $borrowedQuery ? ($borrowedQuery->fetch_assoc()['total'] ?? 0) : 0;
$pending  = $pendingQuery  ? ($pendingQuery->fetch_assoc()['total']  ?? 0) : 0;

/* ---------- EQUIPMENT TABLE DATA ---------- */

$equipmentList   = [];
$equipmentQuery  = $conn->query("SELECT id, name, quantity, status FROM equipment ORDER BY name ASC");
if ($equipmentQuery && $equipmentQuery->num_rows > 0) {
    while ($row = $equipmentQuery->fetch_assoc()) {
        $equipmentList[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - EZBorrowing System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/dashboards.css">
    <link rel="stylesheet" href="../assets/borrow_items.css">
</head>
<body>

<div class="container">

    <!-- ✅ Overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar">
        <!-- ✅ ADDED: Close icon/button (mobile sidebar hide) -->
        <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">✕</button>

        <h2 class="logo">EZBorrow</h2>
        <ul class="menu">
            <li class="active"><a href="dashboard.php">🏠 Dashboard</a></li>
            <li><a href="borrow_items.php">📦 Borrow Items</a></li>
            <li><a href="return_items.php">↩️ Return Items</a></li>
            <li><a href="equipment_list.php">🧾 Equipment List</a></li>
            <li><a href="history.php">📚 History</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="#" onclick="confirmLogout(event)">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <!-- ✅ Hamburger button -->
            <button class="menu-toggle" id="menuToggle">☰</button>

            <!-- ✅ Wrap title + profile together (same concept as Borrow Items) -->
            <div style="display:flex;align-items:center;justify-content:space-between;flex:1;min-width:0;">

                <div class="page-title"><?= htmlspecialchars($page_title) ?></div>

                <div class="user-profile">
                    <img src="<?= htmlspecialchars($profile_path) ?>" class="user-avatar" alt="Profile">
                    <span class="user-name"><?= htmlspecialchars($firstname) ?></span>
                </div>

            </div>
        </header>


        <section class="welcome">
            <h2>Welcome, <?= htmlspecialchars($firstname) ?>!</h2>
            <div class="stats">
                <div class="card">
                    <h3><?= (int)$available ?></h3>
                    <p>Available Equipment</p>
                </div>
                <div class="card">
                    <h3><?= (int)$borrowed ?></h3>
                    <p>Borrowed</p>
                </div>
                <div class="card">
                    <h3><?= (int)$pending ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
        </section>

        <section class="dashboard">
            <h3>Dashboard</h3>
            <table>
                <thead>
                    <tr>
                        <th>Equipment Name</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th colspan="2">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($equipmentList)): ?>
                        <tr><td colspan="5">No equipment available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($equipmentList as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['quantity']) ?></td>
                                <td>
                                    <?= ($item['status'] === 'available') ? '✅' : '❌'; ?>
                                </td>
                                <td>
                                    <?php if ($role === 'student' || $role === 'faculty'): ?>
                                        <?php if ($item['status'] === 'available' && $item['quantity'] > 0): ?>
                                            <a href="borrow_items.php?id=<?= (int)$item['id'] ?>" class="btn-borrow">Borrow</a>
                                        <?php else: ?>
                                            <button class="btn-borrow" style="background:#e0e0e0;color:#777;cursor:not-allowed;" disabled>
                                                Borrow
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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

/* ✅ Sidebar toggle (hamburger) */
const menuToggle = document.getElementById("menuToggle");
const sidebar = document.querySelector(".sidebar");
const overlay = document.getElementById("sidebarOverlay");

menuToggle.addEventListener("click", () => {
    sidebar.classList.toggle("sidebar-open");
    overlay.classList.toggle("active");
});

overlay.addEventListener("click", () => {
    sidebar.classList.remove("sidebar-open");
    overlay.classList.remove("active");
});

/* ✅ ADDED: Close icon click hides sidebar */
const sidebarClose = document.getElementById("sidebarClose");
sidebarClose.addEventListener("click", () => {
    sidebar.classList.remove("sidebar-open");
    overlay.classList.remove("active");
});
</script>

</body>
</html>
