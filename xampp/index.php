<?php
header('Content-Type: application/json');

// CORS Configuration
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowedOrigins = [
    'http://localhost:4200',
    'http://localhost:8100',
    'http://127.0.0.1:4200',
    'http://127.0.0.1:8100'
];

if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();
date_default_timezone_set('Asia/Manila');

function reply($data) {
    echo json_encode($data);
    exit;
}

function fail($msg, $code = 400) {
    http_response_code($code);
    reply(['error' => $msg]);
}

try {

    
   // DATABASE CONNECTION
    require __DIR__ . '/config.php';

    $db = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $a = $_GET['action'] ?? '';
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    if (
        !in_array($a, ['services', 'me', 'bookings']) &&
        $_SERVER['REQUEST_METHOD'] !== 'POST'
    ) {
        fail('POST required', 405);
    }

    // GET SERVICES
    if ($a === 'services') {
        reply(
            $db->query(
                'SELECT * FROM services ORDER BY id'
            )->fetchAll()
        );
    }

    // CURRENT USER
    if ($a === 'me') {
        reply($_SESSION['user'] ?? null);
    }

    // LOGOUT
    if ($a === 'logout') {
        session_destroy();
        reply(['ok' => true]);
    }

    // REGISTER AND LOGIN
    if ($a === 'register' || $a === 'login') {

        $email = strtolower(trim($d['email'] ?? ''));
        $pw = $d['password'] ?? '';
        $name = trim($d['name'] ?? '');

        if ($a === 'register') {

            if (
                !$name ||
                strlen($name) > 120 ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 190 ||
                strlen($pw) < 6 ||
                strlen($pw) > 72
            ) {
                fail(
                    'Enter valid name, email and password (6–72 characters).'
                );
            }

            // Check existing email
            $q = $db->prepare(
                'SELECT id FROM users WHERE email = ?'
            );

            $q->execute([$email]);

            if ($q->fetch()) {
                fail('Email already registered.');
            }

            // Create account
            $q = $db->prepare(
                'INSERT INTO users
                (name, email, password_hash)
                VALUES (?, ?, ?)'
            );

            $q->execute([
                $name,
                $email,
                password_hash($pw, PASSWORD_DEFAULT)
            ]);
        }

        // Authenticate user
        $q = $db->prepare(
            'SELECT * FROM users WHERE email = ?'
        );

        $q->execute([$email]);
        $u = $q->fetch();

        if (
            !$u ||
            !password_verify($pw, $u['password_hash'])
        ) {
            fail('Invalid email or password.', 401);
        }

        session_regenerate_id(true);

        unset(
            $u['password_hash'],
            $u['created_at']
        );

        $_SESSION['user'] = $u;

        reply($u);
    }

    // CHECK USER SESSION
    $u = $_SESSION['user'] ?? null;

    if (!$u) {
        fail('Please log in.', 401);
    }

    // GET BOOKINGS
    if ($a === 'bookings') {

        $q = $db->prepare(
            'SELECT
                b.reference,
                u.email,
                b.service_id AS serviceId,
                s.name AS serviceName,
                b.checkin_date AS date,
                TIME_FORMAT(b.checkin_time, "%H:%i") AS time,
                b.quantity,
                b.total,
                b.status,
                b.payment_method AS paymentMethod
            FROM bookings b
            JOIN services s ON s.id = b.service_id
            JOIN users u ON u.id = b.user_id
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC'
        );

        $q->execute([$u['id']]);

        reply($q->fetchAll());
    }

    // CREATE BOOKING
    if ($a === 'create') {

        $q = $db->prepare(
            'SELECT * FROM services WHERE id = ?'
        );

        $q->execute([$d['serviceId'] ?? 0]);

        $s = $q->fetch();

        $date = $d['date'] ?? '';
        $time = $d['time'] ?? '';

        $n = filter_var(
            $d['quantity'] ?? 0,
            FILTER_VALIDATE_INT
        );

        $dt = DateTime::createFromFormat(
            '!Y-m-d',
            $date
        );

        if (
            !$s ||
            !$dt ||
            $dt->format('Y-m-d') !== $date ||
            $date < date('Y-m-d') ||
            !preg_match(
                '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',
                $time
            ) ||
            $n === false ||
            $n < 1 ||
            $n > 10
        ) {
            fail('Invalid booking details.');
        }

        $ref = 'STY-' . strtoupper(
            bin2hex(random_bytes(8))
        );

        $total = $s['price'] * $n;

        $q = $db->prepare(
            'INSERT INTO bookings
            (
                reference,
                user_id,
                service_id,
                checkin_date,
                checkin_time,
                quantity,
                total
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $q->execute([
            $ref,
            $u['id'],
            $s['id'],
            $date,
            $time,
            $n,
            $total
        ]);

        reply([
            'reference' => $ref,
            'email' => $u['email'],
            'serviceId' => (int) $s['id'],
            'serviceName' => $s['name'],
            'date' => $date,
            'time' => $time,
            'quantity' => $n,
            'total' => $total,
            'status' => 'Awaiting payment'
        ]);
    }

    // CANCEL BOOKING (only the signed-in owner's booking)
    if ($a === 'cancel') {
        $ref = trim((string) ($d['reference'] ?? ''));
        if ($ref === '') fail('Booking reference is required.');
        $db->beginTransaction();
        $q = $db->prepare('SELECT status FROM bookings WHERE reference = ? AND user_id = ? FOR UPDATE');
        $q->execute([$ref, $u['id']]);
        $b = $q->fetch();
        if (!$b) {
            $db->rollBack();
            fail('Booking not found.', 404);
        }
        if ($b['status'] !== 'Cancelled') {
            $q = $db->prepare("UPDATE bookings SET status = 'Cancelled' WHERE reference = ? AND user_id = ?");
            $q->execute([$ref, $u['id']]);
        }
        $db->commit();
        reply(['ok' => true, 'status' => 'Cancelled']);
    }

    // DEMO PAYMENT
    if ($a === 'pay') {

        $statuses = [
            'success' => 'Confirmed',
            'pending' => 'Payment pending',
            'failed' => 'Payment failed'
        ];

        $status = $statuses[
            $d['outcome'] ?? ''
        ] ?? null;

        $method = $d['method'] ?? '';

        if (
            !$status ||
            !in_array(
                $method,
                ['Demo Wallet', 'Pay at Property']
            )
        ) {
            fail('Invalid payment.');
        }

        $db->beginTransaction();

        $q = $db->prepare(
            'SELECT * FROM bookings
            WHERE reference = ?
            AND user_id = ?
            FOR UPDATE'
        );

        $q->execute([
            $d['reference'] ?? '',
            $u['id']
        ]);

        $b = $q->fetch();

        if (!$b || in_array($b['status'], ['Confirmed', 'Cancelled'], true)) {
            $db->rollBack();
            fail('Booking unavailable for payment.');
        }

        $q = $db->prepare(
            'UPDATE bookings
            SET status = ?, payment_method = ?
            WHERE reference = ?'
        );

        $q->execute([
            $status,
            $method,
            $b['reference']
        ]);

        $q = $db->prepare(
            'INSERT INTO payments
            (booking_reference, method, status, amount)
            VALUES (?, ?, ?, ?)'
        );

        $q->execute([
            $b['reference'],
            $method,
            $status,
            $b['total']
        ]);

        $db->commit();

        reply(['ok' => true]);
    }

    fail('Unknown action', 404);

} catch (Throwable $e) {

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log($e->getMessage());

    fail('Server error: ' . $e->getMessage(), 500);
}
