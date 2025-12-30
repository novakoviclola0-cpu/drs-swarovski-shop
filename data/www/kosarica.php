<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/cart_functions.php';

// inicializiramo košarico v seji
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];  // [izdelek_id => količina]
}

// Ako je korisnik prijavljen, učitaj košaricu iz baze
if (isset($_SESSION['user_id'])) {
    $dbCart = loadCartFromDB($pdo, $_SESSION['user_id']);
    // Spoji session i bazu (prioritet session)
    foreach ($_SESSION['cart'] as $cartId => $qty) {
        $dbCart[$cartId] = $qty;
    }
    $_SESSION['cart'] = $dbCart;
}

// popust v seji (0 ali 0.20)
if (!isset($_SESSION['discount'])) {
    $_SESSION['discount'] = 0;
}

$discountMessage = "";
$discountError   = "";

// števec artiklov za header
$cartCount = array_sum($_SESSION['cart']);

// priprava podatkov za prikaz košarice (potrebno pre checkout)
$items = [];
$total = 0.0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM izdelek WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $r) {
        $map[$r['id']] = $r;
    }

    foreach ($_SESSION['cart'] as $pid => $qty) {
        if (!isset($map[$pid])) continue;
        $product = $map[$pid];
        $lineTotal = $product['cena'] * $qty;
        $total += $lineTotal;

        $items[] = [
            'id'    => $pid,
            'ime'   => $product['ime'],
            'cena'  => $product['cena'],
            'qty'   => $qty,
            'line'  => $lineTotal
        ];
    }
}

// izračun popusta
$discountPercent = $_SESSION['discount'] ?? 0;
$discountAmount  = 0;
$grandTotal      = $total;

if ($discountPercent > 0 && $total > 0) {
    $discountAmount = $total * $discountPercent;
    $grandTotal     = $total - $discountAmount;
}

// obdelava akcij (dodaj, izbriši, kupi, koda)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($action === 'add' && $id > 0) {
        if (!isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id] = 0;
        }
        $_SESSION['cart'][$id]++;
        // Sačuvaj u bazu ako je korisnik prijavljen
        if (isset($_SESSION['user_id'])) {
            saveCartToDB($pdo, $_SESSION['user_id'], $_SESSION['cart']);
        }
        header("Location: kosarica.php");
        exit;
    }

    if ($action === 'remove' && $id > 0) {
        unset($_SESSION['cart'][$id]);
        // Sačuvaj u bazu ako je korisnik prijavljen
        if (isset($_SESSION['user_id'])) {
            saveCartToDB($pdo, $_SESSION['user_id'], $_SESSION['cart']);
        }
        header("Location: kosarica.php");
        exit;
    }

    if ($action === 'checkout') {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
        
        // Kreiraj račun
        $pdfRequested = isset($_POST['pdf_racun']) && $_POST['pdf_racun'] == '1';
        
        // Sačuvaj podatke za račun u session
        $_SESSION['checkout_items'] = $items;
        $_SESSION['checkout_total'] = $total;
        $_SESSION['checkout_discount'] = $discountAmount;
        $_SESSION['checkout_grand_total'] = $grandTotal;
        $_SESSION['checkout_discount_percent'] = $discountPercent;
        
        // Obriši košaricu
        $_SESSION['cart'] = [];
        $_SESSION['discount'] = 0;
        saveCartToDB($pdo, $_SESSION['user_id'], []);
        
        // Preusmeri na stranicu za račun ili direktno na PDF
        if ($pdfRequested) {
            header("Location: racun_pdf.php");
        } else {
            header("Location: racun.php");
        }
        exit;
    }

    if ($action === 'apply_code') {
        $code = strtoupper(trim($_POST['discount_code'] ?? ''));

        if ($code === 'AKCIJA') {
            $_SESSION['discount'] = 0.20;
            $discountMessage = "Koda za popust je bila uspešno uporabljena. Popust 20% je dodan.";
        } else {
            $_SESSION['discount'] = 0;
            $discountError = "Neveljavna koda za popust.";
        }
    }
}

// priprava podatkov za prikaz košarice
$items = [];
$total = 0.0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("SELECT * FROM izdelek WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $r) {
        $map[$r['id']] = $r;
    }

    foreach ($_SESSION['cart'] as $pid => $qty) {
        if (!isset($map[$pid])) continue;
        $product = $map[$pid];
        $lineTotal = $product['cena'] * $qty;
        $total += $lineTotal;

        $items[] = [
            'id'    => $pid,
            'ime'   => $product['ime'],
            'cena'  => $product['cena'],
            'qty'   => $qty,
            'line'  => $lineTotal
        ];
    }
}

// izračun popusta
$discountPercent = $_SESSION['discount'] ?? 0;
$discountAmount  = 0;
$grandTotal      = $total;

