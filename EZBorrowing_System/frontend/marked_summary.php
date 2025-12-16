<?php
session_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'custodian') {
    header("Location: login.php");
    exit();
}

$active = 'marked_summary';
$page_title = 'Marked Equipment Summary';

$firstname    = $_SESSION['firstname'];
$profile_pic  = $_SESSION['profile_pic'] ?? null;
$profile_path = $profile_pic ? "../" . $profile_pic : "../uploads/images/default-avatar.png";

$conditions = [];
$q = $conn->query("SELECT DISTINCT condition_type AS cond FROM equipment_marked ORDER BY cond ASC");
if ($q instanceof mysqli_result) {
    while ($row = $q->fetch_assoc()) {
        if ($row['cond'] !== null && $row['cond'] !== '') {
            $conditions[] = $row['cond'];
        }
    }
}

$months_available = [];
$years_available  = [];

$mq = $conn->query("SELECT date_marked FROM equipment_marked");
if ($mq instanceof mysqli_result) {
    while ($row = $mq->fetch_assoc()) {
        if (empty($row['date_marked'])) continue;
        $ts     = strtotime($row['date_marked']);
        $m_num  = (int)date('n', $ts);
        $m_name = date('F', $ts);
        $y      = date('Y', $ts);
        $months_available[$m_num] = $m_name;
        $years_available[$y] = true;
    }
}
ksort($months_available);
ksort($years_available);

$search    = $_GET['search'] ?? '';
$condition = $_GET['condition'] ?? 'all';
$month     = $_GET['month'] ?? 'all';
$year      = $_GET['year'] ?? 'all';

$sql = "
    SELECT 
        em.id,
        em.quantity,
        em.condition_type,
        em.date_marked,
        eq.name AS equipment_name,
        u.firstname,
        u.lastname
    FROM equipment_marked em
    JOIN equipment eq ON em.equipment_id = eq.id
    JOIN users u      ON em.marked_by     = u.id
    WHERE 1
";

if ($search !== '') {
    $s = '%' . $conn->real_escape_string($search) . '%';
    $sql .= " AND (eq.name LIKE '$s'
              OR em.condition_type LIKE '$s'
              OR u.firstname LIKE '$s'
              OR u.lastname LIKE '$s')";
}

if ($condition !== 'all') {
    $c = $conn->real_escape_string($condition);
    $sql .= " AND em.condition_type = '$c'";
}

if ($month !== 'all') {
    $sql .= " AND MONTH(em.date_marked) = " . intval($month);
}

if ($year !== 'all') {
    $sql .= " AND YEAR(em.date_marked) = " . intval($year);
}

$sql .= " ORDER BY em.date_marked DESC";

$result = $conn->query($sql);
$items = [];

if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Marked Equipment Summary</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/dashboards.css">
    <link rel="stylesheet" href="../assets/history.css">
</head>

<body>

