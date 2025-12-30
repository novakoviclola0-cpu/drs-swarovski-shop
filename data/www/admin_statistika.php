<?php
session_start();
require __DIR__ . '/db.php';

// inicializacija košarice za prikaz števila v meniju
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cartCount = array_sum($_SESSION['cart']);

// preverimo, ali je uporabnik admin
$isAdmin = false;
$user = null;
if (isset($_SESSION['user_id'])) {
    $uStmt = $pdo->prepare("SELECT e_mail FROM oseba WHERE id = :id");
    $uStmt->execute([':id' => $_SESSION['user_id']]);
    $user = $uStmt->fetch();
    if ($user && $user['e_mail'] === 'admin@gmail.com') {
        $isAdmin = true;
    }
}

if (!$isAdmin) {
    die("Dostop zavrnjen. Ta stran je namenjena samo administratorju.");
}

// Pridobimo statistiko ocen
$statsStmt = $pdo->query("
    SELECT 
        i.id,
        i.ime,
        i.povprecna_ocena,
        COUNT(k.id) as stevilo_ocen,
        AVG(k.ocena) as avg_ocena
    FROM izdelek i
    LEFT JOIN komentar k ON i.id = k.izdelek_id
    GROUP BY i.id, i.ime, i.povprecna_ocena
    HAVING COUNT(k.id) > 0
    ORDER BY avg_ocena DESC, stevilo_ocen DESC
    LIMIT 10
");
$topProducts = $statsStmt->fetchAll();

// Pridobimo distribucijo ocen po izdelkih
$distributionStmt = $pdo->query("
    SELECT 
        ocena,
        COUNT(*) as stevilo
    FROM komentar
    GROUP BY ocena
    ORDER BY ocena DESC
");
$ratingDistribution = $distributionStmt->fetchAll();

// Pripravimo podatke za graf
$productNames = [];
$productRatings = [];
$productCounts = [];

foreach ($topProducts as $product) {
    $productNames[] = htmlspecialchars($product['ime']);
    $productRatings[] = round((float)$product['avg_ocena'], 2);
    $productCounts[] = (int)$product['stevilo_ocen'];
}

$ratingLabels = [];
$ratingCounts = [];
foreach ($ratingDistribution as $dist) {
    $ratingLabels[] = $dist['ocena'] . ' zvezdic';
    $ratingCounts[] = (int)$dist['stevilo'];
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Statistika ocen - Admin</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-page {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 24px 50px;
        }
        .admin-page h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .admin-note {
            font-size: 14px;
            color: #555;
            margin-bottom: 30px;
        }
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .chart-box {
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e5e5e5;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .chart-box h3 {
            font-size: 18px;
            margin-bottom: 15px;
            font-family: "Playfair Display", serif;
        }
        .chart-full {
            grid-column: 1 / -1;
        }
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        .stats-table th,
        .stats-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e5e5;
            text-align: left;
        }
        .stats-table th {
            background: #f9f9f9;
            font-weight: 600;
        }
        .stats-table tr:hover {
            background: #f5f5f5;
        }
        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
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
                <a href="admin_statistika.php">Statistika</a>
                <a href="admin_dodaj_izdelek.php">Dodaj izdelek</a>
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

<main class="admin-page">
    <h2>Statistika najbolje ocenjenih izdelkov</h2>
    <p class="admin-note">
        Pregled najbolje ocenjenih izdelkov z interaktivnimi grafi.
    </p>

    <?php if (empty($topProducts)): ?>
        <p>Ni podatkov o ocenah.</p>
    <?php else: ?>
        <div class="charts-container">
            <div class="chart-box chart-full">
                <h3>Top 10 najbolje ocenjenih izdelkov</h3>
                <canvas id="ratingsChart"></canvas>
            </div>

            <div class="chart-box">
                <h3>Povprečne ocene</h3>
                <canvas id="avgRatingsChart"></canvas>
            </div>

            <div class="chart-box">
                <h3>Distribucija ocen</h3>
                <canvas id="distributionChart"></canvas>
            </div>
        </div>

        <table class="stats-table">
            <thead>
                <tr>
                    <th>Izdelek</th>
                    <th>Povprečna ocena</th>
                    <th>Število ocen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topProducts as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars($product['ime']) ?></td>
                        <td><?= number_format((float)$product['avg_ocena'], 2) ?> ⭐</td>
                        <td><?= (int)$product['stevilo_ocen'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<footer>
    <div class="footer-bg"></div>
</footer>

<script>
// Graf za top izdelke (bar chart)
const ratingsCtx = document.getElementById('ratingsChart');
if (ratingsCtx) {
    new Chart(ratingsCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($productNames) ?>,
            datasets: [{
                label: 'Povprečna ocena',
                data: <?= json_encode($productRatings) ?>,
                backgroundColor: 'rgba(34, 34, 34, 0.8)',
                borderColor: 'rgba(34, 34, 34, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: {
                        stepSize: 0.5
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Graf za povprečne ocene (line chart)
const avgRatingsCtx = document.getElementById('avgRatingsChart');
if (avgRatingsCtx) {
    new Chart(avgRatingsCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($productNames) ?>,
            datasets: [{
                label: 'Povprečna ocena',
                data: <?= json_encode($productRatings) ?>,
                borderColor: 'rgba(34, 34, 34, 1)',
                backgroundColor: 'rgba(34, 34, 34, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5
                }
            }
        }
    });
}

// Graf za distribucijo ocen (pie chart)
const distributionCtx = document.getElementById('distributionChart');
if (distributionCtx) {
    new Chart(distributionCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($ratingLabels) ?>,
            datasets: [{
                data: <?= json_encode($ratingCounts) ?>,
                backgroundColor: [
                    'rgba(34, 34, 34, 0.8)',
                    'rgba(100, 100, 100, 0.8)',
                    'rgba(150, 150, 150, 0.8)',
                    'rgba(200, 200, 200, 0.8)',
                    'rgba(226, 176, 7, 0.8)'
                ],
                borderColor: [
                    'rgba(34, 34, 34, 1)',
                    'rgba(100, 100, 100, 1)',
                    'rgba(150, 150, 150, 1)',
                    'rgba(200, 200, 200, 1)',
                    'rgba(226, 176, 7, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });
}
</script>

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

