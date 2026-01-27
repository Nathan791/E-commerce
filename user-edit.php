<?php
require "auth.php";

// 1. Database Connection
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli("localhost", "root", "", "commerce");
    $db->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Connection failed.");
}

// 2. Fetch User Data Safely
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

// 3. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name  = $_POST["name"];
    $email = $_POST["email"];
    $role  = $_POST["role"];
    $password = $_POST["password"];

    if (!empty($password)) {
        // Update everything INCLUDING password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $email, $role, $hashedPassword, $id);
    } else {
        // Update everything EXCEPT password
        $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $email, $role, $id);
    }

    if ($stmt->execute()) {
        header("Location: users.php?success=updated");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; padding-top: 50px; }
        .signup-card { max-width: 500px; margin: auto; border-radius: 15px; border: none; }
    </style>
</head>
<body>
    
<div class="container">
    <div class="card signup-card shadow-sm p-4">
        <div class="d-flex align-items-center mb-4">
            <a href="users.php" class="text-dark me-2">
                <i class='bx bx-left-arrow-alt fs-2'></i>
            </a>
            <h2 class="h4 m-0 fw-bold">Edit User: <strong><?= htmlspecialchars($user['name']) ?></strong></h2>
        </div>

        <form method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>

            <div class="mb-3">
                 <label for="password" class="form-label">New Password</label>
                 <small class="text-muted d-block mb-2">(Leave blank to keep current password)</small>
                <input type="password" class="form-control" id="password" name="password" placeholder="••••••••">
            </div>
               
            <div class="mb-4">
                <label for="role" class="form-label">Access Level</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="user" <?= ($user['role'] ?? '') === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class='bx bx-save me-1'></i> Update Account
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>