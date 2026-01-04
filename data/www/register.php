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
        
        // Generiraj verifikacijski token
        $verificationToken = bin2hex(random_bytes(32));
        
        // Poskusi vstaviti uporabnika z verifikacijskim tokenom
        try {
            $ins = $pdo->prepare("
                INSERT INTO oseba (ime, priimek, e_mail, geslo, tip_id, verification_token, email_verified)
                VALUES (:ime, :priimek, :email, :geslo, 2, :token, 0)
            ");

            $ins->execute([
                ':ime'     => $ime,
                ':priimek' => $priimek,
                ':email'   => $email,
                ':geslo'   => $hash,
                ':token'   => $verificationToken
            ]);
            
            // Pošlji verifikacijski email
            $verifyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                        . "://" . $_SERVER['HTTP_HOST'] 
                        . dirname($_SERVER['PHP_SELF']) 
                        . "/verify_email.php?token=" . $verificationToken;
            
            $subject = "Potrditev e-poštnega naslova - Swarovski";
            $message = "Pozdravljeni " . htmlspecialchars($ime) . ",\n\n";
            $message .= "Hvala za registracijo! Prosimo, potrdite vaš e-poštni naslov s klikom na spodnjo povezavo:\n\n";
            $message .= $verifyUrl . "\n\n";
            $message .= "Če niste zahtevali ta račun, lahko to sporočilo prezrete.\n\n";
            $message .= "Lep pozdrav,\nEkipa Swarovski";
            
            $headers = "From: noreply@swarovski.si\r\n";
            $headers .= "Reply-To: noreply@swarovski.si\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            @mail($email, $subject, $message, $headers);
            
            $_SESSION['registration_success'] = true;
            $_SESSION['registration_email'] = $email;
            header("Location: register.php?success=1");
            exit;
        } catch (PDOException $e) {
            // Preveri, ali je napaka zaradi že obstoječega emaila
            if ($e->getCode() == 23000) {
                $errors[] = "Uporabnik s tem e-poštnim naslovom že obstaja.";
            } else {
                $errors[] = "Napaka pri registraciji. Poskusite znova.";
            }
        }
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
        <h2>Registracija</h2>

        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
            <div class="success-msg" style="background: #e6f4ea; border: 1px solid #a3d3b2; color: #265e33; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                <p><strong>Registracija uspešna!</strong></p>
                <p>Na vaš e-poštni naslov smo vam poslali povezavo za potrditev. Prosimo, preverite svojo e-pošto in kliknite na povezavo, da aktivirate vaš račun.</p>
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

