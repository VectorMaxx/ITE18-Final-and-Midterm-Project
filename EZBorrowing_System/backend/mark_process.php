<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'custodian') {
    header("Location: ../frontend/login.php");
    exit();
}

$equipment_id = intval($_POST['equipment_id'] ?? 0);
$condition = $_POST['condition'] ?? '';
$user_id = $_SESSION['user_id'];

if ($equipment_id <= 0 || $condition === '') {
    header("Location: ../frontend/equipment_list.php?error=invalid_mark");
    exit();
}

// Step 1: Get current equipment quantity
$stmt = $conn->prepare("SELECT quantity FROM equipment WHERE id = ?");
$stmt->bind_param("i", $equipment_id);
$stmt->execute();
$stmt->bind_result($current_qty);
$stmt->fetch();
$stmt->close();

if ($current_qty <= 0) {
    header("Location: ../frontend/equipment_list.php?error=no_quantity");
    exit();
}

// Step 2: Reduce quantity by 1
$new_qty = $current_qty - 1;
$new_status = ($new_qty == 0) ? 'unavailable' : 'available';

$update = $conn->prepare("UPDATE equipment SET quantity = ?, status = ? WHERE id = ?");
$update->bind_param("isi", $new_qty, $new_status, $equipment_id);
$update->execute();
$update->close();

// Step 3: Log to equipment_marked table
$insert = $conn->prepare("
    INSERT INTO equipment_marked (equipment_id, marked_by, condition_type, quantity, date_marked)
    VALUES (?, ?, ?, 1, NOW())
");
$insert->bind_param("iis", $equipment_id, $user_id, $condition);
$insert->execute();
$insert->close();

$conn->close();

header("Location: ../frontend/equipment_list.php?success=marked");
exit();
?>
