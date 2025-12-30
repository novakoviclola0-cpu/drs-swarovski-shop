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

$successMsg = "";
$errorMsg = "";

// Tipovi izdelkov (hardkodovani, kao u izdelek.php)
$tipNazivi = [
    1 => 'Ogrlica',
    2 => 'Prstan',
    3 => 'Minduše',
    4 => 'Figurica'
];

// Obdelava POST zahteve
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ime = trim($_POST['ime'] ?? '');
    $opis = trim($_POST['opis'] ?? '');
    $cena = isset($_POST['cena']) ? (float)$_POST['cena'] : 0;
    $tip_id = isset($_POST['tip_id']) ? (int)$_POST['tip_id'] : 0;
    $material = trim($_POST['material'] ?? '');

    if (empty($ime)) {
        $errorMsg = "Ime izdelka je obvezno.";
    } elseif ($cena <= 0) {
        $errorMsg = "Cena mora biti večja od 0.";
    } elseif ($tip_id <= 0) {
        $errorMsg = "Izberite tip izdelka.";
    } else {
        // Vstavimo nov izdelek
        try {
            $ins = $pdo->prepare("
                INSERT INTO izdelek (ime, opis, cena, tip_id, material, povprecna_ocena)
                VALUES (:ime, :opis, :cena, :tip_id, :material, 0)
            ");
            
            $ins->execute([
                ':ime' => $ime,
                ':opis' => $opis ?: null,
                ':cena' => $cena,
                ':tip_id' => $tip_id,
                ':material' => $material ?: null
            ]);

            $newId = $pdo->lastInsertId();
            $successMsg = "Izdelek je bil uspešno dodan (ID: $newId).";
            
            // Počistimo formo
            $_POST = [];
        } catch (PDOException $e) {
            $errorMsg = "Napaka pri dodajanju izdelka: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Dodaj izdelek - Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-page {
            max-width: 800px;
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
            margin-bottom: 20px;
        }
        .msg-success {
            padding: 10px 14px;
            border-radius: 6px;
            background: #e6f4ea;
            border: 1px solid #a3d3b2;
            color: #265e33;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .msg-error {
            padding: 10px 14px;
            border-radius: 6px;
            background: #fce8e6;
            border: 1px solid #f5b5ae;
            color: #b3261e;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .form-box {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #e5e5e5;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .form-box label {
            display: block;
            margin-top: 16px;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 13px;
            color: #333;
        }
        .form-box input[type="text"],
        .form-box input[type="number"],
        .form-box select,
        .form-box textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d5d5d5;
            border-radius: 8px;
            font-size: 14px;
            background: #fafafa;
            font-family: inherit;
        }
        .form-box input:focus,
        .form-box select:focus,
        .form-box textarea:focus {
            outline: none;
            border-color: #111;
            background: #ffffff;
        }
        .form-box textarea {
            min-height: 100px;
            resize: vertical;
        }
        .submit-btn {
            margin-top: 24px;
            padding: 12px 24px;
            background: #111;
            color: #fff;
            border: none;
            border-radius: 999px;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            cursor: pointer;
        }
        .submit-btn:hover {
            background: #333;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            font-size: 13px;
            color: #555;
            text-decoration: underline;
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
    <a href="admin_slike.php" class="back-link">← Nazaj na urejanje slik</a>
    
    <h2>Dodaj nov izdelek</h2>
    <p class="admin-note">
        Tukaj lahko dodate nov izdelek v bazo. Sliko lahko nato dodate na strani "Urejanje slik".
    </p>

    <?php if (!empty($successMsg)): ?>
        <div class="msg-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="msg-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="form-box">
        <form method="POST">
            <label for="ime">Ime izdelka *</label>
            <input type="text" id="ime" name="ime" 
                   value="<?= htmlspecialchars($_POST['ime'] ?? '') ?>" 
                   required>

            <label for="opis">Opis</label>
            <textarea id="opis" name="opis"><?= htmlspecialchars($_POST['opis'] ?? '') ?></textarea>

            <label for="cena">Cena (€) *</label>
            <input type="number" id="cena" name="cena" 
                   step="0.01" min="0.01"
                   value="<?= htmlspecialchars($_POST['cena'] ?? '') ?>" 
                   required>

            <label for="tip_id">Tip izdelka *</label>
            <select id="tip_id" name="tip_id" required>
                <option value="">-- Izberite tip --</option>
                <?php foreach ($tipNazivi as $tipId => $tipNaziv): ?>
                    <option value="<?= (int)$tipId ?>" 
                            <?= (isset($_POST['tip_id']) && $_POST['tip_id'] == $tipId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tipNaziv) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="material">Material</label>
            <input type="text" id="material" name="material" 
                   value="<?= htmlspecialchars($_POST['material'] ?? '') ?>"
                   placeholder="npr. Kristal, Srebro, Zlato">

            <button type="submit" class="submit-btn">Dodaj izdelek</button>
        </form>
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
</script>

</body>
</html>

