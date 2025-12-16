<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'custodian') {
    header("Location: ../frontend/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id'] ?? 0);

    if ($request_id <= 0) {
        header("Location: ../frontend/borrow_items.php?error=invalid");
        exit();
    }

    if (isset($_POST['approve'])) {
        $stmt = $conn->prepare("SELECT equipment_id, quantity FROM borrow_records WHERE id = ?");
        if (!$stmt) { die("Prepare failed: " . $conn->error); }
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->bind_result($equipment_id, $quantity_borrow);
        if (!$stmt->fetch()) {
            $stmt->close();
            header("Location: ../frontend/borrow_items.php?error=notfound");
            exit();
        }
        $stmt->close();

        $getqty = $conn->prepare("SELECT quantity FROM equipment WHERE id = ?");
        if (!$getqty) { die("Prepare failed: " . $conn->error); }
        $getqty->bind_param("i", $equipment_id);
        $getqty->execute();
        $getqty->bind_result($current_quantity);
        $getqty->fetch();
        $getqty->close();

        $new_quantity = $current_quantity - $quantity_borrow;
        if ($new_quantity < 0) $new_quantity = 0;
        $new_status = ($new_quantity == 0) ? 'borrowed' : 'available';

        $update_eq = $conn->prepare("UPDATE equipment SET quantity = ?, status = ? WHERE id = ?");
        if (!$update_eq) { die("Prepare failed: " . $conn->error); }
        $update_eq->bind_param("isi", $new_quantity, $new_status, $equipment_id);
        $update_eq->execute();
        $update_eq->close();

        $update_rec = $conn->prepare("UPDATE borrow_records SET status = 'borrowed', date_borrowed = NOW() WHERE id = ?");
        if (!$update_rec) { die("Prepare failed: " . $conn->error); }
        $update_rec->bind_param("i", $request_id);
        $update_rec->execute();
        $update_rec->close();

        $conn->close();
        header("Location: ../frontend/borrow_items.php?success=approved");
        exit();
    }

    if (isset($_POST['decline'])) {
        $decline = $conn->prepare("UPDATE borrow_records SET status = 'declined' WHERE id = ?");
        if (!$decline) { die("Prepare failed: " . $conn->error); }
        $decline->bind_param("i", $request_id);
        $decline->execute();
        $decline->close();
        $conn->close();
        header("Location: ../frontend/borrow_items.php?success=declined");
        exit();
    }
}

$conn->close();
header("Location: ../frontend/borrow_items.php");
exit();
?>
