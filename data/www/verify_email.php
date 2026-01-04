<?php
session_start();
require __DIR__ . '/db.php';

// inicializacija košarice za prikaz števila v meniju
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cartCount = array_sum($_SESSION['cart']);

$message = '';
$success = false;

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    if (empty($token)) {
        $message = "Neveljaven verifikacijski token.";
    } else {
        // Preveri, ali token obstaja v bazi
        $stmt = $pdo->prepare("SELECT id, e_mail FROM oseba WHERE verification_token = :token AND email_verified = 0");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Posodobi uporabnika kot verificiranega
            $update = $pdo->prepare("UPDATE oseba SET email_verified = 1, verification_token = NULL WHERE id = :id");
            $update->execute([':id' => $user['id']]);
            
            $success = true;
            $message = "Vaš e-poštni naslov je bil uspešno potrjen! Sedaj se lahko prijavite.";
        } else {
            $message = "Neveljaven ali že uporabljen verifikacijski token.";
        }
    }
} else {
    $message = "Manjka verifikacijski token.";
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Potrditev e-pošte</title>
    <link rel="stylesheet" href="style.css">
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

<div class="form-container">
    <div class="form-box">
        <h2>Potrditev e-pošte</h2>

        <?php if ($success): ?>
            <div class="success-msg" style="background: #e6f4ea; border: 1px solid #a3d3b2; color: #265e33; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                <p><?= htmlspecialchars($message) ?></p>
            </div>
            <p style="text-align: center; margin-top: 20px;">
                <a href="login.php" style="display: inline-block; padding: 10px 20px; background: #111; color: #fff; border-radius: 999px; text-decoration: none; font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em;">Prijava</a>
            </p>
        <?php else: ?>
            <div class="error-msg">
                <p><?= htmlspecialchars($message) ?></p>
            </div>
            <p style="text-align: center; margin-top: 20px;">
                <a href="register.php" style="display: inline-block; padding: 10px 20px; background: #111; color: #fff; border-radius: 999px; text-decoration: none; font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em;">Nazaj na registracijo</a>
            </p>
        <?php endif; ?>
    </div>
</div>

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

