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

// sporočila
$successMsg = "";
$errorMsg   = "";

// obdelava uploada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['izdelek_id'])) {
    $izdelek_id = (int)$_POST['izdelek_id'];

    if ($izdelek_id <= 0) {
        $errorMsg = "Neveljaven ID izdelka.";
    } elseif (!isset($_FILES['slika']) || $_FILES['slika']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Napaka pri nalaganju datoteke.";
    } else {
        $file = $_FILES['slika'];

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $fileName   = $file['name'];
        $tmpPath    = $file['tmp_name'];

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errorMsg = "Dovoljene so samo slike (JPG, PNG, WEBP).";
        } else {
            $newName = 'images/izdelek_' . $izdelek_id . '_' . time() . '.' . $ext;
            $fullPath = __DIR__ . '/' . $newName;

            if (!move_uploaded_file($tmpPath, $fullPath)) {
                $errorMsg = "Premik slike ni uspel (morda pravice do mape?).";
            } else {
                $upd = $pdo->prepare("UPDATE izdelek SET slika = :slika WHERE id = :id");
                $upd->execute([
                    ':slika' => $newName,
                    ':id'    => $izdelek_id
                ]);
                $successMsg = "Slika za izdelek je bila uspešno posodobljena.";
            }
        }
    }
}

// pridobimo vse izdelke za prikaz v tabeli
$stmt = $pdo->query("SELECT id, ime, slika FROM izdelek ORDER BY id ASC");
$izdelki = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Urejanje slik izdelkov - Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-page {
            max-width: 1000px;
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
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .admin-table th,
        .admin-table td {
            border-bottom: 1px solid #e3e3e3;
            padding: 8px;
            text-align: left;
            vertical-align: middle;
        }
        .admin-table th {
            background: #f9f9f9;
            font-weight: 600;
        }
        .admin-thumb {
            width: 80px;
            height: 80px;
            background: #f0f0f0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .admin-thumb img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .upload-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .upload-form input[type="file"] {
            font-size: 13px;
        }
        .upload-btn {
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid #111;
            background: #111;
            color: #fff;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            cursor: pointer;
        }
        .upload-btn:hover {
            background: #fff;
            color: #111;
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
    <h2>Urejanje slik izdelkov</h2>
    <p class="admin-note">
        Tukaj lahko za vsak izdelek naložite sliko. Pot slike se shrani v bazo (stolpec <code>slika</code>),
        na strani pa se prikaže ta slika. Če slika ni nastavljena, se uporabi privzet vzorec <code>images/ID.jpg</code>.
    </p>

    <?php if (!empty($successMsg)): ?>
        <div class="msg-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="msg-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Izdelek</th>
                <th>Trenutna slika</th>
                <th>Naloži novo sliko</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($izdelki as $iz): ?>
                <?php
                    $thumbSrc = $iz['slika']
                        ? $iz['slika']
                        : 'images/' . $iz['id'] . '.jpg';
                ?>
                <tr>
                    <td><?= (int)$iz['id'] ?></td>
                    <td><?= htmlspecialchars($iz['ime']) ?></td>
                    <td>
                        <div class="admin-thumb">
                            <img src="<?= htmlspecialchars($thumbSrc) ?>" alt="" loading="lazy" decoding="async">
                        </div>
                    </td>
                    <td>
                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                            <input type="hidden" name="izdelek_id" value="<?= (int)$iz['id'] ?>">
                            <input type="file" name="slika" accept=".jpg,.jpeg,.png,.webp" required>
                            <button type="submit" class="upload-btn">Naloži</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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
