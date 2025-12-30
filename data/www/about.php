<?php
session_start();

// inicializacija košarice za prikaz števila v meniju
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cartCount = array_sum($_SESSION['cart']);
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>O nas - Swarovski</title>
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

<section class="about-section">
    <div class="about-box">
        <h2>O nas</h2>

        <p>
            Swarovski je sinonim za sijaj, eleganco in brezčasen dizajn.
            Naša spletna trgovina združuje sodobno tehnologijo in prestižno estetiko,
            da vam ponudi vrhunsko nakupovalno izkušnjo.
        </p>

        <p>
            V ponudbi najdete nakit, figurice, ure, dodatke in darilne kolekcije —
            vse zasnovano s prepoznavno Swarovski natančnostjo in kristalnim leskom.
        </p>

        <h3>Kontakt</h3>
        <p>Telefon: +386 1 234 56 78</p>
        <p>E-pošta: info@swarovski-trgovina.si</p>

        <h3>Delovni čas</h3>
        <p>Ponedeljek–petek: 9:00–19:00</p>
        <p>Sobota: 9:00–15:00</p>
        <p>Nedelja in prazniki: zaprto</p>

        <section class="locations-section" style="max-width: 980px; margin: 40px auto; padding: 0 24px;">
    <h2 style="margin-bottom: 10px;">Naše lokacije</h2>
    <p style="margin-bottom: 18px;">Spodaj si lahko ogledate nekaj naših lokacij po svetu.</p>

    <h3 style="margin: 22px 0 10px;">New York</h3>
    <img loading="lazy" decoding="async"
         src="https://mma.prnewswire.com/media/2296722/2023_NYFLAG_ARCHSHOT_ENTRANCE_GL_300DPI_CMYK.jpg"
         alt="Swarovski lokacija – New York (1)"
         style="width:100%; max-width:980px; height:420px; object-fit:cover; display:block; margin:12px auto; border-radius:14px; background:#f0f0f0;">
    <img loading="lazy" decoding="async"
         src="https://media.cnn.com/api/v1/images/stellar/prod/231207183854-02-swarovski-new-store-120723.jpg?c=original"
         alt="Swarovski lokacija – New York (2)"
         style="width:100%; max-width:980px; height:420px; object-fit:cover; display:block; margin:12px auto; border-radius:14px; background:#f0f0f0;">

    <h3 style="margin: 26px 0 10px;">Dunaj</h3>
    <img loading="lazy" decoding="async"
         src="https://www.viennadesignweek.at/site/assets/files/7740/vdw_15_programmpartner_swarovski_kistallwelten-c-swarovski-wien.1200x0.1532475587.jpg"
         alt="Swarovski lokacija – Dunaj (1)"
         style="width:100%; max-width:980px; height:420px; object-fit:cover; display:block; margin:12px auto; border-radius:14px; background:#f0f0f0;">
    <img loading="lazy" decoding="async"
         src="https://c8.alamy.com/comp/2J491DD/view-of-the-entrance-of-a-swarovski-flagship-store-in-the-historic-center-of-vienna-austria-at-shopping-street-krntner-strae-by-night-2J491DD.jpg"
         alt="Swarovski lokacija – Dunaj (2)"
         style="width:100%; max-width:980px; height:420px; object-fit:cover; display:block; margin:12px auto; border-radius:14px; background:#f0f0f0;">
</section>

        <p style="margin-top: 30px;">
            Hvala, ker zaupate našemu kristalnemu svetu. ✨
        </p>
    </div>
</section>

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