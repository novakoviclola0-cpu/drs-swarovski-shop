<?php
session_start();
require __DIR__ . '/db.php';

// košarica count
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cartCount = array_sum($_SESSION['cart']);

$errors = [];
$ime = $priimek = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ime      = trim($_POST['ime']      ?? '');
    $priimek  = trim($_POST['priimek']  ?? '');
    $email    = trim($_POST['email']    ?? '');
    $geslo    = $_POST['geslo']        ?? '';
    $geslo2   = $_POST['geslo2']       ?? '';

    if ($ime === '' || $priimek === '' || $email === '' || $geslo === '' || $geslo2 === '') {
        $errors[] = "Vsa polja so obvezna.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "E-poštni naslov ni veljaven.";
    }

    if ($geslo !== $geslo2) {
        $errors[] = "Gesli se ne ujemata.";
    }

    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM oseba WHERE e_mail = :email");
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            $errors[] = "Uporabnik s tem e-poštnim naslovom že obstaja.";
        }
    }

    if (empty($errors)) {
        $hash = password_hash($geslo, PASSWORD_DEFAULT);

        $ins = $pdo->prepare("
            INSERT INTO oseba (ime, priimek, e_mail, geslo, tip_id)
            VALUES (:ime, :priimek, :email, :geslo, 2)
        ");

        $ins->execute([
            ':ime'     => $ime,
            ':priimek' => $priimek,
            ':email'   => $email,
            ':geslo'   => $hash
        ]);

        header("Location: login.php?registered=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Registracija</title>
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
        <h2>Registracija</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-msg">
                <?php foreach ($errors as $e): ?>
                    <p><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label for="ime">Ime</label>
            <input type="text" id="ime" name="ime"
                   value="<?= htmlspecialchars($ime) ?>">

            <label for="priimek">Priimek</label>
            <input type="text" id="priimek" name="priimek"
                   value="<?= htmlspecialchars($priimek) ?>">

            <label for="email">E-pošta</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($email) ?>">

            <label for="geslo">Geslo</label>
            <input type="password" id="geslo" name="geslo">

            <label for="geslo2">Ponovi geslo</label>
            <input type="password" id="geslo2" name="geslo2">

            <button type="submit" class="submit-btn">Ustvari račun</button>
        </form>

        <p style="margin-top:12px; font-size:14px;">
            Že imate račun?
            <a href="login.php">Prijava</a>
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

