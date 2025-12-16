<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

// Filters
$status = $_GET['status'] ?? 'all';
$month  = $_GET['month'] ?? 'all';
$year   = $_GET['year'] ?? 'all';

// Base SQL
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
";

// Restrict for student/faculty
if ($_SESSION['role'] !== 'custodian') {
    $uid = intval($_SESSION['user_id']);
    $sql .= " WHERE br.user_id = $uid ";
}

$conditions = [];

if ($status !== 'all') $conditions[] = "br.status = '$status'";
if ($month !== 'all')  $conditions[] = "MONTH(br.date_borrowed) = $month";
if ($year !== 'all')   $conditions[] = "YEAR(br.date_borrowed) = $year";

if (!empty($conditions)) {
    $sql .= ($_SESSION['role'] === 'custodian' ? " WHERE " : " AND ") .
            implode(" AND ", $conditions);
}

$sql .= " ORDER BY br.date_borrowed DESC";

$result = $conn->query($sql);

// File setup
$filename = "Borrow History.csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

// 🔥 FIX #1: Add UTF-8 BOM
echo "\xEF\xBB\xBF";

$output = fopen("php://output", "w");

// Title
fputcsv($output, ["EZBorrowing System – Borrow History"]);
fputcsv($output, ["Generated: " . date("Y-m-d H:i:s")]);
fputcsv($output, ["Filters: Status=$status, Month=$month, Year=$year"]);
fputcsv($output, [""]);

// Header
fputcsv($output, [
    "Borrower", "Equipment", "Quantity", "Date Borrowed", "Date Returned", "Condition", "Status"
]);

$currentMonth = "";

while ($row = $result->fetch_assoc()) {

    // Month separator
    $monthLabel = date("F Y", strtotime($row['date_borrowed']));
    if ($monthLabel !== $currentMonth) {
        fputcsv($output, [""]);
        fputcsv($output, ["=== $monthLabel ==="]);
        $currentMonth = $monthLabel;
    }

    // Clean values
    $borrower = $row['firstname'] . " " . $row['lastname'];
    $equipment = $row['equipment_name'];
    $quantity = $row['quantity'];
    $dateBorrowed = $row['date_borrowed'];
    $dateReturned = $row['date_returned'] ?: "—";
    $condition = $row['item_condition'] ?: "—";
    $statusTxt = ucfirst($row['status']);

    // FIX #2 — Force Excel to treat all fields as TEXT
    $safe = function($v) {
        return '="'.$v.'"';
    };

    fputcsv($output, [
        $safe($borrower),
        $safe($equipment),
        $safe($quantity),
        $safe($dateBorrowed),
        $safe($dateReturned),
        $safe($condition),
        $safe($statusTxt)
    ]);
}

fclose($output);
$conn->close();
exit;
?>
