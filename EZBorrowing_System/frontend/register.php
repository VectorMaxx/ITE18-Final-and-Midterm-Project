<?php
$error_message = '';
$success_message = '';
$firstname = '';
$lastname = '';
$id_number = '';
$username = '';
$role = '';

if (isset($_GET['success'])) {
    $success_message = "Registration successful! You can now <a href='login.php'>login</a>.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include '../backend/db.php';

    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $id_number = trim($_POST['id']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT id FROM users WHERE id_number = ?");
    $stmt->bind_param("s", $id_number);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error_message = "ID Number already exists.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (id_number, username, password, role, firstname, lastname) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $id_number, $username, $hashed_password, $role, $firstname, $lastname);

        if ($stmt->execute()) {
            header("Location: register.php?success=1");
            exit;
        } else {
            $error_message = "Registration failed: " . $conn->error;
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Register - EZBorrowing System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/register.css" />
</head>
<body>
    <div class="container">
        <form class="form-box" method="post" action="" autocomplete="off">
            <h2>Register Account</h2>

            <?php if ($success_message): ?>
                <div class="success-message"><?= $success_message ?></div>
            <?php endif; ?>

            <input type="text" name="firstname" placeholder="First Name" value="<?= htmlspecialchars($firstname) ?>" required />
            <input type="text" name="lastname" placeholder="Last Name" value="<?= htmlspecialchars($lastname) ?>" required />

            <input type="text" name="id" placeholder="ID Number" value="<?= htmlspecialchars($id_number) ?>" required />
            <?php if ($error_message && strpos($error_message, "ID Number") !== false): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($username) ?>" required />

            <label for="role" class="role-label">Select Role</label>
            <div class="dropdown">
                <select id="role" name="role" required>
                    <option value="" disabled <?= empty($role) ? 'selected' : '' ?>>Select your role</option>
                    <option value="student" <?= ($role == 'student') ? 'selected' : '' ?>>Student</option>
                    <option value="faculty" <?= ($role == 'faculty') ? 'selected' : '' ?>>Faculty</option>
                    <option value="custodian" <?= ($role == 'custodian') ? 'selected' : '' ?>>Custodian</option>
                </select>
            </div>

            <input type="password" name="password" placeholder="Password" required />
            <input type="password" name="confirm_password" placeholder="Confirm Password" required />
            <?php if ($error_message && strpos($error_message, "Password") !== false): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <button type="submit">Register</button>
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </form>
    </div>

    <script>
        if (window.location.search.includes('success=1')) {
            window.history.replaceState({}, document.title, 'register.php');
        }
    </script>
</body>
</html>
