<?php
session_start();

$name = "";
$email = "";
$errorMessage = "";
$successMessage = "";

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF check
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errorMessage = "Invalid request.";
    } else {

        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        // 2. Validation
    if (strlen($name) < 3) $errors[] = "Name must be at least 3 characters.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
        if ($name === "" || $email === "" || $password === "") {
            $errorMessage = "All fields are required.";
        } else {

            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            try {
                $db = new mysqli("localhost", "root", "", "commerce");
                $db->set_charset("utf8mb4");

                // Email unique
                $check = $db->prepare("SELECT id FROM users WHERE email = ?");
                $check->bind_param("s", $email);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $errorMessage = "Email already exists.";
                } else {

                    // 🔒 ROLE CONTROL
                    $role = "user";
                    if (
                        isset($_SESSION["role"]) &&
                        $_SESSION["role"] === "admin" &&
                        isset($_POST["role"]) &&
                        in_array($_POST["role"], ["user", "admin"], true)
                    ) {
                        $role = $_POST["role"];
                    }

                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    $stmt = $db->prepare(
                        "INSERT INTO users (name, email, password, role)
                         VALUES (?, ?, ?, ?)"
                    );

                    $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
                    $stmt->execute();

                    $successMessage = "Account created successfully.";
                    $name = $email = "";
                }

            } catch (Exception $e) {
                $errorMessage = "Server error.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | Premium Store</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; }
        .signup-card { max-width: 450px; width: 100%; margin: auto; border: none; border-radius: 12px; }
        .input-group-text { cursor: pointer; background: white; }
    </style>
</head>
<body>

<div class="container">
    <div class="card signup-card shadow-sm p-4">
        <div class="d-flex align-items-center mb-4">
            <a href="javascript:history.back()" class="text-dark me-2"><i class='bx bx-left-arrow-alt fs-2'></i></a>
            <h2 class="h4 m-0 fw-bold">Create Account</h2>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 small">
                <ul class="mb-0">
                    <?php foreach($errors as $error) echo "<li>$error</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label class="form-label small fw-semibold">Username</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" placeholder="Full Name" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" placeholder="name@company.com" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Min. 6 characters"  required>
                    <span class="input-group-text" id="togglePassword">
                        <i class='bx bx-show'></i>
                    </span>
                </div>
            </div>

            
            <div class="mb-3">
                <label class="form-label small fw-bold text-primary">Assign Account Role</label>
                <select name="role" class="form-select border-primary">
                    <option value="user">User (Default)</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
             

            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold mb-3 shadow-sm">Sign Up</button>

            <p class="text-center small text-muted">
                By signing up, you agree to our <a href="#" class="text-decoration-none">Terms</a>.
            </p>
            
            <hr>
            
            <p class="text-center small">
                Already registered? <a href="login.php" class="fw-bold text-decoration-none">Sign In</a>
            </p>
        </form>
    </div>
</div>

<script>
    // Password Toggle Logic
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

    togglePassword.addEventListener('click', function (e) {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.querySelector('i').classList.toggle('bx-show');
        this.querySelector('i').classList.toggle('bx-hide');
    });
</script>

</body>
</html>