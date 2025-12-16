<?php
session_start();
include '../backend/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$active = 'history';
$page_title = 'Borrow & Return History';
$firstname = $_SESSION['firstname'];
$profile_pic = $_SESSION['profile_pic'] ?? null;
$profile_path = $profile_pic ? "../" . $profile_pic : "../uploads/images/default-avatar.png";

$borrow_records = [];

/* FETCH HISTORY (custodian = all, others = own) */

if ($role === 'custodian') {
    $sql = "
        SELECT 
            br.id,
            u.firstname,
            u.lastname,
            eq.name AS equipment_name,
            br.quantity,
            br.date_borrowed,
            br.status,
            ri.date_returned,
            ri.item_condition
        FROM borrow_records br
        JOIN users u ON br.user_id = u.id
        JOIN equipment eq ON br.equipment_id = eq.id
        LEFT JOIN returned_items ri ON ri.borrow_id = br.id
        ORDER BY br.date_borrowed DESC
    ";
} else {
    $user_id = $_SESSION['user_id'];
    $sql = "
        SELECT 
            br.id,
            u.firstname,
            u.lastname,
            eq.name AS equipment_name,
            br.quantity,
            br.date_borrowed,
            br.status,
            ri.date_returned,
            ri.item_condition
        FROM borrow_records br
        JOIN users u ON br.user_id = u.id
        JOIN equipment eq ON br.equipment_id = eq.id
        LEFT JOIN returned_items ri ON ri.borrow_id = br.id
        WHERE br.user_id = $user_id
        ORDER BY br.date_borrowed DESC
    ";
}

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

        // Normalize Status
        if (isset($row['status'])) {
            $row['status'] = strtolower(trim($row['status']));  
        }

        // Normalize Date Returned
        if (!isset($row['date_returned']) || trim($row['date_returned']) === "") {
            $row['date_returned'] = null;
        }

        $borrow_records[] = $row;
    }
}
$conn->close();

/* BUILD MONTH/YEAR FILTER LISTS */

$months_available = [];
$years_available  = [];

foreach ($borrow_records as $b) {
    if (!empty($b['date_borrowed'])) {
        $ts = strtotime($b['date_borrowed']);
        $m_num = (int)date('n', $ts);
        $m_name = date('F', $ts);
        $y = date('Y', $ts);
        $months_available[$m_num] = $m_name;
        $years_available[$y] = true;
    }
}
ksort($months_available);
ksort($years_available);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Borrow & Return History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/dashboards.css">
    <link rel="stylesheet" href="../assets/history.css">
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
            <li><a href="equipment_list.php">🧾 Equipment List</a></li>
            <li class="active"><a href="history.php">📚 All History</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="#" onclick="confirmLogout(event)">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div style="display:flex;justify-content:space-between;width:100%;align-items:center;">

                <!-- ✅ ADDED: hamburger button (mobile only; dashboards.css controls visibility) -->
                <button class="menu-toggle" id="menuToggle">☰</button>

                <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
                <div class="user-profile">
                    <img src="<?= htmlspecialchars($profile_path) ?>" class="user-avatar">
                    <span class="user-name"><?= htmlspecialchars($firstname) ?></span>
                </div>
            </div>
        </header>

        <section class="page-header">
            <div>
                <h2>Borrow & Return History</h2>
                <p class="page-subtitle">Use filters to find records by user, equipment, or status.</p>
            </div>

            <div class="return-filters">
                <input type="search" id="searchInput" placeholder="Search user or equipment...">

                <select id="statusFilter">
                    <option value="all">All statuses</option>
                    <option value="borrowed">Borrowed</option>
                    <option value="returned">Returned</option>
                    <option value="declined">Declined</option>
                    <option value="pending">Pending</option>
                </select>

                <select id="monthFilter">
                    <option value="all">All months</option>
                    <?php foreach ($months_available as $m_num => $m_name): ?>
                        <option value="<?= $m_num ?>"><?= $m_name ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="yearFilter">
                    <option value="all">All years</option>
                    <?php foreach ($years_available as $year => $_): ?>
                        <option value="<?= $year ?>"><?= $year ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if ($_SESSION['role'] === 'custodian'): ?>
                    <button type="button" id="printBtn" class="history-action-btn">Print Report</button>
                    <form id="exportForm" method="get" action="../backend/export_history_excel.php" target="_blank">
                        <input type="hidden" name="status" id="exportStatus">
                        <input type="hidden" name="month" id="exportMonth">
                        <input type="hidden" name="year" id="exportYear">
                        <button type="submit" class="history-action-btn">Export Excel</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section>
            <h3 class="section-title">Borrowed Items</h3>

            <div class="table-wrapper">
                <table class="return-table" id="historyTable">
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>Equipment</th>
                            <th>Quantity</th>
                            <th>Date Borrowed</th>
                            <th>Date Returned</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($borrow_records)): ?>
                        <tr><td colspan="6" class="empty-row">No history found.</td></tr>
                    <?php else: ?>

                        <?php
                        $current_month = "";
                        foreach ($borrow_records as $b):
                            $record_month = date("F Y", strtotime($b['date_borrowed']));
                            $ts = strtotime($b['date_borrowed']);
                            $row_month = date('n', $ts);
                            $row_year  = date('Y', $ts);
                        ?>

                            <?php if ($record_month !== $current_month): ?>
                                <tr style="background:#e8f5e9;">
                                    <td colspan="6" style="font-weight:700;color:#2e7d32;padding:12px;">
                                        📅 <?= $record_month ?>
                                    </td>
                                </tr>
                                <?php $current_month = $record_month; ?>
                            <?php endif; ?>

                            <tr data-name="<?= strtolower($b['firstname'].' '.$b['lastname'].' '.$b['equipment_name']) ?>"
                                data-status="<?= $b['status'] ?>"
                                data-month="<?= $row_month ?>"
                                data-year="<?= $row_year ?>">

                                <td><?= htmlspecialchars($b['firstname'].' '.$b['lastname']) ?></td>
                                <td><?= htmlspecialchars($b['equipment_name']) ?></td>
                                <td><?= (int)$b['quantity'] ?></td>

                                <td>
                                    <?= ($b['status'] === 'declined' ? '—' : htmlspecialchars($b['date_borrowed'])) ?>
                                </td>

                                <td style="font-weight:600;color:#2e7d32;">
                                    <?= $b['date_returned'] ? htmlspecialchars($b['date_returned']) : "—" ?>
                                </td>

                                <td>
                                    <?php if ($b['status'] === 'borrowed'): ?>
                                        <span class="status-pill status-borrowed">Borrowed</span>
                                    <?php elseif ($b['status'] === 'returned'): ?>
                                        <span class="status-pill status-returned">Returned</span>
                                    <?php elseif ($b['status'] === 'declined'): ?>
                                        <span class="status-pill status-declined">Declined</span>
                                    <?php elseif ($b['status'] === 'pending'): ?>
                                        <span class="status-pill status-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
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
function confirmLogout(event){
    event.preventDefault();
    if(confirm("Logout?")) window.location="logout.php";
}

