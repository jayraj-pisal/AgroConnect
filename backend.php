<?php
/**
 * AgroConnect REST API – PHP Backend
 * ════════════════════════════════════════════
 * Routes all requests via a single entry-point.
 * Deploy under /backend/api.php
 *
 * Requires:  PHP 8.1+, PDO, MySQL
 * Security:  Prepared statements, bcrypt, session auth
 * ════════════════════════════════════════════
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');   // never show errors in production

// ── CORS (adjust origin in production) ──────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

set_exception_handler(function (\Throwable $e): void {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'details' => str_contains(strtolower($e->getMessage()), 'sqlstate')
            ? 'Database connection or query failed. Check MySQL and DB credentials.'
            : 'An unexpected error occurred.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
});

// ── SESSION ─────────────────────────────────
session_start();

// ── DATABASE CONFIG ──────────────────────────
define('DB_HOST', getenv('AGRO_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('AGRO_DB_NAME') ?: 'agroconnect');
define('DB_USER', getenv('AGRO_DB_USER') ?: 'root');
define('DB_PASS', getenv('AGRO_DB_PASS') !== false ? getenv('AGRO_DB_PASS') : '');
define('DB_CHAR', getenv('AGRO_DB_CHAR') ?: 'utf8mb4');

// ── HELPERS ─────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHAR);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_response('Database connection failed. Check MySQL and DB credentials.', 500);
        }
    }
    return $pdo;
}

function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function error_response(string $message, int $status = 400): never {
    json_response(['success' => false, 'error' => $message], $status);
}

function success_response(mixed $data = null, string $message = 'OK'): never {
    json_response(['success' => true, 'message' => $message, 'data' => $data]);
}

function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw ?: '{}', true) ?? [];
}

function sanitize(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function require_auth(): array {
    if (empty($_SESSION['user'])) error_response('Unauthorized', 401);
    return $_SESSION['user'];
}

function require_role(string ...$roles): array {
    $user = require_auth();
    if (!in_array($user['role'], $roles, true))
        error_response('Forbidden', 403);
    return $user;
}

// ── ROUTER ──────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$routeParam = isset($_GET['route']) ? trim((string)$_GET['route'], '/') : '';
$path   = $routeParam !== ''
    ? $routeParam
    : ($_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$path   = trim((string)$path, '/');

// Strip script path if requests come through /folder/backend.php/route
$scriptName = trim(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($scriptName === $path) {
    $path = '';
} elseif ($scriptName && str_starts_with($path, $scriptName . '/')) {
    $path = substr($path, strlen($scriptName) + 1);
}

// Strip base prefix if deployed in subfolder, e.g. "backend/"
$path   = preg_replace('#^backend/?#', '', $path);
$parts  = $path === '' ? [] : explode('/', $path);
$route  = $path;
$last   = $parts ? end($parts) : null;
$id     = ($last !== null && ctype_digit((string)$last)) ? (int)$last : null;

match ("$method:$route") {
    // ── AUTH ──
    'POST:auth/register'    => route_register(),
    'POST:auth/login'       => route_login(),
    'POST:auth/logout'      => route_logout(),
    'GET:auth/me'           => route_me(),
    // ── PRODUCTS ──
    'GET:products'          => route_list_products(),
    'POST:products'         => route_create_product(),
    'GET:products/'.$id     => route_get_product($id),
    'PUT:products/'.$id     => route_update_product($id),
    'DELETE:products/'.$id  => route_delete_product($id),
    // ── EQUIPMENT ──
    'GET:equipment'         => route_list_equipment(),
    'POST:equipment'        => route_create_equipment(),
    'GET:equipment/'.$id    => route_get_equipment($id),
    'DELETE:equipment/'.$id => route_delete_equipment($id),
    // ── ORDERS ──
    'GET:orders'            => route_list_orders(),
    'POST:orders'           => route_create_order(),
    'PUT:orders/'.$id       => route_update_order($id),
    // ── MESSAGES ──
    'GET:messages'          => route_list_messages(),
    'POST:messages'         => route_send_message(),
    // ── MARKET RATES ──
    'GET:market-rates'      => route_market_rates(),
    // ── ADMIN ──
    'GET:admin/users'       => route_admin_users(),
    'GET:admin/products'    => route_admin_products(),
    'GET:admin/orders'      => route_admin_orders(),
    'PUT:admin/users/'.$id  => route_admin_update_user($id),
    'GET:admin/stats'       => route_admin_stats(),
    // ── CATCH-ALL ──
    default => error_response("Route not found: $method /$route", 404),
};

// ════════════════════════════════════════════
//  AUTH ROUTES
// ════════════════════════════════════════════

function route_register(): void {
    $b = body();
    $name     = sanitize($b['name']     ?? '');
    $email    = filter_var($b['email']  ?? '', FILTER_VALIDATE_EMAIL);
    $phone    = sanitize($b['phone']    ?? '');
    $password = $b['password']          ?? '';
    $role     = in_array($b['role'] ?? '', ['farmer','buyer']) ? $b['role'] : 'buyer';
    $location = sanitize($b['location'] ?? '');

    if (!$name || !$email)        error_response('Name and email are required');
    if (strlen($password) < 8)   error_response('Password must be at least 8 characters');

    $db = db();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch())           error_response('Email already registered');

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, phone, password_hash, role, location) VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([$name, $email, $phone, $hash, $role, $location]);
    $uid = (int)$db->lastInsertId();

    $user = ['id' => $uid, 'name' => $name, 'email' => $email, 'role' => $role];
    $_SESSION['user'] = $user;
    success_response($user, 'Registration successful');
}

function route_login(): void {
    $b     = body();
    $email = filter_var($b['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $pass  = $b['password'] ?? '';

    if (!$email) error_response('Invalid email');

    $db   = db();
    $stmt = $db->prepare('SELECT id, name, email, password_hash, role, location FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash']))
        error_response('Invalid email or password', 401);

    // Update last login
    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    unset($user['password_hash']);
    $_SESSION['user'] = $user;
    success_response($user, 'Login successful');
}

function route_logout(): void {
    session_destroy();
    success_response(null, 'Logged out');
}

function route_me(): void {
    $user = require_auth();
    success_response($user);
}

// ════════════════════════════════════════════
//  PRODUCT ROUTES
// ════════════════════════════════════════════

function route_list_products(): void {
    $db = db();
    $where = ['p.status = ?'];
    $params = ['approved'];

    if (!empty($_GET['category'])) {
        $where[] = 'c.slug = ?';
        $params[] = $_GET['category'];
    }
    if (!empty($_GET['state'])) {
        $where[] = 'p.state = ?';
        $params[] = $_GET['state'];
    }
    if (!empty($_GET['min_price'])) {
        $where[] = 'p.price_per_kg >= ?';
        $params[] = (float)$_GET['min_price'];
    }
    if (!empty($_GET['max_price'])) {
        $where[] = 'p.price_per_kg <= ?';
        $params[] = (float)$_GET['max_price'];
    }
    if (!empty($_GET['q'])) {
        $where[] = 'MATCH(p.name, p.description) AGAINST(? IN BOOLEAN MODE)';
        $params[] = $_GET['q'] . '*';
    }

    $limit  = min((int)($_GET['limit']  ?? 20), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    $sort   = match ($_GET['sort'] ?? 'newest') {
        'price_asc'  => 'p.price_per_kg ASC',
        'price_desc' => 'p.price_per_kg DESC',
        default      => 'p.created_at DESC',
    };

    $sql = "SELECT p.*, c.name AS category_name, c.icon AS category_icon,
                   u.name AS seller_name, u.location AS seller_location
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN users      u ON p.user_id      = u.id
            WHERE " . implode(' AND ', $where) .
           " ORDER BY $sort LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Decode JSON images
    foreach ($products as &$p) {
        $p['images'] = json_decode($p['images'] ?? '[]', true);
    }

    success_response($products);
}

function route_create_product(): void {
    $user = require_role('farmer', 'admin');
    $b    = body();

    $name        = sanitize($b['name']        ?? '');
    $description = sanitize($b['description'] ?? '');
    $category_id = (int)($b['category_id']    ?? 0);
    $price       = (float)($b['price_per_kg'] ?? 0);
    $quantity    = (float)($b['quantity_kg']  ?? 0);
    $location    = sanitize($b['location']    ?? '');
    $state       = sanitize($b['state']       ?? '');
    $is_organic  = (int)(bool)($b['is_organic'] ?? false);
    $images      = json_encode($b['images'] ?? []);

    if (!$name || !$category_id || $price <= 0)
        error_response('Name, category, and price are required');

    $db   = db();
    $stmt = $db->prepare(
        'INSERT INTO products (user_id, category_id, name, description, price_per_kg,
         quantity_kg, location, state, is_organic, images, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $status = $user['role'] === 'admin' ? 'approved' : 'pending';
    $stmt->execute([$user['id'], $category_id, $name, $description, $price,
                    $quantity, $location, $state, $is_organic, $images, $status]);

    success_response(['id' => (int)$db->lastInsertId()], 'Product listed successfully');
}

function route_get_product(?int $id): void {
    if (!$id) error_response('Product ID required');
    $db   = db();
    $stmt = $db->prepare(
        'SELECT p.*, c.name AS category_name, u.name AS seller_name,
                u.phone AS seller_phone, u.location AS seller_location
         FROM products p
         JOIN categories c ON p.category_id = c.id
         JOIN users      u ON p.user_id      = u.id
         WHERE p.id = ?'
    );
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) error_response('Product not found', 404);
    $product['images'] = json_decode($product['images'] ?? '[]', true);

    // Increment view count
    $db->prepare('UPDATE products SET views = views + 1 WHERE id = ?')->execute([$id]);

    success_response($product);
}

function route_update_product(?int $id): void {
    $user = require_auth();
    if (!$id) error_response('Product ID required');
    $db   = db();
    $prod = $db->prepare('SELECT user_id FROM products WHERE id = ?');
    $prod->execute([$id]);
    $row  = $prod->fetch();
    if (!$row) error_response('Product not found', 404);
    if ($row['user_id'] !== $user['id'] && $user['role'] !== 'admin')
        error_response('Forbidden', 403);

    $b    = body();
    $sets = [];
    $params = [];
    $allowed = ['name','description','price_per_kg','quantity_kg','location','state','is_organic'];
    foreach ($allowed as $field) {
        if (isset($b[$field])) {
            $sets[]   = "$field = ?";
            $params[] = $b[$field];
        }
    }
    if (empty($sets)) error_response('No fields to update');
    $params[] = $id;
    $db->prepare('UPDATE products SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($params);
    success_response(null, 'Product updated');
}

function route_delete_product(?int $id): void {
    $user = require_auth();
    if (!$id) error_response('Product ID required');
    $db   = db();
    $stmt = $db->prepare('SELECT user_id FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row  = $stmt->fetch();
    if (!$row) error_response('Not found', 404);
    if ($row['user_id'] !== $user['id'] && $user['role'] !== 'admin') error_response('Forbidden', 403);
    $db->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    success_response(null, 'Product deleted');
}

// ════════════════════════════════════════════
//  EQUIPMENT ROUTES
// ════════════════════════════════════════════

function route_list_equipment(): void {
    $db     = db();
    $where  = ['e.status = ?'];
    $params = ['approved'];
    if (!empty($_GET['category'])) {
        $where[] = 'c.slug = ?';
        $params[] = $_GET['category'];
    }
    if (!empty($_GET['max_price'])) {
        $where[] = 'e.price <= ?';
        $params[] = (float)$_GET['max_price'];
    }
    $stmt = $db->prepare(
        'SELECT e.*, c.name AS category_name, c.icon,
                u.name AS seller_name, u.location AS seller_location
         FROM equipment_ads e
         JOIN categories c ON e.category_id = c.id
         JOIN users      u ON e.user_id      = u.id
         WHERE ' . implode(' AND ', $where) . ' ORDER BY e.created_at DESC LIMIT 50'
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    foreach ($items as &$i) $i['images'] = json_decode($i['images'] ?? '[]', true);
    success_response($items);
}

function route_create_equipment(): void {
    $user = require_auth();
    $b    = body();
    $title       = sanitize($b['title']        ?? '');
    $description = sanitize($b['description']  ?? '');
    $category_id = (int)($b['category_id']     ?? 0);
    $price       = (float)($b['price']         ?? 0);
    $condition   = $b['condition_val']         ?? 'good';
    $location    = sanitize($b['location']     ?? '');
    $contact     = sanitize($b['contact_phone']?? '');

    if (!$title || !$category_id || $price <= 0) error_response('Title, category, and price required');

    $db   = db();
    $stmt = $db->prepare(
        'INSERT INTO equipment_ads (user_id, category_id, title, description, price,
         condition_val, location, contact_phone, status)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([$user['id'], $category_id, $title, $description, $price,
                    $condition, $location, $contact, 'pending']);
    success_response(['id' => (int)$db->lastInsertId()], 'Equipment ad posted');
}

function route_get_equipment(?int $id): void {
    if (!$id) error_response('ID required');
    $db   = db();
    $stmt = $db->prepare('SELECT e.*, u.name AS seller_name FROM equipment_ads e JOIN users u ON e.user_id=u.id WHERE e.id=?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) error_response('Not found', 404);
    success_response($item);
}

function route_delete_equipment(?int $id): void {
    $user = require_auth();
    if (!$id) error_response('ID required');
    $db   = db();
    $stmt = $db->prepare('SELECT user_id FROM equipment_ads WHERE id=?');
    $stmt->execute([$id]);
    $row  = $stmt->fetch();
    if (!$row) error_response('Not found', 404);
    if ($row['user_id'] !== $user['id'] && $user['role'] !== 'admin') error_response('Forbidden', 403);
    $db->prepare('DELETE FROM equipment_ads WHERE id=?')->execute([$id]);
    success_response(null, 'Ad deleted');
}

// ════════════════════════════════════════════
//  ORDER ROUTES
// ════════════════════════════════════════════

function route_list_orders(): void {
    $user = require_auth();
    $db   = db();
    $col  = $user['role'] === 'buyer' ? 'o.buyer_id' : 'o.seller_id';
    $stmt = $db->prepare(
        "SELECT o.*, p.name AS product_name, u.name AS other_party_name
         FROM orders o
         JOIN products p ON o.product_id = p.id
         JOIN users    u ON u.id = IF(o.buyer_id=?, o.seller_id, o.buyer_id)
         WHERE $col = ? ORDER BY o.created_at DESC"
    );
    $stmt->execute([$user['id'], $user['id']]);
    success_response($stmt->fetchAll());
}

function route_create_order(): void {
    $user = require_role('buyer');
    $b    = body();
    $product_id  = (int)($b['product_id']  ?? 0);
    $quantity_kg = (float)($b['quantity_kg'] ?? 0);
    $address     = sanitize($b['delivery_address'] ?? '');
    $payment     = $b['payment_method'] ?? 'cod';

    if (!$product_id || $quantity_kg <= 0) error_response('Product and quantity required');

    $db   = db();
    $stmt = $db->prepare('SELECT user_id, price_per_kg, quantity_kg FROM products WHERE id=? AND status=?');
    $stmt->execute([$product_id, 'approved']);
    $product = $stmt->fetch();
    if (!$product) error_response('Product not found or unavailable');
    if ($product['quantity_kg'] < $quantity_kg) error_response('Insufficient stock');

    $total = round($quantity_kg * $product['price_per_kg'], 2);

    $db->beginTransaction();
    try {
        $ins = $db->prepare(
            'INSERT INTO orders (buyer_id, seller_id, product_id, quantity_kg, price_per_kg, total_amount, delivery_address, payment_method)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $ins->execute([$user['id'], $product['user_id'], $product_id, $quantity_kg,
                       $product['price_per_kg'], $total, $address, $payment]);
        $order_id = (int)$db->lastInsertId();

        // Reduce stock
        $db->prepare('UPDATE products SET quantity_kg = quantity_kg - ? WHERE id=?')
           ->execute([$quantity_kg, $product_id]);

        $db->commit();
        success_response(['order_id' => $order_id, 'total' => $total], 'Order placed successfully');
    } catch (\Throwable $e) {
        $db->rollBack();
        error_response('Order failed: ' . $e->getMessage(), 500);
    }
}

function route_update_order(?int $id): void {
    $user = require_auth();
    if (!$id) error_response('Order ID required');
    $b      = body();
    $status = $b['order_status'] ?? null;
    $valid  = ['confirmed','packed','shipped','delivered','cancelled'];
    if (!in_array($status, $valid, true)) error_response('Invalid status');
    $db     = db();
    $stmt   = $db->prepare('SELECT seller_id, order_status FROM orders WHERE id=?');
    $stmt->execute([$id]);
    $order  = $stmt->fetch();
    if (!$order) error_response('Order not found', 404);
    if ($order['seller_id'] !== $user['id'] && $user['role'] !== 'admin') error_response('Forbidden', 403);
    $db->prepare('UPDATE orders SET order_status=? WHERE id=?')->execute([$status, $id]);
    success_response(null, 'Order updated');
}

// ════════════════════════════════════════════
//  MESSAGES ROUTES
// ════════════════════════════════════════════

function route_list_messages(): void {
    $user = require_auth();
    $db   = db();
    $stmt = $db->prepare(
        'SELECT m.*, u.name AS sender_name FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE m.sender_id=? OR m.receiver_id=?
         ORDER BY m.created_at DESC LIMIT 100'
    );
    $stmt->execute([$user['id'], $user['id']]);
    success_response($stmt->fetchAll());
}

function route_send_message(): void {
    $user        = require_auth();
    $b           = body();
    $receiver_id = (int)($b['receiver_id'] ?? 0);
    $message     = sanitize($b['message']  ?? '');
    $product_id  = $b['product_id'] ? (int)$b['product_id'] : null;

    if (!$receiver_id || !$message) error_response('Receiver and message are required');
    if ($receiver_id === $user['id']) error_response('Cannot message yourself');

    $db   = db();
    $stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, product_id, message) VALUES (?,?,?,?)');
    $stmt->execute([$user['id'], $receiver_id, $product_id, $message]);
    success_response(['id' => (int)$db->lastInsertId()], 'Message sent');
}

// ════════════════════════════════════════════
//  MARKET RATES
// ════════════════════════════════════════════

function route_market_rates(): void {
    $db    = db();
    $where = ['rate_date = CURDATE()'];
    $params = [];
    if (!empty($_GET['state'])) {
        $where[] = 'state = ?';
        $params[] = $_GET['state'];
    }
    if (!empty($_GET['crop'])) {
        $where[] = 'crop_name LIKE ?';
        $params[] = '%' . $_GET['crop'] . '%';
    }
    $stmt = $db->prepare(
        'SELECT * FROM market_rates WHERE ' . implode(' AND ', $where) . ' ORDER BY crop_name'
    );
    $stmt->execute($params);
    success_response($stmt->fetchAll());
}

// ════════════════════════════════════════════
//  ADMIN ROUTES
// ════════════════════════════════════════════

function route_admin_users(): void {
    require_role('admin');
    $db   = db();
    $stmt = $db->prepare('SELECT id, name, email, role, location, is_active, is_verified, created_at FROM users ORDER BY created_at DESC');
    $stmt->execute();
    success_response($stmt->fetchAll());
}

function route_admin_products(): void {
    require_role('admin');
    $db = db();
    $stmt = $db->prepare(
        'SELECT p.id, p.name, p.price_per_kg, p.quantity_kg, p.location, p.status, p.created_at,
                c.name AS category_name, c.icon AS category_icon, u.name AS seller_name
         FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN users u ON u.id = p.user_id
         ORDER BY p.created_at DESC'
    );
    $stmt->execute();
    success_response($stmt->fetchAll());
}

function route_admin_orders(): void {
    require_role('admin');
    $db = db();
    $stmt = $db->prepare(
        'SELECT o.id, o.quantity_kg, o.total_amount, o.payment_status, o.order_status, o.created_at,
                p.name AS product_name, buyer.name AS buyer_name, seller.name AS seller_name
         FROM orders o
         JOIN products p ON p.id = o.product_id
         JOIN users buyer ON buyer.id = o.buyer_id
         JOIN users seller ON seller.id = o.seller_id
         ORDER BY o.created_at DESC'
    );
    $stmt->execute();
    success_response($stmt->fetchAll());
}

function route_admin_update_user(?int $id): void {
    require_role('admin');
    if (!$id) error_response('User ID required');
    $b    = body();
    $db   = db();
    $sets = [];
    $params = [];
    $allowed = ['is_active', 'is_verified', 'role'];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $b)) {
            $sets[]   = "$field = ?";
            $params[] = $b[$field];
        }
    }
    if (empty($sets)) error_response('No fields to update');
    $params[] = $id;
    $db->prepare('UPDATE users SET ' . implode(',', $sets) . ' WHERE id=?')->execute($params);
    success_response(null, 'User updated');
}

function route_admin_stats(): void {
    require_role('admin');
    $db = db();
    $stats = [];
    foreach ([
        'total_users'    => 'SELECT COUNT(*) FROM users',
        'total_products' => 'SELECT COUNT(*) FROM products',
        'total_orders'   => 'SELECT COUNT(*) FROM orders',
        'pending_items'  => 'SELECT COUNT(*) FROM products WHERE status="pending"',
        'revenue'        => 'SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status="paid"',
    ] as $key => $sql) {
        $stats[$key] = $db->query($sql)->fetchColumn();
    }
    success_response($stats);
}
