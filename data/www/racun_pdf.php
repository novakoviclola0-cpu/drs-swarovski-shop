<?php
session_start();
require __DIR__ . '/db.php';

// Proveri da li postoji račun u session
if (!isset($_SESSION['checkout_items']) || empty($_SESSION['checkout_items'])) {
    header("Location: kosarica.php");
    exit;
}

$items = $_SESSION['checkout_items'];
$total = $_SESSION['checkout_total'];
$discountAmount = $_SESSION['checkout_discount'] ?? 0;
$grandTotal = $_SESSION['checkout_grand_total'];
$discountPercent = $_SESSION['checkout_discount_percent'] ?? 0;

// Generiši jedinstveni ID računa
$racunId = 'RAC-' . date('Ymd') . '-' . strtoupper(substr(md5(time() . $_SESSION['user_id']), 0, 8));
$datum = date('d.m.Y H:i');

// Podaci za QR kod
$qrData = "RACUN:" . $racunId . "|DATUM:" . $datum . "|IZNOS:" . number_format($grandTotal, 2) . "EUR|KUPAC:" . $_SESSION['ime'] . " " . $_SESSION['priimek'];

// Generiši HTML za PDF (koristićemo browser print to PDF)
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Račun PDF - Swarovski</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }
        .racun-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .racun-info {
            margin-bottom: 20px;
        }
        .racun-items table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .racun-items th,
        .racun-items td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .racun-items th {
            background: #f5f5f5;
        }
        .racun-total {
            text-align: right;
            margin-top: 20px;
        }
        .racun-qr {
            text-align: center;
            margin-top: 30px;
        }
        #pdf-content {
            background: white;
            padding: 20px;
        }
    </style>
</head>
<body>

<div id="pdf-content">
    <div class="racun-header">
        <h1>Swarovski</h1>
        <h2>Račun</h2>
    </div>

    <div class="racun-info">
        <p><strong>Broj računa:</strong> <?= htmlspecialchars($racunId) ?></p>
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
                    <th>Ukupno</th>
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
        <p style="font-size: 18px; margin-top: 10px;"><strong>Za plačilo:</strong> <?= number_format($grandTotal, 2) ?> €</p>
    </div>

    <div class="racun-qr">
        <h3>QR kod računa</h3>
        <div id="qrcode"></div>
    </div>
</div>

<script>
const qrData = <?= json_encode($qrData) ?>;
const { jsPDF } = window.jspdf;

// Generiši QR kod
new QRCode(document.getElementById("qrcode"), {
    text: qrData,
    width: 150,
    height: 150
});

// Sačekaj da se QR kod generiše, pa generiši i preuzmi PDF
setTimeout(function() {
    const element = document.getElementById('pdf-content');
    const filename = 'racun_<?= htmlspecialchars($racunId) ?>.pdf';
    
    html2canvas(element, {
        scale: 2,
        useCORS: true,
        logging: false
    }).then(function(canvas) {
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'mm', 'a4');
        const imgWidth = 210;
        const pageHeight = 295;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;

        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }

        // Automatski preuzmi PDF
        pdf.save(filename);
        
        // Preusmeri na početnu stranicu nakon preuzimanja
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 500);
    });
}, 1500);
</script>

</body>
</html>

