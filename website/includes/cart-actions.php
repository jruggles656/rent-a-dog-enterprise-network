<?php
/**
 * Cart AJAX Endpoint
 *
 * Handles AJAX requests for cart operations.
 * Accepts JSON body or form POST. Returns JSON.
 *
 * Actions: add, remove, update, get, clear
 */

require_once __DIR__ . '/data.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
// Also accept form POST
if (empty($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $type  = $input['type'] ?? '';
        $id    = (int)($input['id'] ?? 0);
        $hours = (int)($input['hours'] ?? 1);

        $success = addToCart($type, $id, $hours);
        $msg = 'Added to cart!';
        if ($success) {
            $cart = getCart();
            $last = end($cart);
            if ($last) $msg = htmlspecialchars($last['name']) . ' added to cart!';
        }
        echo json_encode([
            'success' => $success,
            'message' => $success ? $msg : 'Could not add item.',
            'cart'    => getCart(),
            'count'   => getCartCount(),
            'total'   => getCartTotal(),
        ]);
        break;

    case 'remove':
        $index = (int)($input['index'] ?? -1);
        $success = removeFromCart($index);
        echo json_encode([
            'success' => $success,
            'cart'    => getCart(),
            'count'   => getCartCount(),
            'total'   => getCartTotal(),
        ]);
        break;

    case 'update':
        $index = (int)($input['index'] ?? -1);
        $hours = (int)($input['hours'] ?? 1);
        $success = updateCartItem($index, $hours);
        echo json_encode([
            'success' => $success,
            'cart'    => getCart(),
            'count'   => getCartCount(),
            'total'   => getCartTotal(),
        ]);
        break;

    case 'get':
        echo json_encode([
            'success' => true,
            'cart'    => getCart(),
            'count'   => getCartCount(),
            'total'   => getCartTotal(),
        ]);
        break;

    case 'clear':
        clearCart();
        echo json_encode([
            'success' => true,
            'cart'    => [],
            'count'   => 0,
            'total'   => 0,
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
