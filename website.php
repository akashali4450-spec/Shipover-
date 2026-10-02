<?php
/*
|--------------------------------------------------------------------------
| ONE FILE WEBSITE
|--------------------------------------------------------------------------
| Upload this single file as:
| public_html/index.php
|
| 1) Change DB settings below.
| 2) Open the website once. Tables are created automatically.
| 3) Admin login:
|      URL:  ?page=admin
|      Username: admin
|      Password: admin123
|    CHANGE THESE TWO values before going live.
|--------------------------------------------------------------------------
*/

session_start();

/* =========================
   DATABASE SETTINGS
   ========================= */
$dbHost = 'localhost';
$dbName = 'mywebsite';
$dbUser = 'root';
$dbPass = '';

/* =========================
   ADMIN SETTINGS
   ========================= */
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'admin123';

/* =========================
   DEFAULT PLANS
   ========================= */
$defaultPlans = [
    ['400 Plan',   400,    150,   '0.80%'],
    ['700 Plan',   700,    200,   '0.91%'],
    ['1000 Plan',  1000,   350,   '1.00%'],
    ['3000 Plan',  3000,   2000,  '1.08%'],
    ['5000 Plan',  5000,   1500,  '1.16%'],
    ['10000 Plan', 10000,  4000,  '1.30%'],
    ['30000 Plan', 30000,  10000, '1.45%'],
    ['70000 Plan', 70000,  30000, '1.60%'],
];

$pdo = null;
$dbError = '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            balance DECIMAL(12,2) NOT NULL DEFAULT 200,
            total_income DECIMAL(12,2) NOT NULL DEFAULT 0,
            team_income DECIMAL(12,2) NOT NULL DEFAULT 0,
            referral_code VARCHAR(50) NOT NULL UNIQUE,
            status ENUM('active','blocked') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            price DECIMAL(12,2) NOT NULL,
            rate VARCHAR(30) NOT NULL DEFAULT '0.80%',
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            plan_id INT UNSIGNED NULL,
            customer_name VARCHAR(150) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            transaction_id VARCHAR(150) DEFAULT '',
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('pending','paid','rejected','completed') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bank_cards (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            bank_name VARCHAR(100) NOT NULL,
            account_name VARCHAR(150) NOT NULL,
            account_number VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_methods (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            method_key VARCHAR(50) NOT NULL UNIQUE,
            method_name VARCHAR(100) NOT NULL,
            account_name VARCHAR(150) DEFAULT '',
            account_number VARCHAR(100) DEFAULT '',
            instructions TEXT DEFAULT '',
            active TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();

    if ($count === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO plans (name, amount, price, rate, active, sort_order)
            VALUES (?, ?, ?, ?, 1, ?)
        ");

        foreach ($defaultPlans as $i => $p) {
            $stmt->execute([$p[0], $p[1], $p[2], $p[3], $i + 1]);
        }
    }

    $paymentCount = (int)$pdo->query("SELECT COUNT(*) FROM payment_methods")->fetchColumn();

    if ($paymentCount === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO payment_methods
            (method_key, method_name, account_name, account_number, instructions)
            VALUES (?, ?, '', '', 'Payment details can be updated from Admin.')
        ");

        foreach ([
            ['easypaisa', 'Easypaisa'],
            ['jazzcash', 'JazzCash'],
            ['p2c', 'P2C']
        ] as $pm) {
            $stmt->execute([$pm[0], $pm[1]]);
        }
    }

} catch (Throwable $e) {
    $dbError = 'Database connection failed. Check DB settings at the top of this file.';
}

/* =========================
   HELPERS
   ========================= */
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v): string {
    return 'Rs.' . number_format((float)$v, 0);
}

function redirect($url): never {
    header('Location: ' . $url);
    exit;
}

function loggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function adminLoggedIn(): bool {
    return !empty($_SESSION['admin_logged']);
}

function user(PDO $pdo): ?array {
    if (!loggedIn()) return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();

    return $u ?: null;
}

