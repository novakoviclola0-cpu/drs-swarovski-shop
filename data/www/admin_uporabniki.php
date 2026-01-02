<?php
session_start();
require __DIR__ . '/db.php';

// Inicijalizacija košarice (za prikaz broja u headeru)
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cartCount = array_sum($_SESSION['cart']);

// Provera da li je korisnik admin
$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT e_mail FROM oseba WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['e_mail'] === 'admin@gmail.com') {
        $isAdmin = true;
    }
}

if (!$isAdmin) {
    die("Dostop zavrnjen. Ta stran je namenjena samo administratorju.");
}

// Brisanje korisnika
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $userId = (int)$_POST['user_id'];

    // Ne dozvoljavamo brisanje samog sebe
    if ($userId > 0 && $userId !== $_SESSION['user_id']) {
        $del = $pdo->prepare("DELETE FROM oseba WHERE id = :id");
        $del->execute([':id' => $userId]);
    }

    // Osveži stranicu
    header("Location: admin_uporabniki.php");
    exit;
}

// Izvoz u CSV (Excel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
    $filename = "uporabniki_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM da Excel pravilno prikaže slovenske karaktere
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Zaglavlje
    fputcsv($output, ['ID', 'Ime', 'Priimek', 'E-pošta', 'Tip ID']);

    // Podaci
    $allUsers = $pdo->query("SELECT id, ime, priimek, e_mail, tip_id FROM oseba ORDER BY id ASC");
    while ($row = $allUsers->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['ime'],
            $row['priimek'],
            $row['e_mail'],
            $row['tip_id']
        ]);
    }

    fclose($output);
    exit;
}

// Učitavanje svih korisnika za prikaz
$stmt = $pdo->query("SELECT id, ime, priimek, e_mail, tip_id FROM oseba ORDER BY id ASC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Upravljanje uporabniki - Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-page {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 24px 60px;
        }
        .admin-page h2 {
            font-size: 26px;
            margin-bottom: 10px;
        }
        .admin-note {
            font-size: 14px;
            color: #555;
            margin-bottom: 30px;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        .admin-table th,
        .admin-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }
        .admin-table th {
            background: #f5f5f5;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .admin-table tr:hover {
            background: #f9f9f9;
        }
        .delete-btn {
            background: #d32f2f;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        .delete-btn:hover {
            background: #b71c1c;
        }
        .export-section {
            text-align: right;
            margin-top: 30px;
        }
        .export-btn {
            background: #111111;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
        }
        .export-btn:hover {
            background: #333;
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
            <a href="kosarica.php">Košarica<?= $cartCount ? " ($cartCount)" : "" ?></a>

            <?php if ($isAdmin): ?>
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

<main class="admin-page">
    <h2>Upravljanje uporabniki</h2>
    <p class="admin-note">
        Pregled svih registrovanih korisnika. Možete izbrisati korisnike (osim samog sebe).<br>
        <strong>Tip ID:</strong> 1 = Administrator, 2 = Običan korisnik
    </p>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Ime</th>
                <th>Priimek</th>
                <th>E-pošta</th>
                <th>Tip ID</th>
                <th>Akcija</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['id']) ?></td>
                    <td><?= htmlspecialchars($user['ime']) ?></td>
                    <td><?= htmlspecialchars($user['priimek']) ?></td>
                    <td><?= htmlspecialchars($user['e_mail']) ?></td>
                    <td><?= htmlspecialchars($user['tip_id']) ?></td>
                    <td>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Da li ste sigurni da želite izbrisati ovog korisnika?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button type="submit" class="delete-btn">Izbriši</button>
                            </form>
                        <?php else: ?>
                            <em>(Vi)</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="export-section">
        <form method="POST">
            <input type="hidden" name="action" value="export">
            <button type="submit" class="export-btn">Izvozi u Excel (CSV)</button>
        </form>
    </div>
</main>

<footer>
    <div class="footer-bg"></div>
</footer>

<script>
// Učitavanje teme
(function() {
    const theme = localStorage.getItem('theme') || 'light';
    if (theme === 'dark') {
        document.body.classList.add('dark-theme');
    }
})();
</script>

</body>
</html>
