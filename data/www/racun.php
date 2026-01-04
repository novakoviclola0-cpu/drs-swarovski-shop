<?php
session_start();
require __DIR__ . '/db.php';

// Preveri, ali obstaja račun v session
if (!isset($_SESSION['checkout_items']) || empty($_SESSION['checkout_items'])) {
    header("Location: kosarica.php");
    exit;
}

$items = $_SESSION['checkout_items'];
$total = $_SESSION['checkout_total'];
$discountAmount = $_SESSION['checkout_discount'] ?? 0;
$grandTotal = $_SESSION['checkout_grand_total'];
$discountPercent = $_SESSION['checkout_discount_percent'] ?? 0;

// Generiraj edinstveni ID računa
$racunId = 'RAC-' . date('Ymd') . '-' . strtoupper(substr(md5(time() . $_SESSION['user_id']), 0, 8));
$datum = date('d.m.Y H:i');

// Podatki za QR kod (kot niz)
$qrData = "RACUN:" . $racunId . "|DATUM:" . $datum . "|IZNOS:" . number_format($grandTotal, 2) . "EUR|KUPAC:" . $_SESSION['ime'] . " " . $_SESSION['priimek'];

$pdfRequested = isset($_GET['pdf']) && $_GET['pdf'] == '1';

// Če je zahtevan PDF, preusmeri na generiranje PDF
if ($pdfRequested) {
    header("Location: racun_pdf.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Račun - Swarovski</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        .racun-page {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 24px 50px;
        }
        .racun-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .racun-box {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #e5e5e5;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .racun-info {
            margin-bottom: 20px;
        }
        .racun-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        .racun-items {
            margin: 20px 0;
        }
        .racun-items table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .racun-items th,
        .racun-items td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .racun-items th {
            background: #f9f9f9;
            font-weight: 600;
        }
        .racun-total {
            margin-top: 20px;
            text-align: right;
            font-size: 16px;
        }
        .racun-total p {
            margin: 5px 0;
        }
        .racun-qr {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .btn-print {
            margin-top: 20px;
            padding: 10px 20px;
            background: #111;
            color: #fff;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .btn-print:hover {
            background: #333;
        }
        body.dark-theme .racun-box {
            background: #2a2a2a;
            border-color: #3a3a3a;
            color: #e0e0e0;
        }
        body.dark-theme .racun-items th {
            background: #1a1a1a;
        }
        body.dark-theme .racun-items td {
            border-bottom-color: #3a3a3a;
        }
        body.dark-theme .racun-qr {
            background: #1a1a1a;
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
                Košarica<?= isset($_SESSION['cart']) && array_sum($_SESSION['cart']) > 0 ? " (" . array_sum($_SESSION['cart']) . ")" : "" ?>
            </a>

            <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@gmail.com'): ?>
                <a href="admin_slike.php">Urejanje slik</a>
                <a href="admin_statistika.php">Statistika</a>
                <a href="admin_dodaj_izdelek.php">Dodaj izdelek</a>
                <a href="admin_uporabniki.php">Uporabniki</a>
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

<main class="racun-page">
    <div class="racun-box">
        <div class="racun-header">
            <h1>Swarovski</h1>
            <h2>Račun</h2>
        </div>

        <div class="racun-info">
            <p><strong>Številka računa:</strong> <?= htmlspecialchars($racunId) ?></p>
            <p><strong>Datum:</strong> <?= htmlspecialchars($datum) ?></p>
            <p><strong>Kupac:</strong> <?= htmlspecialchars($_SESSION['ime'] . ' ' . $_SESSION['priimek']) ?></p>
        </div>

        <div class="racun-items">
            <table>
                <thead>
                    <tr>
                        <th>Izdelek</th>
                        <th>Količina</th>
                        <th>Cena</th>
                        <th>Skupaj</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['ime']) ?></td>
                            <td><?= (int)$item['qty'] ?></td>
                            <td><?= number_format($item['cena'], 2) ?> €</td>
                            <td><?= number_format($item['line'], 2) ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="racun-total">
            <p><strong>Vmesni znesek:</strong> <?= number_format($total, 2) ?> €</p>
            <?php if ($discountAmount > 0): ?>
                <p><strong>Popust (<?= number_format($discountPercent * 100, 0) ?>%):</strong> -<?= number_format($discountAmount, 2) ?> €</p>
            <?php endif; ?>
            <p style="font-size: 20px; margin-top: 10px;"><strong>Za plačilo:</strong> <?= number_format($grandTotal, 2) ?> €</p>
        </div>

        <div class="racun-qr">
            <h3>QR kod računa</h3>
            <div id="qrcode"></div>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="racun.php?pdf=1" class="btn-print" style="display: inline-block; text-decoration: none;">Prenesi PDF</a>
            <button onclick="window.print()" class="btn-print">Natisni</button>
            <a href="index.php" class="btn-print" style="display: inline-block; text-decoration: none; margin-left: 10px;">Nazaj na domov</a>
        </div>
    </div>
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

// Generiraj QR kod
const qrData = <?= json_encode($qrData) ?>;
new QRCode(document.getElementById("qrcode"), {
    text: qrData,
    width: 200,
    height: 200
});
</script>

</body>
</html>

