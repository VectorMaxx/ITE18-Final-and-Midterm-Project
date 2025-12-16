<?php
session_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role        = $_SESSION['role'];
$user_id     = $_SESSION['user_id'];
$firstname   = $_SESSION['firstname'];
$profile_pic = $_SESSION['profile_pic'] ?? null;
$profile_path = $profile_pic ? "../" . $profile_pic : "../uploads/images/default-avatar.png";
$page_title  = 'Return Items';

/* ------------------- DATA QUERIES ------------------- */
if ($role === 'custodian') {
    // Custodian: see all returned items, optional condition filter
    $conditionFilter = $_GET['condition'] ?? 'all';
    $returned = [];

    $sql = "SELECT ri.id,
                   u.firstname,
                   u.lastname,
                   eq.name AS equipment_name,
                   ri.quantity_returned,
                   ri.date_returned,
                   ri.item_condition
            FROM returned_items ri
            JOIN equipment eq ON ri.equipment_id = eq.id
            JOIN users u ON ri.user_id = u.id";

    if ($conditionFilter !== 'all') {
        $sql .= " WHERE ri.item_condition = ?";
    }
    $sql .= " ORDER BY ri.date_returned DESC";

    if ($conditionFilter !== 'all') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $conditionFilter);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $returned[] = $row;
        }
    }
    if ($result instanceof mysqli_result) {
        $result->close();
    }
} else {
    // Student / faculty: see their own borrowed items
    $borrowed = [];
    $sql = "SELECT br.id,
                   br.equipment_id,
                   eq.name AS equipment_name,
                   br.quantity,
                   br.date_borrowed
            FROM borrow_records br
            JOIN equipment eq ON br.equipment_id = eq.id
            WHERE br.user_id = ? AND br.status = 'borrowed'
            ORDER BY br.date_borrowed DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $borrowed[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Return Items - EZBorrowing System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/dashboards.css">
    <link rel="stylesheet" href="../assets/return_items.css">
</head>
<body>
<div class="container">

    <!-- ✅ ADDED: Overlay for mobile sidebar (required for the toggle script) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar">

        <!-- ✅ ADDED: Close icon/button (mobile sidebar hide) -->
        <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">✕</button>

        <h2 class="logo">EZBorrow</h2>
        <ul class="menu">
            <li><a href="dashboard.php">🏠 Dashboard</a></li>
            <li><a href="borrow_items.php">📦 Borrow Items</a></li>
            <li class="active"><a href="return_items.php">↩️ Return Items</a></li>
            <li><a href="equipment_list.php">🧾 Equipment List</a></li>
            <li><a href="history.php">📚 History</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="#" onclick="confirmLogout(event)">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
                <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
                <div class="user-profile">
                    <img src="<?= htmlspecialchars($profile_path) ?>" class="user-avatar" alt="Profile">
                    <span class="user-name"><?= htmlspecialchars($firstname) ?></span>
                </div>
            </div>
        </header>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'returned'): ?>
            <div class="return-success-banner">
                <span class="icon">✅</span>
                <span>Your item has been returned successfully.</span>
            </div>
        <?php endif; ?>

        <section class="page-header">
            <div>
                <h2>Return Items</h2>
                <p class="page-subtitle">
                    <?php if ($role === 'custodian'): ?>
                        View all returned items for all users.
                    <?php else: ?>
                        Review the items you borrowed, choose their condition, and mark them for return.
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($role !== 'custodian'): ?>
                <div class="right-align">
                    <div class="search-bar-container">
                        <span class="search-icon">
                            <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                                <circle cx="10" cy="10" r="7" stroke="#388e3c" stroke-width="2"/>
                                <line x1="16" y1="16" x2="21" y2="21" stroke="#388e3c" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <input type="search" id="searchReturnInput" placeholder="Search borrowed equipment..." class="search-input">
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section>
            <?php if ($role === 'custodian'): ?>
                <h3 style="margin-bottom:12px;">Returned Items</h3>

                <!-- Condition filter for custodian -->
                <form method="get" class="condition-filter-form">
                    <label for="conditionFilter">Filter by condition:</label>
                    <select id="conditionFilter" name="condition" class="condition-select" onchange="this.form.submit()">
                        <option value="all" <?= ($conditionFilter ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="Good" <?= ($conditionFilter ?? 'all') === 'Good' ? 'selected' : '' ?>>Good</option>
                        <option value="Fair" <?= ($conditionFilter ?? 'all') === 'Fair' ? 'selected' : '' ?>>Fair</option>
                        <option value="Damaged" <?= ($conditionFilter ?? 'all') === 'Damaged' ? 'selected' : '' ?>>Damaged</option>
                    </select>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Equipment Name</th>
                                <th>Quantity Returned</th>
                                <th>Date Returned</th>
                                <th>Condition</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($returned)): ?>
                                <tr>
                                    <td colspan="5" class="empty-row">No returned items yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($returned as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['firstname'] . " " . $row['lastname']) ?></td>
                                        <td><?= htmlspecialchars($row['equipment_name']) ?></td>
                                        <td><?= (int)$row['quantity_returned'] ?></td>
                                        <td><?= htmlspecialchars(date("Y-m-d H:i", strtotime($row['date_returned']))) ?></td>
                                        <td><?= htmlspecialchars($row['item_condition'] ?: '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <h3 style="margin-bottom:12px;">Borrowed Items</h3>
                <div class="table-wrapper">
                    <table id="returnTable">
                        <thead>
                            <tr>
                                <th>Equipment Name</th>
                                <th>Quantity</th>
                                <th>Date Borrowed</th>
                                <th>Status</th>
                                <th>Condition</th>
                                <th class="return-action-col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($borrowed)): ?>
                                <tr>
                                    <td colspan="6" class="empty-row">You have no items currently borrowed.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($borrowed as $row): ?>
                                    <?php $rowId = (int)$row['id']; ?>
                                    <tr data-name="<?= htmlspecialchars(strtolower($row['equipment_name'])) ?>">
                                        <td><?= htmlspecialchars($row['equipment_name']) ?></td>
                                        <td><?= (int)$row['quantity'] ?></td>
                                        <td><?= htmlspecialchars(date("Y-m-d H:i", strtotime($row['date_borrowed']))) ?></td>
                                        <td><span style="color:#188a28;font-weight:600;">Borrowed</span></td>
                                        <td>
                                            <!-- Select is visually in the Condition column, but belongs to the form via 'form' attribute -->
                                            <select name="item_condition" class="condition-select" form="returnForm<?= $rowId ?>">
                                                <option value="Good">Good</option>
                                                <option value="Fair">Fair</option>
                                                <option value="Damaged">Damaged</option>
                                            </select>
                                        </td>
                                        <td class="return-action-col">
                                            <form method="post"
                                                  action="../backend/return_process.php"
                                                  id="returnForm<?= $rowId ?>">
                                                <input type="hidden" name="borrow_id" value="<?= $rowId ?>">
                                                <input type="hidden" name="equipment_id" value="<?= (int)$row['equipment_id'] ?>">
                                                <input type="hidden" name="quantity" value="<?= (int)$row['quantity'] ?>">
                                                <button type="submit" class="btn-borrow">Return</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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

<?php if ($role !== 'custodian'): ?>
const searchReturnInput = document.getElementById('searchReturnInput');
const returnRows = document.querySelectorAll('#returnTable tbody tr');

function filterReturns() {
    const term = searchReturnInput.value.toLowerCase();
    returnRows.forEach(row => {
        const name = row.dataset.name || '';
        row.style.display = name.includes(term) ? '' : 'none';
    });
}
if (searchReturnInput) searchReturnInput.addEventListener('input', filterReturns);
<?php endif; ?>
</script>
</body>
</html>
