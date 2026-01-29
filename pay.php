<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/vendor/autoload.php';
// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Accessing via $_ENV or getenv()
$stripeKey = $_ENV['STRIPE_SECRET'] ?? getenv('STRIPE_SECRET');

if (!$stripeKey) {
    http_response_code(500);
    // Be specific in logs to help debugging
    error_log('Stripe Error: STRIPE_SECRET is not set in .env or system environment.');
    http_response_code(500);
    die('Internal Server Error: Payment configuration is missing.');
}

\Stripe\Stripe::setApiKey($stripeKey);
// 1. Security: Check Request Method & CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Security validation failed.");
}

// 2. Validate Cart
if (empty($_SESSION['cart'])) {
    header("Location: shop.php");
    exit();
}

$line_items = [];
$order_total_cents = 0;

// 3. Prepare items & verify prices server-side
foreach ($_SESSION['cart'] as $productId => $quantity) {
    $stmt = $db->prepare("SELECT name, price FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if ($product) {
        // Use round() and (int) to prevent floating point math errors
        $unit_cents = (int)round($product['price'] * 100);
        $line_items[] = [
            'price_data' => [
                'currency' => 'usd',
                'product_data' => ['name' => $product['name']],
                'unit_amount' => $unit_cents,
            ],
            'quantity' => (int)$quantity,
        ];
        $order_total_cents += ($unit_cents * $quantity);
    }
}

// 4. Create "Pending" Order in DB before redirecting
// This is critical for tracking abandoned checkouts
$user_id = $_SESSION['user_id'] ?? 0;
$stmt = $db->prepare("INSERT INTO orders (user_id, total, status) VALUES (?, ?, 'pending')");
$total_decimal = $order_total_cents / 100;
$stmt->bind_param("id", $user_id, $total_decimal);
$stmt->execute();
$order_id = $db->insert_id;

// ... (Your existing loading and DB logic above)

// 5. Create Stripe Session
try {
    $session = \Stripe\Checkout\Session::create([
        // REMOVE 'payment_method_types' => ['card'], 
        // USE THIS INSTEAD:
        'automatic_payment_methods' => [
            'enabled' => true,
        ],
        'line_items' => $line_items,
        'mode' => 'payment',
        // Fix: Added leading slash to success path
        'success_url' => rtrim($_ENV['BASE_URL'], '/') . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => rtrim($_ENV['BASE_URL'], '/') . '/checkout.php',
        'metadata' => [
            'order_id' => $order_id 
        ],
    ]);

    header("HTTP/1.1 303 See Other");
    header("Location: " . $session->url);
} catch (Exception $e) {
    error_log("Stripe Error: " . $e->getMessage());
    die("Gateway error: " . $e->getMessage());
}