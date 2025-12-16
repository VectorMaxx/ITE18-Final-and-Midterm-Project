<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'custodian') {
    die("Unauthorized access.");
}

/* ============================================================
   FILTERS (must match marked_summary.php)
   ============================================================ */
$search    = $_GET['search'] ?? '';
$condition = $_GET['condition'] ?? 'all';
$month     = $_GET['month'] ?? 'all';
$year      = $_GET['year'] ?? 'all';

/* ============================================================
   SQL QUERY (correct table: equipment_marked)
   ============================================================ */
$sql = "
    SELECT 
        em.quantity,
        em.condition_type,
        em.date_marked,
        eq.name AS equipment_name,
        u.firstname,
        u.lastname
    FROM equipment_marked em
    JOIN equipment eq ON em.equipment_id = eq.id
    JOIN users u ON em.marked_by = u.id
    WHERE 1
";

/* Search filter */
if ($search !== '') {
    $s = "%" . $conn->real_escape_string($search) . "%";
    $sql .= "
        AND (
            eq.name LIKE '$s'
            OR em.condition_type LIKE '$s'
            OR u.firstname LIKE '$s'
            OR u.lastname LIKE '$s'
        )
    ";
}

/* Condition filter */
if ($condition !== 'all') {
    $c = $conn->real_escape_string($condition);
    $sql .= " AND em.condition_type = '$c'";
}

/* Month filter */
if ($month !== 'all') {
    $sql .= " AND MONTH(em.date_marked) = " . intval($month);
}

/* Year filter */
if ($year !== 'all') {
    $sql .= " AND YEAR(em.date_marked) = " . intval($year);
}

$sql .= " ORDER BY em.date_marked DESC";

$result = $conn->query($sql);
if (!$result) {
    die("Query error: " . $conn->error);
}

/* ============================================================
   CSV OUTPUT
   ============================================================ */

$filename = "Marked_Items_Report.csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

// UTF-8 BOM so Excel reads encoding correctly
echo "\xEF\xBB\xBF";

$output = fopen("php://output", "w");

/* Title / meta rows */
fputcsv($output, ["EZBorrowing System – Marked Items Report"]);
fputcsv($output, ["Generated: " . date("Y-m-d H:i:s")]);
fputcsv(
    $output,
    ["Filters: Search='{$search}', Condition={$condition}, Month={$month}, Year={$year}"]
);
fputcsv($output, [""]);

/* Table header */
fputcsv($output, [
    "Equipment",
    "Condition",
    "Quantity",
    "Marked By",
    "Date Marked"
]);

$currentMonth = "";

// Helper to force Excel to treat as text
$safe = function($v) {
    return '="' . $v . '"';
};

/* Data rows with month section labels */
while ($row = $result->fetch_assoc()) {
    $monthLabel = date("F Y", strtotime($row['date_marked']));

    // Month separator row if month changes
    if ($monthLabel !== $currentMonth) {
        fputcsv($output, [""]); // blank line
        fputcsv($output, ["=== " . $monthLabel . " ==="]);
        $currentMonth = $monthLabel;
    }

    $equipmentName = $row['equipment_name'];
    $conditionTxt  = $row['condition_type'];
    $qty           = $row['quantity'];
    $markedBy      = $row['firstname'] . " " . $row['lastname'];
    $dateMarked    = $row['date_marked'];

    fputcsv($output, [
        $safe($equipmentName),
        $safe($conditionTxt),
        $safe($qty),
        $safe($markedBy),
        $safe($dateMarked)
    ]);
}

fclose($output);
$conn->close();
exit();
