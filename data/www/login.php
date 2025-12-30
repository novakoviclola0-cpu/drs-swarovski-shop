<?php
session_start();
require __DIR__ . '/db.php';

// košarica count
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cartCount = array_sum($_SESSION['cart']);

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $geslo = $_POST['geslo'] ?? '';

    if ($email === '' || $geslo === '') {
        $errors[] = "Vnesite e-poštni naslov in geslo.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM oseba WHERE e_mail = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($geslo, $user['geslo'])) {
            $errors[] = "Napačen e-poštni naslov ali geslo.";
        } else {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['ime']      = $user['ime'];
            $_SESSION['priimek']  = $user['priimek'];
            $_SESSION['email']    = $user['e_mail'];
            $_SESSION['tip_id']   = $user['tip_id'];

            // Učitaj košaricu iz baze i spoji sa session košaricom
            require __DIR__ . '/cart_functions.php';
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            $dbCart = loadCartFromDB($pdo, $user['id']);
            // Spoji: prioritet ima baza, ali ako ima nešto u session, dodaj
            foreach ($_SESSION['cart'] as $cartId => $qty) {
                if (isset($dbCart[$cartId])) {
                    $dbCart[$cartId] += $qty;
                } else {
                    $dbCart[$cartId] = $qty;
                }
            }
            $_SESSION['cart'] = $dbCart;
            // Sačuvaj spojenu košaricu u bazu
            saveCartToDB($pdo, $user['id'], $dbCart);

            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Prijava</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .custom-alert {
            max-width: 400px;
            margin: 0 auto 15px;
            padding: 10px 14px;
            border-radius: 6px;
            background: #e6f4ea;
            border: 1px solid #a3d3b2;
            font-size: 14px;
            color: #265e33;
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

<div class="form-container">
    <div class="form-box">
        <h2>Prijava</h2>

        <?php if (isset($_GET['registered'])): ?>
            <div class="custom-alert">
                Račun je bil uspešno ustvarjen. Zdaj se lahko prijavite.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-msg">
                <?php foreach ($errors as $e): ?>
                    <p><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label for="email">E-pošta</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($email) ?>">

            <label for="geslo">Geslo</label>
            <input type="password" id="geslo" name="geslo">

            <button type="submit" class="submit-btn">Prijava</button>
        </form>

        <p style="margin-top:12px; font-size:14px;">
            Še nimate računa?
            <a href="register.php">Registracija</a>
        </p>
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