if ($discountPercent > 0 && $total > 0) {
    $discountAmount = $total * $discountPercent;
    $grandTotal     = $total - $discountAmount;
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Košarica - Swarovski</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-page {
            max-width: 1100px;
            margin: 40px auto 60px;
            padding: 0 24px;
        }

        .cart-title {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .cart-empty {
            font-size: 14px;
            color: #555;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .cart-table th,
        .cart-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .cart-table th {
            font-weight: 600;
        }

        .cart-price,
        .cart-line-total {
            white-space: nowrap;
        }

        .cart-remove-form {
            display: inline-block;
        }

        .cart-remove-btn {
            border: 1px solid #b3261e;
            background: transparent;
            color: #b3261e;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            cursor: pointer;
        }

        .cart-remove-btn:hover {
            background: #b3261e;
            color: #fff;
        }

        .cart-summary {
            text-align: right;
            margin-top: 10px;
            font-size: 15px;
        }

        .cart-total {
            font-weight: 600;
        }

        .cart-checkout-form {
            text-align: right;
            margin-top: 15px;
        }

        .cart-checkout-btn {
            padding: 10px 22px;
            border-radius: 999px;
            border: 1px solid #111;
            background: #111;
            color: #fff;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.12em;
            cursor: pointer;
        }

        .cart-checkout-btn:hover {
            background: #fff;
            color: #111;
        }

        .cart-success {
            max-width: 400px;
            margin: 0 auto 15px;
            padding: 10px 14px;
            border-radius: 6px;
            background: #e6f4ea;
            border: 1px solid #a3d3b2;
            font-size: 14px;
            color: #265e33;
        }

        .discount-box {
            max-width: 400px;
            margin-left: auto;
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
            background: #fafafa;
            font-size: 14px;
        }

        .discount-box label {
            display: block;
            margin-bottom: 6px;
        }

        .discount-box-input-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .discount-box input[type="text"] {
            flex: 1;
            padding: 6px 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .discount-btn {
            padding: 7px 14px;
            border-radius: 999px;
            border: 1px solid #111;
            background: #111;
            color: #fff;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
        }

        .discount-btn:hover {
            background: #fff;
            color: #111;
        }

        .discount-msg {
            margin-top: 6px;
            font-size: 13px;
            color: #265e33;
        }

        .discount-error {
            margin-top: 6px;
            font-size: 13px;
            color: #b3261e;
        }

        .cart-summary-line {
            margin: 2px 0;
        }

        .cart-summary-line.discount-line {
            color: #b3261e;
        }

        .cart-summary-line.total-line {
            font-weight: 700;
            margin-top: 6px;
        }
    </style>
</head>
<body>

<header class="main-header">
    <div class="pink-bg"></div>
    <div class="header-content">
        <h1 class="logo-title">Swarovski</h1>

        <nav class="right-menu">
            <a href="index.php">Domov</a>
            <a href="kosarica.php">
                Košarica<?= $cartCount ? " ($cartCount)" : "" ?>
            </a>

            <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@gmail.com'): ?>
                <a href="admin_slike.php">Urejanje slik</a>
            <?php endif; ?>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php">Prijava</a>
            <?php else: ?>
                <a href="logout.php" class="logout-btn">
                    Odjava (<?= htmlspecialchars($_SESSION['ime']) ?>)
                </a>
            <?php endif; ?>

            <a href="about.php">O nas</a>
        </nav>
    </div>
</header>

<main class="cart-page">
    <h2 class="cart-title">Košarica</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="cart-success">
            Hvala za nakup! Vaša košarica je bila izpraznjena.
        </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <p class="cart-empty">Vaša košarica je trenutno prazna.</p>
    <?php else: ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Izdelek</th>
                    <th>Količina</th>
                    <th>Cena</th>
                    <th>Skupaj</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= htmlspecialchars($it['ime']) ?></td>
                        <td><?= (int)$it['qty'] ?></td>
                        <td class="cart-price"><?= number_format($it['cena'], 2) ?> €</td>
                        <td class="cart-line-total"><?= number_format($it['line'], 2) ?> €</td>
                        <td>
                            <form method="POST" class="cart-remove-form">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                <button type="submit" class="cart-remove-btn">Odstrani</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- KODA ZA POPUST -->
        <div class="discount-box">
            <form method="POST">
                <input type="hidden" name="action" value="apply_code">
                <label for="discount_code">Imate kodo za popust?</label>
                <div class="discount-box-input-row">
                    <input type="text" id="discount_code" name="discount_code"
                           placeholder="Vnesite kodo (npr. AKCIJA)">
                    <button type="submit" class="discount-btn">Uporabi kodo</button>
                </div>
            </form>

            <?php if ($discountMessage): ?>
                <div class="discount-msg"><?= htmlspecialchars($discountMessage) ?></div>
            <?php endif; ?>

            <?php if ($discountError): ?>
                <div class="discount-error"><?= htmlspecialchars($discountError) ?></div>
            <?php endif; ?>
        </div>

        <!-- POVZETEK -->
        <div class="cart-summary">
            <p class="cart-summary-line">
                Vmesni znesek: <?= number_format($total, 2) ?> €
            </p>

            <?php if ($discountPercent > 0 && $discountAmount > 0): ?>
                <p class="cart-summary-line discount-line">
                    Popust (AKCIJA - 20%): −<?= number_format($discountAmount, 2) ?> €
                </p>
                <p class="cart-summary-line total-line">
                    Za plačilo: <?= number_format($grandTotal, 2) ?> €
                </p>
            <?php else: ?>
                <p class="cart-summary-line total-line">
                    Za plačilo: <?= number_format($grandTotal, 2) ?> €
                </p>
            <?php endif; ?>
        </div>

        <div class="cart-checkout-form">
            <form method="POST" id="checkoutForm">
                <input type="hidden" name="action" value="checkout">
                <div style="margin-bottom: 12px; text-align: right;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                        <input type="checkbox" name="pdf_racun" value="1" style="cursor: pointer;">
                        <span>Želite račun u PDF-u?</span>
                    </label>
                </div>
                <button type="submit" class="cart-checkout-btn">Kupi</button>
            </form>
        </div>
    <?php endif; ?>
</main>

<footer>
    <div class="footer-bg"></div>
</footer>

<script>
// Učitaj temu iz localStorage
(function() {
    const theme = localStorage.getItem('theme') || 'light';
    if (theme === 'dark') {
        document.body.classList.add('dark-theme');
    }
})();
</script>

</body>
</html>
