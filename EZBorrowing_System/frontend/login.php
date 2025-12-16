<?php
session_start();
$error_message = '';
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include '../backend/db.php';

    $id_number = trim($_POST['id'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($id_number === '' || $password === '') {
        $_SESSION['login_error'] = "Please enter both ID number and password.";
        header("Location: login.php");
        exit();
    }
    $stmt = $conn->prepare("SELECT id, firstname, role, password, profile_pic FROM users WHERE id_number = ?");
    $stmt->bind_param("s", $id_number);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $firstname, $role, $hashed_password, $profile_pic);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            // SUCCESS
            $_SESSION['user_id']      = $user_id;
            $_SESSION['firstname']    = $firstname;
            $_SESSION['role']         = $role;
            $_SESSION['profile_pic']  = $profile_pic;

            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['login_error'] = "Invalid ID number or password.";
        }
    } else {
        $_SESSION['login_error'] = "Invalid ID number or password.";
    }

    $stmt->close();
    $conn->close();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Login - EZBorrowing System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/login.css" />
</head>
<body>
    <div class="container">
        <form class="form-box" method="post" action="" autocomplete="off">
            <h2>Login</h2>

            <?php if ($error_message): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <input type="text" name="id" placeholder="ID Number" required>
            <input type="password" name="password" placeholder="Password" required>

            <button type="submit">Login</button>
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </form>
    </div>
</body>
</html>