/* ✅ ADDED: Sidebar toggle (hamburger) + overlay */
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

/* ✅ ADDED: Close icon click hides sidebar */
const sidebarClose = document.getElementById("sidebarClose");
if (sidebarClose && sidebar && overlay) {
    sidebarClose.addEventListener("click", () => {
        sidebar.classList.remove("sidebar-open");
        overlay.classList.remove("active");
    });
}

const searchInput  = document.getElementById("searchInput");
const statusFilter = document.getElementById("statusFilter");
const monthFilter  = document.getElementById("monthFilter");
const yearFilter   = document.getElementById("yearFilter");
const printBtn     = document.getElementById("printBtn");
const exportForm   = document.getElementById("exportForm");
const exportStatus = document.getElementById("exportStatus");
const exportMonth  = document.getElementById("exportMonth");
const exportYear   = document.getElementById("exportYear");

const rows = document.querySelectorAll("#historyTable tbody tr");

function filterRows(){
    let term  = searchInput.value.toLowerCase();
    let sVal  = statusFilter.value;
    let mVal  = monthFilter.value;
    let yVal  = yearFilter.value;

    rows.forEach(row => {

        if (!row.dataset.name) {
            row.style.display = "";
            return;
        }

        let matchName  = row.dataset.name.includes(term);
        let matchStat  = (sVal === "all" || row.dataset.status === sVal);
        let matchMonth = (mVal === "all" || row.dataset.month === mVal);
        let matchYear  = (yVal === "all" || row.dataset.year === yVal);

        row.style.display = (matchName && matchStat && matchMonth && matchYear) ? "" : "none";
    });
}

searchInput.addEventListener("input", filterRows);
statusFilter.addEventListener("change", filterRows);
monthFilter.addEventListener("change", filterRows);
yearFilter.addEventListener("change", filterRows);

if (printBtn) {
    printBtn.addEventListener("click", () => window.print());
}

if (exportForm) {
    exportForm.addEventListener("submit", () => {
        exportStatus.value = statusFilter.value;
        exportMonth.value  = monthFilter.value;
        exportYear.value   = yearFilter.value;
    });
}
</script>

</body>
</html>