<div class="container">

    <!-- ✅ ADDED: overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar">

        <!-- ✅ ADDED: close icon/button -->
        <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">✕</button>

        <h2 class="logo">EZBorrow</h2>
        <ul class="menu">
            <li><a href="dashboard.php">🏠 Dashboard</a></li>
            <li><a href="borrow_items.php">📦 Borrow Items</a></li>
            <li><a href="return_items.php">↩️ Return Items</a></li>
            <li><a href="equipment_list.php">🧾 Equipment List</a></li>
            <li class="active"><a href="marked_summary.php">📦 Marked Summary</a></li>
            <li><a href="history.php">📚 All History</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="#" onclick="confirmLogout(event)">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div style="display:flex;justify-content:space-between;width:100%;align-items:center;">

                <!-- ✅ ADDED: hamburger (mobile only via dashboards.css) -->
                <button class="menu-toggle" id="menuToggle">☰</button>

                <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
                <div class="user-profile">
                    <img src="<?= htmlspecialchars($profile_path) ?>" class="user-avatar" alt="Profile">
                    <span class="user-name"><?= htmlspecialchars($firstname) ?></span>
                </div>
            </div>
        </header>

        <section class="page-header">
            <div>
                <h2>Marked Items Summary</h2>
                <p class="page-subtitle">
                    View all equipment marked as damaged, repairable, waste, or any other condition.
                </p>
            </div>

            <div class="return-filters">
                <input type="search"
                       id="searchInput"
                       placeholder="Search equipment, condition, user..."
                       value="<?= htmlspecialchars($search) ?>">

                <select id="conditionFilter">
                    <option value="all">All conditions</option>
                    <?php foreach ($conditions as $cond): ?>
                        <option value="<?= htmlspecialchars($cond) ?>"
                            <?= ($condition === $cond) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cond) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select id="monthFilter">
                    <option value="all">All months</option>
                    <?php foreach ($months_available as $m_num => $m_name): ?>
                        <option value="<?= $m_num ?>"
                            <?= ($month == $m_num) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m_name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select id="yearFilter">
                    <option value="all">All years</option>
                    <?php foreach ($years_available as $y => $_): ?>
                        <option value="<?= $y ?>"
                            <?= ($year == $y) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($y) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" id="printBtn" class="history-action-btn">Print Report</button>

                <form id="exportForm"
                      method="get"
                      action="../backend/export_marked_excel.php"
                      target="_blank">
                    <input type="hidden" name="search"    id="exportSearch">
                    <input type="hidden" name="condition" id="exportCondition">
                    <input type="hidden" name="month"     id="exportMonth">
                    <input type="hidden" name="year"      id="exportYear">
                    <button onclick="window.location.href='../backend/export_marked_excel.php?<?= http_build_query($_GET) ?>'" class="history-action-btn" style="margin-right:10px;">
                        Export Excel
                    </button>

                </form>
            </div>
        </section>

        <section>
            <h3 class="section-title">Marked Items</h3>

            <div class="table-wrapper">
                <table class="return-table" id="markedTable">
                    <tbody>

                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="5" class="empty-row">No marked items found.</td>
                        </tr>
                    <?php else: ?>

                    <?php
                    $current_month_group = "";
                    $month_totals = [];
                    ?>

                    <?php foreach ($items as $key => $row): ?>

                        <?php
                        $ts          = strtotime($row['date_marked']);
                        $month_label = date("F Y", $ts);
                        $m           = date('n', $ts);
                        $y           = date('Y', $ts);

                        $cond  = $row['condition_type'];
                        $equip = $row['equipment_name'];
                        $qty   = (int)$row['quantity'];

                        if (!isset($month_totals[$month_label])) {
                            $month_totals[$month_label] = [];
                        }
                        if (!isset($month_totals[$month_label][$cond])) {
                            $month_totals[$month_label][$cond] = [];
                        }
                        if (!isset($month_totals[$month_label][$cond][$equip])) {
                            $month_totals[$month_label][$cond][$equip] = 0;
                        }

                        $month_totals[$month_label][$cond][$equip] += $qty;
                        ?>

                        <?php if ($month_label !== $current_month_group): ?>
                            <tr class="month-label-row"
                                data-month="<?= $m ?>"
                                data-year="<?= $y ?>"
                                style="background:#e8f5e9;">
                                <td colspan="5"
                                    style="font-weight:700;color:#2e7d32;padding:12px;">
                                    📅 <?= htmlspecialchars($month_label) ?>
                                </td>
                            </tr>

                            <tr class="month-header-row"
                                data-month="<?= $m ?>"
                                data-year="<?= $y ?>">
                                <th>Equipment</th>
                                <th>Condition</th>
                                <th>Quantity</th>
                                <th>Marked By</th>
                                <th>Date</th>
                            </tr>

                            <?php $current_month_group = $month_label; ?>
                        <?php endif; ?>

                        <tr data-name="<?= strtolower($row['equipment_name'] . ' ' . $row['condition_type'] . ' ' . $row['firstname'] . ' ' . $row['lastname']) ?>"
                            data-condition="<?= htmlspecialchars($row['condition_type']) ?>"
                            data-month="<?= $m ?>"
                            data-year="<?= $y ?>">

                            <td><?= htmlspecialchars($row['equipment_name']) ?></td>
                            <td><?= htmlspecialchars($row['condition_type']) ?></td>
                            <td><?= (int)$row['quantity'] ?></td>
                            <td><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></td>
                            <td><?= htmlspecialchars($row['date_marked']) ?></td>
                        </tr>

                        <?php
                        $next_index = $key + 1;
                        $next_month_label = isset($items[$next_index])
                            ? date("F Y", strtotime($items[$next_index]['date_marked']))
                            : null;

                        if ($next_month_label !== $month_label):
                        ?>
                        <tr class="summary-row"
                            data-month="<?= $m ?>"
                            data-year="<?= $y ?>"
                            style="background:#f1f8f2; border-top:2px solid #cde7d2;">
                            <td colspan="5" style="padding:20px;">

                                <?php
                                    $grand_total = 0;
                                    foreach ($month_totals[$month_label] as $condKey => $equipments) {
                                        foreach ($equipments as $qtyVal) {
                                            $grand_total += $qtyVal;
                                        }
                                    }

                                    $icons = [
                                        "Damaged"      => "💥",
                                        "Repairable"   => "🔧",
                                        "Waste"        => "🗑",
                                    ];
                                ?>

                                <div style="font-size:1.25em; font-weight:700; color:#166534; margin-bottom:8px;">
                                    Summary for <?= htmlspecialchars($month_label) ?>
                                </div>

                                <div style="font-size:1.05em; font-weight:600; color:#2e7d32; margin-bottom:14px;">
                                    Total items marked this month:
                                    <span style="font-weight:800;"><?= $grand_total ?></span>
                                </div>

                                <?php foreach ($month_totals[$month_label] as $c => $equipments): ?>

                                    <?php
                                        $cond_total = array_sum($equipments);
                                        $percent = $grand_total > 0 ? round(($cond_total / $grand_total) * 100) : 0;
                                        $icon = $icons[$c] ?? "•";
                                    ?>

                                    <div style="margin-top:12px; font-weight:700; color:#1b5e20; font-size:1.08em;">
                                        <?= $icon ?> <?= htmlspecialchars($c) ?>
                                        <span style="color:#388e3c; font-size:0.95em;">
                                            (<?= $cond_total ?> items — <?= $percent ?>%)
                                        </span>
                                    </div>

                                    <ul style="margin:6px 0 0 22px; padding:0; list-style:disc; color:#2e7d32; font-size:0.95em;">
                                        <?php foreach ($equipments as $ename => $qtyVal): ?>
                                            <li style="margin-bottom:3px;">
                                                <strong><?= htmlspecialchars($ename) ?></strong>: <?= $qtyVal ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                <?php endforeach; ?>

                            </td>
                        </tr>
                        <?php endif; ?>
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
    if (confirm("Logout?")) {
        window.location = "logout.php";
    }
}

