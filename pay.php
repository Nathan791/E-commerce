<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION["email"]) || empty($_SESSION['cart'])) {
    header("Location: login.php");
    exit();
}

// 1. Setup CSRF for security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";

// 2. TRANSACTION LOGIC: Only runs when "Pay Now" is clicked
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token validation failed.");
    }

    $db->begin_transaction();
    try {
        $userId = $_SESSION['user_id'];
        $totalOrderAmount = 0;
        $orderItems = [];

        // Single loop to verify and lock stock
        foreach ($_SESSION['cart'] as $id => $qty) {
            $stmt = $db->prepare("SELECT price, stock FROM products WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();

            if (!$product || $product['stock'] < $qty) {
                throw new Exception("Product ID $id is out of stock.");
            }

            $price = $product['price'];
            $totalOrderAmount += ($price * $qty);
            $orderItems[] = ['id' => $id, 'qty' => $qty, 'price' => $price];
        }

        // Insert Order
        $stmt = $db->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'paid')");
        $stmt->bind_param("id", $userId, $totalOrderAmount);
        $stmt->execute();
        $orderId = $db->insert_id;

        // Bulk Update Stock & Insert Items
        $updateStock = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $insertItem = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");

        foreach ($orderItems as $item) {
            $updateStock->bind_param("ii", $item['qty'], $item['id']);
            $updateStock->execute();

            $insertItem->bind_param("iiid", $orderId, $item['id'], $item['qty'], $item['price']);
            $insertItem->execute();
        }

        $db->commit();
        unset($_SESSION['cart']);
        header("Location: payment_success.php?order_id=" . $orderId);
        exit();

    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
    }
}

// 3. PRE-DISPLAY LOGIC: Calculate total for the UI
$displayTotal = 0;
$cartIds = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($cartIds), '?'));
$stmt = $db->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($cartIds)), ...$cartIds);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $displayTotal += $row['price'] * $_SESSION['cart'][$row['id']];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-Out | Commerce</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .checkout-card { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; text-align: center; }
        .total { font-size: 2rem; font-weight: bold; color: #28a745; margin: 1rem 0; }
        .btn-pay { background: #28a745; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 1.1rem; cursor: pointer; width: 100%; transition: 0.2s; }
        .btn-pay:hover { background: #218838; }
        .btn-pay:disabled { background: #ccc; cursor: not-allowed; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="checkout-card">
        <h2>Confirm Order</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <p>Your total is:</p>
        <div class="total">$<?= number_format($displayTotal, 2) ?></div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <button type="submit" class="btn-pay" id="payBtn">Pay Now</button>
        </form>
    </div>

    <script>
        document.querySelector('form').onsubmit = function() {
            const btn = document.getElementById('payBtn');
            btn.disabled = true;
            btn.innerText = "Processing Transaction...";
        };
    </script>
</body>
</html>