/* =========================
   POST ACTIONS
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /* REGISTER */
    if ($action === 'register') {

        if (!$pdo) {
            $error = $dbError;
        } else {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            if ($name === '' || $phone === '' || strlen($password) < 6) {
                $error = 'Name, phone and minimum 6 character password are required.';
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
                $check->execute([$phone]);

                if ($check->fetch()) {
                    $error = 'This phone number is already registered.';
                } else {
                    $ref = strtoupper(substr(md5($phone . microtime(true)), 0, 8));

                    $stmt = $pdo->prepare("
                        INSERT INTO users
                        (name, phone, password, balance, referral_code)
                        VALUES (?, ?, ?, 200, ?)
                    ");

                    $stmt->execute([
                        $name,
                        $phone,
                        password_hash($password, PASSWORD_DEFAULT),
                        $ref
                    ]);

                    $_SESSION['user_id'] = (int)$pdo->lastInsertId();
                    redirect('?page=home');
                }
            }
        }
    }

    /* LOGIN */
    if ($action === 'login') {

        if (!$pdo) {
            $error = $dbError;
        } else {
            $phone = trim($_POST['phone'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
            $stmt->execute([$phone]);
            $u = $stmt->fetch();

            if (!$u || !password_verify($password, $u['password'])) {
                $error = 'Invalid phone number or password.';
            } elseif ($u['status'] !== 'active') {
                $error = 'Your account is blocked.';
            } else {
                $_SESSION['user_id'] = (int)$u['id'];
                redirect('?page=home');
            }
        }
    }

    /* LOGOUT */
    if ($action === 'logout') {
        unset($_SESSION['user_id']);
        redirect('?page=login');
    }

    /* SAVE BANK CARD */
    if ($action === 'save_card' && loggedIn() && $pdo) {

        $bank = trim($_POST['bank_name'] ?? '');
        $accountName = trim($_POST['account_name'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');

        if ($bank === '' || $accountName === '' || $accountNumber === '') {
            $error = 'Please fill all bank details.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO bank_cards
                (user_id, bank_name, account_name, account_number)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $_SESSION['user_id'],
                $bank,
                $accountName,
                $accountNumber
            ]);

            redirect('?page=me&saved=1');
        }
    }

    /* PLACE ORDER */
    if ($action === 'place_order' && loggedIn() && $pdo) {

        $planId = (int)($_POST['plan_id'] ?? 0);
        $payment = trim($_POST['payment_method'] ?? '');
        $transaction = trim($_POST['transaction_id'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND active = 1 LIMIT 1");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();

        $u = user($pdo);

        if (!$plan || !$u) {
            $error = 'Invalid plan.';
        } elseif ($payment === '') {
            $error = 'Select a payment method.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO orders
                (user_id, plan_id, customer_name, phone, payment_method, transaction_id, amount)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $u['id'],
                $plan['id'],
                $u['name'],
                $u['phone'],
                $payment,
                $transaction,
                $plan['price']
            ]);

            redirect('?page=orders&success=1');
        }
    }

    /* ADMIN LOGIN */
    if ($action === 'admin_login') {

        $au = trim($_POST['username'] ?? '');
        $ap = (string)($_POST['password'] ?? '');

        if (hash_equals($ADMIN_USER, $au) && hash_equals($ADMIN_PASS, $ap)) {
            $_SESSION['admin_logged'] = true;
            redirect('?page=admin');
        } else {
            $error = 'Invalid admin login.';
        }
    }

    /* ADMIN LOGOUT */
    if ($action === 'admin_logout') {
        unset($_SESSION['admin_logged']);
        redirect('?page=admin_login');
    }

    /* ADMIN ADD PLAN */
    if ($action === 'admin_add_plan' && adminLoggedIn() && $pdo) {

        $name = trim($_POST['name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $rate = trim($_POST['rate'] ?? '0.80%');

        if ($name !== '' && $amount > 0 && $price > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO plans (name, amount, price, rate, active, sort_order)
                VALUES (?, ?, ?, ?, 1, 99)
            ");
            $stmt->execute([$name, $amount, $price, $rate]);
        }

        redirect('?page=admin');
    }

    /* ADMIN PAYMENT METHOD */
    if ($action === 'admin_payment' && adminLoggedIn() && $pdo) {

        $id = (int)($_POST['id'] ?? 0);
        $accountName = trim($_POST['account_name'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');

        $stmt = $pdo->prepare("
            UPDATE payment_methods
            SET account_name = ?, account_number = ?, instructions = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $accountName,
            $accountNumber,
            $instructions,
            $id
        ]);

        redirect('?page=admin');
    }
}

/* =========================
   PAGE
   ========================= */
$page = $_GET['page'] ?? (loggedIn() ? 'home' : 'login');

if ($page !== 'admin_login' && $page !== 'admin' && $page !== 'login' && $page !== 'register') {
    if (!loggedIn()) {
        redirect('?page=login');
    }
}

if (in_array($page, ['admin', 'admin_login'], true)) {
    /* Admin pages are separate from user pages */
} elseif ($page !== 'login' && $page !== 'register' && !loggedIn()) {
    redirect('?page=login');
}

$currentUser = ($pdo && loggedIn()) ? user($pdo) : null;

if (loggedIn() && !$currentUser) {
    unset($_SESSION['user_id']);
    redirect('?page=login');
}

if ($pdo) {
    $plans = $pdo->query("
        SELECT * FROM plans
        WHERE active = 1
        ORDER BY sort_order ASC, id ASC
    ")->fetchAll();

    if (!$plans) {
        foreach ($defaultPlans as $i => $p) {
            $plans[] = [
                'id' => 0,
                'name' => $p[0],
                'amount' => $p[1],
                'price' => $p[2],
                'rate' => $p[3]
            ];
        }
    }
} else {
    $plans = [];

    foreach ($defaultPlans as $i => $p) {
        $plans[] = [
            'id' => 0,
            'name' => $p[0],
            'amount' => $p[1],
            'price' => $p[2],
            'rate' => $p[3]
        ];
    }
}

function headerStart(string $title = 'MyApp'): void {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{font-family:Arial,Helvetica,sans-serif;background:#eef3f7;color:#182733}
body{min-height:100vh}
a{text-decoration:none;color:inherit}
button,input,select,textarea{font-family:inherit}
.app{width:100%;max-width:520px;min-height:100vh;margin:auto;background:#f7f9fb;padding-bottom:76px}
.topbar{height:62px;background:linear-gradient(135deg,#0878b9,#00558d);color:#fff;padding:0 15px;display:flex;align-items:center;justify-content:space-between}
.brand{display:flex;align-items:center;gap:9px}
.logo{width:35px;height:35px;background:#fff;color:#0878b9;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:18px}
.brand strong{display:block;font-size:16px}.brand small{display:block;font-size:9px;opacity:.8;margin-top:2px}
.top-action{font-size:11px}
.content{padding:12px}
.card{background:#fff;border-radius:9px;padding:14px;box-shadow:0 2px 10px rgba(0,0,0,.05)}
.account{background:linear-gradient(135deg,#076da8,#074d78);color:#fff}
.account-head{display:flex;align-items:center}
.avatar{width:43px;height:43px;border-radius:50%;background:#fff;color:#0878b9;display:flex;align-items:center;justify-content:center;font-weight:bold;margin-right:10px}
.account-info{flex:1}.account-info h3{font-size:14px}.account-info p{font-size:9px;opacity:.8;margin-top:4px}
.red-btn{background:#e83251;color:#fff;border:0;border-radius:5px;padding:8px 10px;font-size:9px}
.account-stats{border-top:1px solid rgba(255,255,255,.15);margin-top:13px;padding-top:12px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.account-stats span{display:block;font-size:8px;opacity:.72}.account-stats strong{display:block;font-size:12px;margin-top:4px}
.banner{height:145px;margin-top:12px;border-radius:9px;overflow:hidden;position:relative;padding:21px;background:linear-gradient(120deg,#075f92,#0a8ac3);color:#fff}
.banner h1{font-size:28px;margin:5px 0}.banner p{font-size:11px;opacity:.9}.banner small{font-size:8px;opacity:.75}
.white-btn{border:0;background:#fff;color:#076da6;padding:8px 13px;border-radius:5px;font-size:9px;font-weight:bold;margin-top:13px}
.circle1,.circle2{position:absolute;border-radius:50%;background:rgba(255,255,255,.09)}.circle1{width:190px;height:190px;right:-70px;top:-70px}.circle2{width:95px;height:95px;right:30px;bottom:-45px;background:rgba(220,45,78,.65)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:10px}.stat{background:#fff;border-radius:7px;text-align:center;padding:11px 3px;box-shadow:0 2px 8px rgba(0,0,0,.04)}.stat strong{display:block;font-size:13px;color:#0876ad}.stat span{display:block;color:#929da7;font-size:7px;margin-top:4px}
.section-title{display:flex;justify-content:space-between;align-items:center;margin:18px 0 9px}.section-title h2{font-size:17px}.section-title p{font-size:9px;color:#9aa3ad;margin-top:3px}.view{font-size:9px;color:#0878b9}
.product{background:#fff;border-radius:8px;padding:9px;display:flex;margin-bottom:9px;box-shadow:0 2px 9px rgba(0,0,0,.05)}
.product-img{width:92px;height:92px;flex-shrink:0;border-radius:6px;position:relative;overflow:hidden;background:linear-gradient(#8bd0ed,#edf6eb)}
.sun{position:absolute;width:23px;height:23px;background:#f8c94a;border-radius:50%;right:14px;top:12px}.mountain{position:absolute;bottom:0;left:0;width:100%;height:58%;background:#408b66;clip-path:polygon(0 100%,20% 55%,38% 75%,62% 20%,100% 100%)}
.product-body{padding-left:10px;flex:1;min-width:0}.product-body h3{font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.rate{font-size:10px;color:#0878b9;font-weight:bold;margin-top:5px}.rate span{font-size:7px;color:#9da6ae;font-weight:normal;margin-left:4px}.p-row{display:flex;gap:23px;margin-top:8px}.p-row small{display:block;font-size:7px;color:#9ba4ac}.p-row strong{display:block;font-size:10px;margin-top:2px}.p-bottom{display:flex;align-items:center;justify-content:space-between;margin-top:8px}.p-bottom span{font-size:7px;color:#9ba4ac}.blue-btn{border:0;background:#0878b9;color:#fff;border-radius:4px;padding:6px 12px;font-size:8px}
.bottom-nav{position:fixed;z-index:100;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:520px;height:62px;background:#fff;border-top:1px solid #e5eaee;display:grid;grid-template-columns:repeat(4,1fr);box-shadow:0 -3px 15px rgba(0,0,0,.06)}
.nav{display:flex;align-items:center;justify-content:center;flex-direction:column;gap:3px;color:#98a2aa;font-size:8px}.nav-icon{width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:17px;border-radius:6px}.nav.active{color:#0878b9}.nav.active .nav-icon{background:#0878b9;color:#fff}
.form-card{margin:14px 12px}.form-card h2{font-size:20px;margin-bottom:5px}.muted{font-size:10px;color:#929da7;margin-bottom:15px}.input{width:100%;padding:12px;border:1px solid #dfe5e9;border-radius:6px;outline:none;margin-bottom:10px;font-size:12px;background:#fff}.input:focus{border-color:#0878b9}.full-btn{width:100%;padding:12px;border:0;border-radius:6px;background:#0878b9;color:#fff;font-weight:bold;font-size:12px}.link-row{text-align:center;margin-top:14px;font-size:10px;color:#0878b9}.error{background:#ffe9ed;color:#b8203b;border-radius:6px;padding:10px;font-size:10px;margin-bottom:10px}.success{background:#e8fff1;color:#087443;border-radius:6px;padding:10px;font-size:10px;margin-bottom:10px}
.order{margin-bottom:9px}.order-head{display:flex;justify-content:space-between}.order h3{font-size:12px}.order small{font-size:8px;color:#9aa3ad}.status{font-size:8px;padding:4px 7px;border-radius:10px;background:#fff3dc;color:#a56a00}.status.paid,.status.completed{background:#e5fff0;color:#087443}.status.rejected{background:#ffe9ed;color:#b8203b}.order-info{display:grid;grid-template-columns:1fr 1fr;margin-top:10px;gap:6px}.order-info span{font-size:8px;color:#9aa3ad}.order-info strong{display:block;font-size:10px;margin-top:3px}
.profile-head{background:linear-gradient(135deg,#0878b9,#07517f);color:#fff;border-radius:9px;padding:20px;text-align:center}.big-avatar{width:60px;height:60px;background:#fff;color:#0878b9;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:25px;margin:auto}.profile-head h2{font-size:16px;margin-top:8px}.profile-head p{font-size:9px;opacity:.8;margin-top:4px}
.menu{background:#fff;border-radius:8px;margin-top:10px;overflow:hidden}.menu a{display:flex;justify-content:space-between;padding:14px;border-bottom:1px solid #eef1f3;font-size:11px}.menu a:last-child{border:0}.menu span{color:#0878b9}
.admin{max-width:900px;margin:auto;padding:15px}.admin table{width:100%;border-collapse:collapse;background:#fff;font-size:11px}.admin th,.admin td{padding:9px;border-bottom:1px solid #e9edf0;text-align:left}.admin .card{margin-bottom:15px}.admin h2{margin-bottom:10px}.admin-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:500px){.admin-grid{grid-template-columns:1fr}}
@media(min-width:600px){body{padding:20px 0}.app{border-radius:12px;overflow:hidden;box-shadow:0 0 35px rgba(0,0,0,.1)}}
</style>
</head>
<body>
<?php
}

function headerEnd(): void {
?>
</body>
</html>
<?php
}

/* =========================
   ADMIN LOGIN
   ========================= */
if ($page === 'admin_login') {
    headerStart('Admin Login');
?>
<div class="admin" style="max-width:480px">
    <div class="card" style="margin-top:50px">
        <h2>Admin Login</h2>
        <p class="muted">Manage plans, payments and orders.</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="admin_login">
            <input class="input" name="username" placeholder="Username" required>
            <input class="input" type="password" name="password" placeholder="Password" required>
            <button class="full-btn">Login</button>
        </form>
    </div>
</div>
<?php
    headerEnd();
    exit;
}

/* =========================
   ADMIN DASHBOARD
   ========================= */
if ($page === 'admin') {

    if (!adminLoggedIn()) {
        redirect('?page=admin_login');
    }

    $adminOrders = [];
    $payments = [];
    $adminPlans = [];

    if ($pdo) {
        $adminOrders = $pdo->query("
            SELECT o.*, p.name AS plan_name
            FROM orders o
            LEFT JOIN plans p ON p.id = o.plan_id
            ORDER BY o.id DESC
            LIMIT 100
        ")->fetchAll();

        $payments = $pdo->query("
            SELECT * FROM payment_methods ORDER BY id ASC
        ")->fetchAll();

        $adminPlans = $pdo->query("
            SELECT * FROM plans ORDER BY sort_order ASC, id ASC
        ")->fetchAll();
    }

    headerStart('Admin');
?>
<div class="admin">

    <div class="topbar" style="border-radius:9px;margin-bottom:12px">
        <div class="brand">
            <div class="logo">A</div>
            <div>
                <strong>MyApp Admin</strong>
                <small>Control Panel</small>
            </div>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="admin_logout">
            <button class="red-btn">Logout</button>
        </form>
    </div>

    <?php if ($dbError): ?>
        <div class="error"><?= e($dbError) ?></div>
    <?php endif; ?>

    <div class="admin-grid">

        <div class="card">
            <h2>Add Plan</h2>

            <form method="post">
                <input type="hidden" name="action" value="admin_add_plan">

                <input class="input" name="name" placeholder="Plan name" required>
                <input class="input" type="number" name="amount" placeholder="Investment amount" required>
                <input class="input" type="number" name="price" placeholder="Price" required>
                <input class="input" name="rate" placeholder="Rate e.g. 1.20%" value="0.80%">

                <button class="full-btn">Add Plan</button>
            </form>
        </div>

        <div class="card">
            <h2>Quick Info</h2>

            <p style="font-size:11px;line-height:1.8">
                Website pages:<br>
                Login / Register<br>
                Home<br>
                Plans<br>
                Orders<br>
                Team<br>
                Me / Profile<br>
                Add Bank Card<br>
                Payment
            </p>
        </div>

    </div>

    <div class="card">
        <h2>Plans</h2>

        <table>
            <tr>
                <th>Name</th>
                <th>Amount</th>
                <th>Price</th>
                <th>Rate</th>
            </tr>

            <?php foreach ($adminPlans as $p): ?>
            <tr>
                <td><?= e($p['name']) ?></td>
                <td><?= money($p['amount']) ?></td>
                <td><?= money($p['price']) ?></td>
                <td><?= e($p['rate']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <h2>Payment Methods</h2>

        <?php foreach ($payments as $pm): ?>
            <form method="post" style="border-bottom:1px solid #eee;padding:12px 0">
                <input type="hidden" name="action" value="admin_payment">
                <input type="hidden" name="id" value="<?= (int)$pm['id'] ?>">

                <strong style="font-size:12px"><?= e($pm['method_name']) ?></strong>

                <input class="input" name="account_name"
                       value="<?= e($pm['account_name']) ?>"
                       placeholder="Account name">

                <input class="input" name="account_number"
                       value="<?= e($pm['account_number']) ?>"
                       placeholder="Account number">

                <textarea class="input" name="instructions"
                          placeholder="Payment instructions"><?= e($pm['instructions']) ?></textarea>

                <button class="full-btn">Save <?= e($pm['method_name']) ?></button>
            </form>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2>Recent Orders</h2>

        <table>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Plan</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>

            <?php foreach ($adminOrders as $o): ?>
            <tr>
                <td>#<?= (int)$o['id'] ?></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= e($o['plan_name'] ?? '-') ?></td>
                <td><?= money($o['amount']) ?></td>
                <td><?= e($o['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

</div>
<?php
    headerEnd();
    exit;
}

/* =========================
   LOGIN
   ========================= */
if ($page === 'login') {
    headerStart('Login');
?>
<div class="app">

    <div class="topbar">
        <div class="brand">
            <div class="logo">A</div>
            <div>
                <strong>MyApp</strong>
                <small>Welcome back</small>
            </div>
        </div>
    </div>

    <div class="form-card card" style="margin-top:35px">

        <h2>Welcome Back</h2>
        <p class="muted">Login to continue to your account.</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($dbError): ?>
            <div class="error"><?= e($dbError) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="login">

            <input class="input"
                   name="phone"
                   placeholder="Phone number"
                   required>

            <input class="input"
                   type="password"
                   name="password"
                   placeholder="Password"
                   required>

            <button class="full-btn">
                Login
            </button>
        </form>

        <div class="link-row">
            Don't have an account?
            <a href="?page=register"><b>Register</b></a>
        </div>

        <div class="link-row" style="margin-top:8px">
            <a href="?page=admin_login">Admin Login</a>
        </div>

    </div>

</div>
<?php
    headerEnd();
    exit;
}

/* =========================
   REGISTER
   ========================= */
if ($page === 'register') {
    headerStart('Register');
?>
<div class="app">

    <div class="topbar">
        <div class="brand">
            <div class="logo">A</div>
            <div>
                <strong>MyApp</strong>
                <small>Create account</small>
            </div>
        </div>
    </div>

    <div class="form-card card" style="margin-top:25px">

        <h2>Create Account</h2>
        <p class="muted">Register a new account.</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">

            <input type="hidden" name="action" value="register">

            <input class="input"
                   name="name"
                   placeholder="Full name"
                   required>

            <input class="input"
                   name="phone"
                   placeholder="Phone number"
                   required>

            <input class="input"
                   type="password"
                   name="password"
                   placeholder="Password (minimum 6 characters)"
                   required>

            <button class="full-btn">
                Create Account
            </button>

        </form>

        <div class="link-row">
            Already registered?
            <a href="?page=login"><b>Login</b></a>
        </div>

    </div>

</div>
<?php
    headerEnd();
    exit;
}

/* =========================
   HOME
   ========================= */
if ($page === 'home') {

    $u = $currentUser;

    $orderCount = 0;
    $teamCount = 0;

    if ($pdo) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$u['id']]);
        $orderCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE id <> ?
        ");
        $stmt->execute([$u['id']]);
        $teamCount = (int)$stmt->fetchColumn();
    }

    headerStart('Home');
?>
<div class="app">

    <div class="topbar">
        <div class="brand">
            <div class="logo">A</div>
            <div>
                <strong>MyApp</strong>
                <small>Investment Platform</small>
            </div>
        </div>

        <a class="top-action" href="?page=me">Profile</a>
    </div>

    <div class="content">

        <!-- ACCOUNT -->
        <div class="card account">

            <div class="account-head">

                <div class="avatar">
                    <?= e(strtoupper(substr($u['name'], 0, 1))) ?>
                </div>

                <div class="account-info">
                    <h3>Welcome, <?= e($u['name']) ?></h3>
                    <p><?= e($u['phone']) ?></p>
                </div>

                <a href="?page=payment" class="red-btn">
                    Deposit
                </a>

            </div>

            <div class="account-stats">

                <div>
                    <span>Balance</span>
                    <strong><?= money($u['balance']) ?></strong>
                </div>

                <div>
                    <span>Total Income</span>
                    <strong><?= money($u['total_income']) ?></strong>
                </div>

                <div>
                    <span>Team Income</span>
                    <strong><?= money($u['team_income']) ?></strong>
                </div>

            </div>

        </div>


        <!-- BANNER -->
        <div class="banner">

            <div style="position:relative;z-index:2">
                <small>WELCOME TO</small>
                <h1>MyApp</h1>
                <p>Choose your plan and manage your account.</p>

                <a href="#products" class="white-btn"
                   style="display:inline-block">
                    Explore Plans
                </a>
            </div>

            <div class="circle1"></div>
            <div class="circle2"></div>

        </div>


        <!-- STATS -->
        <div class="stats">

            <div class="stat">
                <strong>0</strong>
                <span>Today Income</span>
            </div>

            <div class="stat">
                <strong><?= $orderCount ?></strong>
                <span>Orders</span>
            </div>

            <div class="stat">
                <strong><?= $teamCount ?></strong>
                <span>Team</span>
            </div>

            <div class="stat">
                <strong><?= money($u['total_income']) ?></strong>
                <span>Total Income</span>
            </div>

        </div>


        <!-- PRODUCTS -->
        <div class="section-title" id="products">

            <div>
                <h2>Products</h2>
                <p>Choose your plan</p>
            </div>

            <a href="#products" class="view">View All</a>

        </div>


        <?php foreach ($plans as $plan): ?>

        <div class="product">

            <div class="product-img">
                <div class="sun"></div>
                <div class="mountain"></div>
            </div>

            <div class="product-body">

                <h3><?= e($plan['name']) ?></h3>

                <div class="rate">
                    <?= e($plan['rate']) ?>
                    <span>Daily income</span>
                </div>

                <div class="p-row">

                    <div>
                        <small>Investment</small>
                        <strong><?= money($plan['amount']) ?></strong>
                    </div>

                    <div>
                        <small>Price</small>
                        <strong><?= money($plan['price']) ?></strong>
                    </div>

                </div>

                <div class="p-bottom">

                    <span>
                        Validity: <b>30 Days</b>
                    </span>

                    <a class="blue-btn"
                       href="?page=payment&plan=<?= (int)$plan['id'] ?>">
                        Details
                    </a>

                </div>

            </div>

        </div>

        <?php endforeach; ?>

    </div>


    <!-- BOTTOM NAV -->
    <nav class="bottom-nav">

        <a class="nav active" href="?page=home">
            <div class="nav-icon">⌂</div>
            Home
        </a>

        <a class="nav" href="?page=orders">
            <div class="nav-icon">▣</div>
            Orders
        </a>

        <a class="nav" href="?page=team">
            <div class="nav-icon">♟</div>
            Team
        </a>

        <a class="nav" href="?page=me">
            <div class="nav-icon">♙</div>
            Me
        </a>

    </nav>

</div>
<?php
    headerEnd();
    exit;
}

/* =========================
   ORDERS
   ========================= */
if ($page === 'orders') {

    $orders = [];

    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT o.*, p.name AS plan_name
            FROM orders o
            LEFT JOIN plans p ON p.id = o.plan_id
            WHERE o.user_id = ?
            ORDER BY o.id DESC
        ");
        $stmt->execute([$currentUser['id']]);
        $orders = $stmt->fetchAll();
    }

    headerStart('Orders');
?>
<div class="app">

    <div class="topbar">
        <div class="brand">
            <div class="logo">A</div>
            <div>
                <strong>Orders</strong>
                <small>Your orders</small>
            </div>
        </div>

        <a class="top-action" href="?page=home">Home</a>
    </div>

    <div class="content">

        <?php if (isset($_GET['success'])): ?>
            <div class="success">Order submitted successfully.</div>
        <?php endif; ?>

        <?php if (!$orders): ?>

            <div class="card" style="text-align:center;padding:35px">
                <h3>No Orders</h3>
                <p class="muted" style="margin-top:6px">
                    Your orders will appear here.
                </p>

                <a href="?page=home#products" class="blue-btn">
                    View Plans
                </a>
            </div>

        <?php endif; ?>


        <?php foreach ($orders as $o): ?>

            <div class="card order">

                <div class="order-head">

                    <div>
                        <h3><?= e($o['plan_name'] ?? 'Plan') ?></h3>
                        <small>
                            <?= e($o['created_at']) ?>
                        </small>
                    </div>

                    <span class="status <?= e($o['status']) ?>">
                        <?= e(ucfirst($o['status'])) ?>
                    </span>

                </div>

                <div class="order-info">

                    <div>
                        <span>Amount</span>
                        <strong><?= money($o['amount']) ?></strong>
                    </div>

                    <div>
                        <span>Payment</span>
                        <strong><?= e($o['payment_method']) ?></strong>
                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <nav class="bottom-nav">

        <a class="nav" href="?page=home">
            <div class="nav-icon">⌂</div>Home
        </a>

        <a class="nav active" href="?page=orders">
            <div class="nav-icon">▣</div>Orders
        </a>

        <a class="nav" href="?page=team">
            <div class="nav-icon">♟</div>Team
        </a>

        <a class="nav" href="?page=me">
            <div class="nav-icon">♙</div>Me
        </a>

    </nav>

</div>
<?php
    headerEnd();
    exit;
}

/* =========================
   TEAM
   ========================= */
if ($page === 'team') {

    $members = [];

    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT id, name, phone, created_at
            FROM users
            WHERE id <> ?
            ORDER BY id DESC
            LIMIT 50
        ");
        $stmt->execute([$currentUser['id']]);
        $members = $stmt->fetchAll();
    }

    headerStart('Team');
?>
<div class="app">

    <div class="topbar">
        <div class="brand">
            <div class="logo">A</div>
            <div>
                <strong>Team</strong>
                <small>Team members</small>
            </div>
        </div>
    </div>

    <div class="content">

        <div class="card account">

            <div style="text-align:center">
                <div class="big-avatar">
                    ♟
                </div>

                <h3 style="margin-top:8px">
                    Your Referral Code
                </h3>

                <p style="font-size:13px;margin-top:5px">
                    <?= e($currentUser['referral_code']) ?>
                </p>

                <p style="font-size:9px;opacity:.8;margin-top:5px">
                    Share your code with your team.
                </p>
            </div>

        </div>

        <div class="section-title">
            <div>
                <h2>Team Members</h2>
                <p><?= count($members) ?> members</p>
            </div>
        </div>

        <?php foreach ($members as $m): ?>

            <div class="card" style="margin-bottom:8px">

                <div style="display:flex;align-items:center">

                    <div class="avatar"
                         style="background:#0878b9;color:#fff">
                        <?= e(strtoupper(substr($m['name'],0,1))) ?>
                    </div>

                    <div>
                        <strong style="font-size:12px">
                            <?= e($m['name']) ?>
                        </strong>

                        <div style="font-size:8px;color:#9aa3ad;margin-top:3px">
                            <?= e($m['phone']) ?>
                        </div>
                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <nav class="bottom-nav">

        <a class="nav" href="?page=home">
            <div class="nav-icon">⌂</div>Home
        </a>

        <a class="nav" href="?page=orders">
            <div class="nav-icon">▣</div>Orders
        </a>

        <a class="nav active" href="?page=team">
            <div class="nav-icon">♟</div>Team
        </a>

        <a class="nav" href="?page=me">
            <div class="nav-icon">♙</div>Me
        </a>

    </nav>

</div>
<?php
    headerEnd();
    exit;
}

/* =========================
   PAYMENT
   ========================= */
if ($page === 'payment') {

    $selectedPlan = null;
    $payments = [];

    if ($pdo) {

        $planId = (int)($_GET['plan'] ?? 0);

        if ($planId > 0) {
            $stmt = $pdo->prepare("
                SELECT * FROM plans
                WHERE id = ? AND active = 1
                LIMIT 1
            ");
            $stmt->execute([$planId]);
            $selectedPlan = $stmt->fetch();
        }

        $payments = $pdo->query("
            SELECT * FROM payment_methods
            WHERE active = 1
            ORDER BY id ASC
        ")->fetchAll();
    }

    headerStart('Payment');
?>
<div class="app">

    <div class="topbar">
        <div class="brand">
            <div class="logo">A</div>
            <div>
                <strong>Payment</strong>
                <small>Submit your payment</small>
            </div>
        </div>

        <a class="top-action" href="?page=home">Home</a>
    </div>

    <div class="content">

        <?php if ($selectedPlan): ?>

            <div class="card account">

                <small style="opacity:.75">
                    Selected Plan
                </small>

                <h2 style="margin-top:5px">
                    <?= e($selectedPlan['name']) ?>
                </h2>

                <div style="font-size:11px;margin-top:7px">
                    Investment:
                    <b><?= money($selectedPlan['amount']) ?></b>
                </div>

                <div style="font-size:11px;margin-top:4px">
                    Price:
                    <b><?= money($selectedPlan['price']) ?></b>
                </div>

            </div>

        <?php endif; ?>


        <div class="section-title">
            <div>
                <h2>Payment Methods</h2>
                <p>Choose a payment method</p>
            </div>
        </div>


        <?php foreach ($payments as $pm): ?>

            <div class="card" style="margin-bottom:9px">

                <h3 style="font-size:13px">
                    <?= e($pm['method_name']) ?>
                </h3>

                <?php if ($pm['account_name']): ?>
                    <p style="font-size:10px;margin-top:7px">
                        Account Name:
                        <b><?= e($pm['account_name']) ?></b>
                    </p>
                <?php endif; ?>

                <?php if ($pm['account_number']): ?>
                    <p style="font-size:10px;margin-top:5px">
                        Account Number:
                        <b><?= e($pm['account_number']) ?></b>
                    </p>
                <?php endif; ?>

                <?php if ($pm['instructions']): ?>
                    <p style="font-size:9px;color:#8e989f;margin-top:7px;line-height:1.5">
                        <?= nl2br(e($pm['instructions'])) ?>
                    </p>
                <?php endif; ?>

            </div>

        <?php endforeach; ?>


        <?php if ($selectedPlan): ?>

            <div class="card" style="margin-top:12px">

                <h3 style="font-size:14px;margin-bottom:12px">
                    Submit Order
                </h3>

                <form method="post">

                    <input type="hidden"
                           name="action"
                           value="place_order">

                    <input type="hidden"
                           name="plan_id"
                           value="<?= (int)$selectedPlan['id'] ?>">

                    <select class="input"
                            name="payment_method"
                            required>

                        <option value="">
                            Select payment method
                        </option>

                        <?php foreach ($payments as $pm): ?>

                            <option value="<?= e($pm['method_key']) ?>">
                                <?= e($pm['method_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <input class="input"
                           name="transaction_id"
                           placeholder="Transaction ID">

                    <button class="full-btn">
                        Submit Order
                    </button>

                </form>

            </div>

        <?php endif; ?>

    </div>


    <nav class="bottom-nav">

        <a class="nav" href="?page=home">
            <div class="nav-icon">⌂</div>Home
        </a>

        <a class="nav" href="?page=orders">
            <div class="nav-icon">▣</div>Orders
        </a>

        <a class="nav" href="?page=team">
            <div class="nav-icon">♟</div>Team
        </a>

        <a class="nav active" href="?page=me">
            <div class="nav-icon">♙</div>Me
        </a>

    </nav>

</div>
<?php
    headerEnd();
    exit;
}

/* =========================
   ME / PROFILE
   ========================= */
if ($page === 'me') {

    $cards = [];

    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT * FROM bank_cards
            WHERE user_id = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$currentUser['id']]);
        $cards = $stmt->fetchAll();
    }

    headerStart('Me');
?>
<div class="app">

    <div class="topbar">
        <div class="brand">
            <div class="logo">A</div>
            <div>
                <strong>My Profile</strong>
                <small>Account settings</small>
            </div>
        </div>
    </div>

    <div class="content">

        <?php if (isset($_GET['saved'])): ?>
            <div class="success">Bank card saved successfully.</div>
        <?php endif; ?>

        <div class="profile-head">

            <div class="big-avatar">
                <?= e(strtoupper(substr($currentUser['name'],0,1))) ?>
            </div>

            <h2><?= e($currentUser['name']) ?></h2>
            <p><?= e($currentUser['phone']) ?></p>

        </div>


        <div class="menu">

            <a href="?page=home">
                <span>⌂</span>
                Home
                <span>›</span>
            </a>

            <a href="?page=orders">
                <span>▣</span>
                My Orders
                <span>›</span>
            </a>

            <a href="?page=team">
                <span>♟</span>
                My Team
                <span>›</span>
            </a>

            <a href="?page=payment">
                <span>₹</span>
                Payment
                <span>›</span>
            </a>

            <a href="#bank">
                <span>▤</span>
                Bank Card
                <span>›</span>
            </a>

        </div>


        <div class="card" id="bank" style="margin-top:10px">

            <h3 style="font-size:14px;margin-bottom:10px">
                Add New Card
            </h3>

            <form method="post">

                <input type="hidden"
                       name="action"
                       value="save_card">

                <input class="input"
                       name="bank_name"
                       placeholder="Bank / Wallet name"
                       required>

                <input class="input"
                       name="account_name"
                       placeholder="Account name"
                       required>

                <input class="input"
                       name="account_number"
                       placeholder="Account number"
                       required>

                <button class="full-btn">
                    Save Card
                </button>

            </form>

        </div>


        <?php if ($cards): ?>

            <div class="section-title">
                <div>
                    <h2>Saved Cards</h2>
                    <p>Your bank details</p>
                </div>
            </div>

            <?php foreach ($cards as $card): ?>

                <div class="card" style="margin-bottom:8px">

                    <strong style="font-size:12px">
                        <?= e($card['bank_name']) ?>
                    </strong>

                    <p style="font-size:9px;color:#929da7;margin-top:5px">
                        <?= e($card['account_name']) ?>
                    </p>

                    <p style="font-size:10px;margin-top:5px">
                        <?= e($card['account_number']) ?>
                    </p>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>


        <form method="post" style="margin-top:10px">

            <input type="hidden"
                   name="action"
                   value="logout">

            <button class="full-btn"
                    style="background:#e83251">
                Logout
            </button>

        </form>

    </div>


    <nav class="bottom-nav">

        <a class="nav" href="?page=home">
            <div class="nav-icon">⌂</div>Home
        </a>

        <a class="nav" href="?page=orders">
            <div class="nav-icon">▣</div>Orders
        </a>

        <a class="nav" href="?page=team">
            <div class="nav-icon">♟</div>Team
        </a>

        <a class="nav active" href="?page=me">
            <div class="nav-icon">♙</div>Me
        </a>

    </nav>

</div>
<?php
    headerEnd();
    exit;
}

/* FALLBACK */
redirect(loggedIn() ? '?page=home' : '?page=login');
?>