/* ✅ ADDED: Sidebar toggle + close */
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

const searchInput     = document.getElementById("searchInput");
const conditionFilter = document.getElementById("conditionFilter");
const monthFilter     = document.getElementById("monthFilter");
const yearFilter      = document.getElementById("yearFilter");
const rows            = document.querySelectorAll("#markedTable tbody tr");

function filterRows() {
    const term   = searchInput.value.toLowerCase();
    const cVal   = conditionFilter.value;
    const mVal   = monthFilter.value;
    const yVal   = yearFilter.value;

    rows.forEach(row => {
        const isSummary     = row.classList.contains("summary-row");
        const isMonthLabel  = row.classList.contains("month-label-row");
        const isMonthHeader = row.classList.contains("month-header-row");

        if (!isSummary && !isMonthLabel && !isMonthHeader && !row.dataset.name) {
            return;
        }

        const matchSearch = (isSummary || isMonthLabel || isMonthHeader)
            ? true
            : row.dataset.name.includes(term);

        const matchCond = (cVal === "all" ||
                          row.dataset.condition === cVal ||
                          isSummary || isMonthLabel || isMonthHeader);

        const matchMonth = (mVal === "all" || row.dataset.month === mVal);
        const matchYear  = (yVal === "all" || row.dataset.year  === yVal);

        row.style.display = (matchCond && matchMonth && matchYear && matchSearch) ? "" : "none";
    });
}

searchInput.addEventListener("input",  filterRows);
conditionFilter.addEventListener("change", filterRows);
monthFilter.addEventListener("change", filterRows);
yearFilter.addEventListener("change", filterRows);

document.getElementById("printBtn").addEventListener("click", () => {
    window.print();
});

document.getElementById("exportForm").addEventListener("submit", () => {
    document.getElementById("exportSearch").value    = searchInput.value;
    document.getElementById("exportCondition").value = conditionFilter.value;
    document.getElementById("exportMonth").value     = monthFilter.value;
    document.getElementById("exportYear").value      = yearFilter.value;
});
</script>

</body>
</html>
