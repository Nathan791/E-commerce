<?php
session_start();
require_once 'db.php';

// Generate CSRF token for security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Calculate total for display
$grand_total = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $id => $qty) {
        $stmt = $db->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        if ($p) $grand_total += ($p['price'] * $qty);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout Review</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 350px; text-align: center; }
        .total { font-size: 2.5rem; margin: 1rem 0; color: #2ecc71; font-weight: bold; }
        button { background: #6772e5; color: white; border: none; padding: 1rem; width: 100%; border-radius: 6px; font-size: 1.1rem; cursor: pointer; }
        button:hover { background: #5469d4; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Order Review</h2>
        <div class="total">$<?= number_format($grand_total, 2) ?></div>
        <form action="pay.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit">Pay with Stripe</button>
        </form>
        <p><a href="cart.php" style="color: #888; text-decoration: none;">← Back to Cart</a></p>
    </div>
</body>
</html>