<?php
session_start();
include '../backend/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$page_title = 'Borrow Items';
$firstname = $_SESSION['firstname'];
$profile_pic = $_SESSION['profile_pic'] ?? null;
$profile_path = $profile_pic ? "../" . $profile_pic : "../uploads/images/default-avatar.png";

$equipmentList = [];
if ($role === 'student' || $role === 'faculty') {
    $result = $conn->query("SELECT id, name, description, quantity, status FROM equipment WHERE quantity > 0 ORDER BY name ASC");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $equipmentList[] = $row;
        }
    }
}

$requests = [];
$borrowed = [];
if ($role === 'custodian') {
    $sql = "SELECT 
                br.id as req_id,
                eq.name,
                eq.description,
                br.quantity,
                br.status as req_status,
                u.firstname,
                u.lastname,
                br.date_borrowed
            FROM borrow_records br
            INNER JOIN equipment eq ON br.equipment_id = eq.id
            INNER JOIN users u ON br.user_id = u.id
            WHERE br.status = 'pending'
            ORDER BY br.date_borrowed DESC";
    $r = $conn->query($sql);
    if ($r && $r->num_rows > 0) {
        while ($row = $r->fetch_assoc()) $requests[] = $row;
    }
    $sql_b = "SELECT br.id as borrow_id,
                u.firstname,
                u.lastname,
                eq.name AS equipment_name,
                br.quantity,
                br.date_borrowed
              FROM borrow_records br
              JOIN users u ON br.user_id = u.id
              JOIN equipment eq ON br.equipment_id = eq.id
              WHERE br.status = 'borrowed'
              ORDER BY br.date_borrowed DESC";
    $res_b = $conn->query($sql_b);
    if ($res_b && $res_b->num_rows > 0) {
        while ($row = $res_b->fetch_assoc()) $borrowed[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Borrow Items - EZBorrowing System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/dashboards.css">
    <link rel="stylesheet" href="../assets/borrow_items.css">
</head>
<body>
<div class="container">

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar">
        <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">✕</button>

        <h2 class="logo">EZBorrow</h2>
        <ul class="menu">
            <li><a href="dashboard.php">🏠 Dashboard</a></li>
            <li class="active"><a href="borrow_items.php">📦 Borrow Items</a></li>
            <li><a href="return_items.php">↩️ Return Items</a></li>
            <li><a href="equipment_list.php">🧾 Equipment List</a></li>
            <li><a href="history.php">📚 History</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="#" onclick="confirmLogout(event)">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
                <div class="user-profile">
                    <img src="<?= htmlspecialchars($profile_path) ?>" class="user-avatar" alt="Profile">
                    <span class="user-name"><?= htmlspecialchars($firstname) ?></span>
                </div>
            </div>
        </header>
        
        <?php if (isset($_GET['error']) && $_GET['error'] === 'duplicate'): ?>
            <div class="alert alert-error" style="...">
                ⚠️ You can only borrow one equipment name at a time. Please wait until your current request is processed or returned.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success']==='approved'): ?>
                <div class="alert alert-success" style="margin:14px 0 18px 0; border-radius:8px; font-weight:600;">
                    ✅ Request approved successfully!
                </div>
            <?php elseif ($_GET['success']==='declined'): ?>
                <div class="alert alert-error" style="margin:14px 0 18px 0; border-radius:8px; font-weight:600;">
                    ❌ Request declined.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <section class="page-header">
            <div>
                <h2>Borrow Items</h2>
                <p class="page-subtitle">
                <?php if ($role === 'student' || $role === 'faculty'): ?>
                    Select equipment you want to borrow from the list below.
                <?php else: ?>
                    View and process pending borrow requests from students and faculty.<br>
                    See below for all items currently out.
                <?php endif; ?>
                </p>
            </div>

            <?php if ($role === 'student' || $role === 'faculty'): ?>
            <div class="borrow-filters right-align">
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
            <?php endif; ?>
        </section>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'pending' && ($role==='student'||$role==='faculty')): ?>
            <div class="alert alert-success borrow-success">
                <span class="alert-icon">🎉</span>
                <span>
                    Request sent! Please wait for custodian approval.
                    <a href="history.php">Check your History.</a>
                </span>
            </div>
        <?php endif; ?>

        <?php if ($role === 'student' || $role === 'faculty'): ?>
        <section>
            <h3 style="margin-bottom:12px;">Equipment List</h3>
            <div class="table-wrapper">
                <table class="borrow-table" id="borrowTable">
                    <thead>
                        <tr>
                            <th>Equipment Name</th>
                            <th>Description</th>
                            <th>Available Qty</th>
                            <th>Status</th>
                            <th>Quantity / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($equipmentList)): ?>
                        <tr>
                            <td colspan="5" class="empty-row">No equipment available to borrow.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($equipmentList as $item): ?>
                        <tr data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>" data-status="<?= htmlspecialchars($item['status']) ?>">
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
                            <td>
                                <?php if ($item['status'] === 'available' && $item['quantity'] > 0): ?>
                                    <form method="post" action="../backend/borrow_process.php" style="display:inline-flex; gap:7px; align-items:center;">
                                        <button type="button" class="quantity-btn" onclick="stepQty(this, -1)" tabindex="-1">−</button>
                                        <input type="number" min="1" max="<?= (int)$item['quantity'] ?>" name="quantity" value="1" style="width:44px;text-align:center;">
                                        <button type="button" class="quantity-btn" onclick="stepQty(this, 1)" tabindex="-1">+</button>
                                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="btn-borrow">Borrow</button>
                                    </form>
                                <?php else: ?>
                                    <!-- ✅ FIX 1: removed the highlighted text -->
                                    <!-- ✅ FIX 2: center the Unavailable button -->
                                    <button class="btn-borrow" style="background: #ccc; color: #888; cursor: not-allowed; display:block; margin:0 auto;" disabled>
                                        Unavailable
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($role === 'custodian'): ?>
        <section style="margin-top:1.2em;">
            <h3>Pending Borrow Requests</h3>
            <div class="table-wrapper">
                <table class="borrow-table" id="requestsTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Equipment</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Date Requested</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="7" class="empty-row">No pending requests.</td>
                            </tr>
                        <?php else: foreach ($requests as $req): ?>
                            <tr>
                                <td><?= htmlspecialchars($req['firstname'] . " " . $req['lastname']) ?></td>
                                <td><?= htmlspecialchars($req['name']) ?></td>
                                <td><?= htmlspecialchars($req['description']) ?></td>
                                <td><?= (int)$req['quantity'] ?></td>
                                <td><?= htmlspecialchars(date("Y-m-d H:i", strtotime($req['date_borrowed']))) ?></td>
                                <td><?= htmlspecialchars(ucfirst($req['req_status'])) ?></td>
                                <td>
                                    <form method="post" action="../backend/process_approval.php" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?= (int)$req['req_id'] ?>">
                                        <div style="display:flex; gap:8px; align-items:center; justify-content:flex-start;">
                                            <button name="approve" value="1" class="btn-approve">Approve</button>
                                            <button name="decline" value="1" class="btn-decline">Decline</button>
                                        </div>
                                    </form>
                                </td>

                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section style="margin-top:2.2em;">
            <h3>Currently Borrowed Items</h3>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Equipment</th>
                            <th>Quantity</th>
                            <th>Date Borrowed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($borrowed)): ?>
                            <tr><td colspan="4" class="empty-row">No items currently borrowed.</td></tr>
                        <?php else: foreach ($borrowed as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['firstname']." ".$row['lastname']) ?></td>
                                <td><?= htmlspecialchars($row['equipment_name']) ?></td>
                                <td><?= (int)$row['quantity'] ?></td>
                                <td><?= htmlspecialchars(date("Y-m-d H:i", strtotime($row['date_borrowed']))) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>

<script>
function confirmLogout(event) {
    event.preventDefault();
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = "logout.php";
    }
}
function stepQty(btn, delta) {
    var input = btn.parentNode.querySelector('input[type="number"]');
    var min = parseInt(input.min, 10)||1, max = parseInt(input.max,10);
    var val = parseInt(input.value,10)||min;
    val = Math.max(min, Math.min(max, val + delta));
    input.value = val;
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

<?php if ($role === 'student' || $role === 'faculty'): ?>
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const rows = document.querySelectorAll('#borrowTable tbody tr');
function filterRows() {
    const term = searchInput.value.toLowerCase();
    const stat = statusFilter.value;
    rows.forEach(row => {
        const name = row.dataset.name;
        const status = row.dataset.status;
        const matchesName = name.includes(term);
        const matchesStatus = (stat === 'all' || stat === status);
        row.style.display = (matchesName && matchesStatus) ? '' : 'none';
    });
}
if (searchInput) searchInput.addEventListener('input', filterRows);
if (statusFilter) statusFilter.addEventListener('change', filterRows);
<?php endif; ?>
</script>
</body>
</html>
