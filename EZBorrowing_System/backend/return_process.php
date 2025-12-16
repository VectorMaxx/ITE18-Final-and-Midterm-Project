<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'faculty'])) {
    header("Location: ../frontend/login.php");
    exit();
}

$borrow_id       = intval($_POST['borrow_id'] ?? 0);
$equipment_id    = intval($_POST['equipment_id'] ?? 0);
$return_quantity = intval($_POST['quantity'] ?? 0);
$condition       = $_POST['item_condition'] ?? '';  // ← FIXED HERE
$user_id         = $_SESSION['user_id'];

if ($borrow_id <= 0 || $equipment_id <= 0 || $return_quantity <= 0) {
    die('Invalid parameters.');
}

// Update borrow_records → mark returned
$update_borrow = $conn->prepare("
    UPDATE borrow_records 
    SET status = 'returned', date_returned = NOW() 
    WHERE id = ?
");
$update_borrow->bind_param("i", $borrow_id);
$update_borrow->execute();
$update_borrow->close();

// Insert into returned_items with condition
$insert = $conn->prepare("
    INSERT INTO returned_items 
        (borrow_id, user_id, equipment_id, quantity_returned, date_returned, item_condition)
    VALUES 
        (?, ?, ?, ?, NOW(), ?)
");
$insert->bind_param("iiiis", $borrow_id, $user_id, $equipment_id, $return_quantity, $condition);
$insert->execute();
$insert->close();

// Update equipment quantity
$update_eq = $conn->prepare("UPDATE equipment SET quantity = quantity + ? WHERE id = ?");
$update_eq->bind_param("ii", $return_quantity, $equipment_id);
$update_eq->execute();
$update_eq->close();

// Check updated quantity
$check = $conn->prepare("SELECT quantity FROM equipment WHERE id = ?");
$check->bind_param("i", $equipment_id);
$check->execute();
$check->bind_result($qty_now);
$check->fetch();
$check->close();

if ($qty_now > 0) {
    $set_avail = $conn->prepare("UPDATE equipment SET status = 'available' WHERE id = ?");
    $set_avail->bind_param("i", $equipment_id);
    $set_avail->execute();
    $set_avail->close();
}

$conn->close();

header("Location: ../frontend/return_items.php?success=returned");
exit();
?>
