<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
include '../backend/db.php';

$active = 'profile';
$page_title = 'Profile';
$firstname = $_SESSION['firstname'];
$profile_pic = $_SESSION['profile_pic'] ?? null;
$profile_path = $profile_pic ? "../" . $profile_pic : "../uploads/images/default-avatar.png";
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $conn->prepare("SELECT id_number, username, role, firstname, lastname, profile_pic, password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($id_number, $username, $role, $firstname, $lastname, $profile_pic_db, $password_hash);
$stmt->fetch();
$stmt->close();

$profile_pic_path = $profile_pic_db ? "../" . $profile_pic_db : "../uploads/images/default-avatar.png";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_firstname = trim($_POST['firstname'] ?? '');
    $new_lastname  = trim($_POST['lastname'] ?? '');
    $new_username  = trim($_POST['username'] ?? '');

    if ($new_firstname === '' || $new_lastname === '' || $new_username === '') {
        $error = "Please fill in all required fields.";
    } else {
        $updatePassword   = false;
        $newPasswordHash  = null;
        $profile_relative = null;

        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password !== '' || $new_password !== '' || $confirm_password !== '') {
            if ($current_password === '' || $new_password === '' || $confirm_password === '') {
                $error = "To change password, fill in all password fields.";
            } elseif (!password_verify($current_password, $password_hash)) {
                $error = "Current password is incorrect.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } else {
                $newPasswordHash = password_hash($new_password, PASSWORD_DEFAULT);
                $updatePassword  = true;
            }
        }

        if (empty($error) && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {

            $uploadDir = '../uploads/images/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

            $file = $_FILES['profile_pic'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = "There was an error uploading your file.";
            } else {
                $filename = basename($file['name']);
                $fileSize = $file['size'];
                $fileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $maxFileSize = 5 * 1024 * 1024;

                if (!in_array($fileType, $allowed)) {
                    $error = "Invalid file type. Allowed: jpg, jpeg, png, gif.";
                } elseif ($fileSize > $maxFileSize) {
                    $error = "File size exceeds 5MB limit.";
                } else {
                    $newName = uniqid('profile_', true) . '.' . $fileType;
                    $targetFile = $uploadDir . $newName;

                    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                        if (!empty($profile_pic_db) && $profile_pic_db !== "uploads/images/default-avatar.png") {
                            $old_file_path = '../' . $profile_pic_db;
                            if (file_exists($old_file_path)) {
                                unlink($old_file_path);
                            }
                        }
                        $profile_relative = 'uploads/images/' . $newName;
                    } else {
                        $error = "Could not save uploaded file.";
                    }
                }
            }
        }

        if (empty($error)) {
            if ($updatePassword && $profile_relative) {
                $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, username = ?, password = ?, profile_pic = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $new_firstname, $new_lastname, $new_username, $newPasswordHash, $profile_relative, $user_id);
            } elseif ($updatePassword) {
                $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, username = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $new_firstname, $new_lastname, $new_username, $newPasswordHash, $user_id);
            } elseif ($profile_relative) {
                $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, username = ?, profile_pic = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $new_firstname, $new_lastname, $new_username, $profile_relative, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, username = ? WHERE id = ?");
                $stmt->bind_param("sssi", $new_firstname, $new_lastname, $new_username, $user_id);
            }

            if ($stmt->execute()) {
                $success = "Profile updated successfully.";
                $_SESSION['firstname'] = $new_firstname;

                if ($profile_relative) {
                    $_SESSION['profile_pic'] = $profile_relative;
                    $profile_pic_path = "../" . $profile_relative;
                }
            } else {
                $error = "Failed to update profile.";
            }

            $stmt->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - EZBorrowing System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/dashboards.css">
    <link rel="stylesheet" href="../assets/profile.css">
</head>
<body>

<div class="container">

    <!-- ✅ ADDED: overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar">

        <!-- ✅ ADDED: close icon -->
        <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">✕</button>

        <h2 class="logo">EZBorrow</h2>
        <ul class="menu">
            <li><a href="dashboard.php">🏠 Dashboard</a></li>
            <li><a href="borrow_items.php">📦 Borrow Items</a></li>
            <li><a href="return_items.php">↩️ Return Items</a></li>
            <li><a href="equipment_list.php">🧾 Equipment List</a></li>
            <li><a href="history.php">📚 History</a></li>
            <li class="active"><a href="profile.php">👤 Profile</a></li>
            <li><a href="#" onclick="confirmLogout(event)">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">

                <!-- ✅ ADDED: hamburger -->
                <button class="menu-toggle" id="menuToggle">☰</button>

                <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
                <div class="user-profile">
                    <img src="<?= htmlspecialchars($profile_path) ?>" class="user-avatar" alt="Profile">
                    <span class="user-name"><?= htmlspecialchars($firstname) ?></span>
                </div>
            </div>
        </header>

        <section class="profile-page">
            <h2>Profile Settings</h2>
            <p class="page-subtitle">Update your personal information, password, and profile picture.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="profile-grid">

                <div class="profile-card">
                    <h3>Profile Picture</h3>
                    <img src="<?= htmlspecialchars($profile_pic_path) ?>" class="profile-preview" alt="Profile Picture">

                    <form method="post" enctype="multipart/form-data" class="profile-form">
                        <label class="field-label">Change Picture</label>
                        <input type="file" name="profile_pic" accept="image/*">
                        <input type="hidden" name="firstname" value="<?= htmlspecialchars($firstname) ?>">
                        <input type="hidden" name="lastname" value="<?= htmlspecialchars($lastname) ?>">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                        <p class="hint">Accepted: jpg, jpeg, png, gif. Max 5MB.</p>
                        <button type="submit" class="btn-primary small">Update Picture</button>
                    </form>
                </div>

                <div class="profile-card">
                    <h3>Account Information</h3>
                    <form method="post" enctype="multipart/form-data" class="profile-form">
                        <label class="field-label">ID Number</label>
                        <input type="text" value="<?= htmlspecialchars($id_number) ?>" disabled>

                        <label class="field-label">Role</label>
                        <input type="text" value="<?= htmlspecialchars(ucfirst($role)) ?>" disabled>

                        <label class="field-label">First Name</label>
                        <input type="text" name="firstname" value="<?= htmlspecialchars($firstname) ?>" required>

                        <label class="field-label">Last Name</label>
                        <input type="text" name="lastname" value="<?= htmlspecialchars($lastname) ?>" required>

                        <label class="field-label">Username</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>

                        <hr class="divider">

                        <h4>Change Password (optional)</h4>
                        <p class="hint">Leave these blank if you don't want to change your password.</p>

                        <label class="field-label">Current Password</label>
                        <input type="password" name="current_password">

                        <label class="field-label">New Password</label>
                        <input type="password" name="new_password">

                        <label class="field-label">Confirm New Password</label>
                        <input type="password" name="confirm_password">

                        <button type="submit" class="btn-primary">Save Changes</button>
                    </form>
                </div>

            </div>
        </section>

    </main>
</div>

<script>
function confirmLogout(event) {
    event.preventDefault();
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = "logout.php";
    }
}

/* ✅ Sidebar toggle + close */
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
</script>

</body>
</html>
