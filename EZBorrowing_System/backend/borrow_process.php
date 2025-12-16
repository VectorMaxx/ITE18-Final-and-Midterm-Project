<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'faculty'])) {
    header("Location: ../frontend/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$equipment_id = intval($_POST['id'] ?? 0);
$quantity_requested = intval($_POST['quantity'] ?? 0);

if ($equipment_id <= 0 || $quantity_requested <= 0) {
    header("Location: ../frontend/borrow_items.php?error=invalid_input");
    exit();
}

$stmt = $conn->prepare("SELECT quantity, status FROM equipment WHERE id = ?");
$stmt->bind_param("i", $equipment_id);
$stmt->execute();
$stmt->bind_result($current_quantity, $current_status);
$stmt->fetch();
$stmt->close();

if ($current_status !== 'available' || $current_quantity < $quantity_requested) {
    header("Location: ../frontend/borrow_items.php?error=unavailable");
    exit();
}

$duplicateCheck = $conn->prepare("
    SELECT COUNT(*) FROM borrow_records
    WHERE user_id = ? AND equipment_id = ? AND status IN ('pending', 'borrowed')
");
$duplicateCheck->bind_param("ii", $user_id, $equipment_id);
$duplicateCheck->execute();
$duplicateCheck->bind_result($count);
$duplicateCheck->fetch();
$duplicateCheck->close();

if ($count > 0) {
    header("Location: ../frontend/borrow_items.php?error=duplicate");
    exit();
}

$insert = $conn->prepare("INSERT INTO borrow_records (user_id, equipment_id, quantity, date_borrowed, status) VALUES (?, ?, ?, NOW(), 'pending')");
$insert->bind_param("iii", $user_id, $equipment_id, $quantity_requested);
$insert->execute();
$insert->close();

$conn->close();

header("Location: ../frontend/borrow_items.php?success=pending");
exit();
?>
