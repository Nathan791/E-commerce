<?php
session_start();

// 1. Centralized Configuration
$config = [
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'commerce',
    'admin_path' => '/COMMERCE/E-commerce-main/admin-dashboard.php',
    'user_path'  => '/COMMERCE/E-commerce-main/user-dashboard.php'
];

// 2. Redirect if already logged in
if (isset($_SESSION["role"])) {
    $redirect = ($_SESSION["role"] === "admin") ? $config['admin_path'] : $config['user_path'];
    header("Location: $redirect");
    exit();
}

$errorMessage = "";

// 3. Handle Login Logic
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $errorMessage = "Please fill in all fields.";
    } else {
        try {
            $db = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
            $db->set_charset("utf8mb4");

            $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("isssi", $name,$email, $password, $role);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user["password"])) {
                    // Prevent Session Fixation
                    session_regenerate_id(true);

                    $_SESSION["id"]    = $user["id"];
                    $_SESSION["name"]  = $user["name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"]  = $user["role"];

                    $redirect = ($user["role"] === "admin") ? $config['admin_path'] : $config['user_path'];
                    header("Location: $redirect");
                    exit();
                }
            }
            $errorMessage = "Invalid email or password.";
            $stmt->close();
            $db->close();
        } catch (Exception $e) {
            error_log($e->getMessage()); // Log error to server, don't show user sensitive info
            $errorMessage = "A connection error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign-In | Commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin-top: 100px; }
        .card { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1rem 0 rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>

<div class="container login-container">
    <div class="card p-4">
        <h2 class="text-center fw-bold mb-4">Login</h2>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address</label>
                <input type="email" name="email" class="form-control" 
                       value="<?= htmlspecialchars($email ?? '') ?>" required>
            </div>

           <div class="mb-3">
                <label class="form-label small fw-semibold">Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Min. 6 characters" required>
                    <span class="input-group-text" id="togglePassword">
                        <i class='bx bx-show'></i>
                    </span>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Sign In</button>
            </div>

            <p class="text-center mt-4 mb-0">
                Don’t have an account? 
                <a href="/COMMERCE/E-commerce-main/create_users.php" class="text-decoration-none">Sign Up</a>
            </p>
        </form>
    </div>
</div>

<script>
    // Improved Toggle Logic
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

    togglePassword.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.querySelector('i').classList.toggle('bx-show');
        this.querySelector('i').classList.toggle('bx-hide');
    });
</script>
</body>
</html>