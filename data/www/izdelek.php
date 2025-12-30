<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/cart_functions.php';

// preverimo ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Izdelek ne obstaja.");
}
$id = (int)$_GET['id'];

// inicializacija košarice
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Ako je korisnik prijavljen, učitaj košaricu iz baze
if (isset($_SESSION['user_id'])) {
    $dbCart = loadCartFromDB($pdo, $_SESSION['user_id']);
    // Spoji session i bazu (prioritet session)
    foreach ($_SESSION['cart'] as $cartId => $qty) {
        $dbCart[$cartId] = $qty;
    }
    $_SESSION['cart'] = $dbCart;
}

$cartMessage = "";

/* ===========================
   DODAJ V KOŠARICO (OSTANI NA STRANI)
   =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'add_to_cart'
) {
    if (!isset($_SESSION['user_id'])) {
        $cartMessage = "Za dodajanje izdelkov v košarico se morate prijaviti.";
    } else {
        $pid = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        // varnostno: dodamo le, če je ID isti kot iz URL-ja
        if ($pid === $id && $pid > 0) {
            if (!isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid] = 0;
            }
            $_SESSION['cart'][$pid]++;

            // Sačuvaj u bazu
            saveCartToDB($pdo, $_SESSION['user_id'], $_SESSION['cart']);

            $cartMessage = "Izdelek je bil dodan v košarico.";
        }
    }
}

// števec artiklov v košarici (po morebitnem dodajanju)
$cartCount = array_sum($_SESSION['cart']);

// pridobimo izdelek
$stmt = $pdo->prepare("SELECT * FROM izdelek WHERE id = :id");
$stmt->execute([':id' => $id]);
$izdelek = $stmt->fetch();

if (!$izdelek) {
    die("Izdelek ni bil najden.");
}

/* ===========================
   ADMIN DETEKCIJA
   =========================== */
$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    $uStmt = $pdo->prepare("SELECT e_mail FROM oseba WHERE id = :id");
    $uStmt->execute([':id' => $_SESSION['user_id']]);
    $user = $uStmt->fetch();
    if ($user && $user['e_mail'] === 'admin@gmail.com') {
        $isAdmin = true;
    }
}

// lepo ime tipa izdelka
$tipNazivi = [
    1 => 'Ogrlica',
    2 => 'Prstan',
    3 => 'Minduše',
    4 => 'Figurica'
];
$tip_ime = isset($tipNazivi[$izdelek['tip_id']]) ? $tipNazivi[$izdelek['tip_id']] : '';

$comment_error = "";

/* ===========================
   OBRAVNAVA POST ZA BRISANJE KOMENTARJA
   =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    if ($isAdmin) {
        $commentId = (int)$_POST['comment_id'];

        $del = $pdo->prepare("DELETE FROM komentar WHERE id = :cid AND izdelek_id = :id");
        $del->execute([
            ':cid' => $commentId,
            ':id'  => $id
        ]);
    }
    header("Location: izdelek.php?id=" . $id);
    exit;
}

/* ===========================
   OBRAVNAVA POST ZA NOV KOMENTAR
   =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['rating'])
    && !isset($_POST['delete_comment'])
    && !(isset($_POST['action']) && $_POST['action'] === 'add_to_cart')
) {

    if (!isset($_SESSION['user_id'])) {
        $comment_error = "Za komentiranje se morate prijaviti.";
    } else {
        $rating = (int)$_POST['rating'];
        $comment_text = isset($_POST['comment']) ? trim($_POST['comment']) : '';

        if ($rating < 1 || $rating > 5) {
            $comment_error = "Prosimo, izberite oceno med 1 in 5 zvezdic.";
        } else {
            $insert = $pdo->prepare("
                INSERT INTO komentar (izdelek_id, user_id, ocena, besedilo, created_at)
                VALUES (:izdelek_id, :user_id, :ocena, :besedilo, NOW())
            ");

            $insert->execute([
                ':izdelek_id' => $id,
                ':user_id'    => $_SESSION['user_id'],
                ':ocena'      => $rating,
                ':besedilo'   => $comment_text
            ]);

            // posodobimo povprečje
            $avgStmt = $pdo->prepare("SELECT AVG(ocena) FROM komentar WHERE izdelek_id = :id");
            $avgStmt->execute([':id' => $id]);
            $newAvg = $avgStmt->fetchColumn();

            $upd = $pdo->prepare("UPDATE izdelek SET povprecna_ocena = :avg WHERE id = :id");
            $upd->execute([
                ':avg' => $newAvg,
                ':id'  => $id
            ]);

            header("Location: izdelek.php?id=" . $id);
            exit;
        }
    }
}

/* ===========================
   POVPREČNA OCENA ZA PRIKAZ
   =========================== */
$avg_stmt = $pdo->prepare("
    SELECT AVG(ocena) AS avg_ocena, COUNT(*) AS cnt
    FROM komentar
    WHERE izdelek_id = :id
");
$avg_stmt->execute([':id' => $id]);
$avg_data     = $avg_stmt->fetch();
$avg_rating   = $avg_data['avg_ocena'];
$rating_count = (int)$avg_data['cnt'];

/* ===========================
   KOMENTARJI ZA PRIKAZ
   =========================== */
$comments_stmt = $pdo->prepare("SELECT * FROM komentar WHERE izdelek_id = :id ORDER BY created_at DESC");
$comments_stmt->execute([':id' => $id]);
$comments = $comments_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($izdelek['ime']) ?> - Swarovski</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .product-page {
            max-width: 1200px;
            margin: 40px auto 60px;
            padding: 0 24px;
        }

        .product-container {
            display: flex;
            gap: 40px;
            align-items: flex-start;
        }

        .product-image-large {
            flex: 1;
            max-width: 520px;
        }

        .product-image-large img {
            width: 100%;
            height: 480px;
            object-fit: cover;
            background: #fff;
            border: 1px solid #eee;
            display: block;
        }

        .product-info-block {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .product-title {
            font-size: 26px;
            font-weight: 600;
            margin: 0 0 8px;
        }

        .product-main-description {
            font-size: 14px;
            line-height: 1.6;
            color: #444;
            margin: 0 0 10px;
        }

        .product-price-large {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 6px;
        }

        .avg-rating-line {
            font-size: 14px;
            color: #444;
            margin: 0 0 10px;
        }

        .avg-rating-line span.star {
            color: #e2b007;
            font-size: 16px;
        }

        .product-details-list {
            margin-top: 4px;
            font-size: 14px;
        }

        .detail-row {
            display: flex;
            gap: 8px;
            margin-bottom: 4px;
        }

        .detail-label {
            min-width: 80px;
            color: #777;
            font-weight: 500;
        }

        .detail-value {
            color: #333;
        }

        .add-to-cart-form {
            margin-top: 18px;
        }

        .add-to-cart-btn {
            padding: 11px 24px;
            min-width: 220px;
            text-align: center;
            background: #111;
            color: #fff;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            cursor: pointer;
            border-radius: 4px;
            font-size: 12px;
        }

        .add-to-cart-btn:hover {
            background: #333;
        }

        .cart-info-msg {
            margin-top: 10px;
            display: inline-block;
            padding: 8px 12px;
            font-size: 13px;
            border-radius: 6px;
            background: #e6f4ea;
            border: 1px solid #a3d3b2;
            color: #265e33;
        }

        .reviews-section {
            margin-top: 40px;
        }

        .reviews-title {
            font-size: 22px;
            margin: 0 0 12px;
            font-family: "Playfair Display", serif;
            color: #111111;
        }

        .reviews-cta,
        .review-form-wrapper {
            width: 100%;
            max-width: 1000px;
            padding: 20px 22px;
            border-radius: 10px;
            border: 1px solid #e5e5e5;
            background: #ffffff;
        }

        .reviews-cta {
            background: #fff3f0;
            border-color: #e2b2a0;
            margin: 10px 0 24px;
        }

        .reviews-cta-text {
            margin: 0 0 10px;
            font-size: 14px;
            color: #55352a;
        }

        .btn-primary-large {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 999px;
            border: 1px solid #111;
            background: #111;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 12px;
        }

        .btn-primary-large:hover {
            background: #fff;
            color: #111;
        }

        .review-form-wrapper {
            margin-top: 10px;
        }

        .review-form-title {
            font-size: 14px;
            font-weight: 500;
            margin: 0 0 8px;
        }

        .comment-form {
            margin-top: 8px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .comment-form textarea {
            font-size: 14px;
            padding: 8px 10px;
            border-radius: 4px;
            border: 1px solid #d4d0c8;
            min-height: 110px;
            resize: vertical;
            width: 100%;
        }

        .comment-form button {
            width: 200px;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #111;
            background: #111;
            color: #fff;
            font-size: 12px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 6px;
        }

        .comments-list {
            margin-top: 18px;
            max-width: 1000px;
        }

        .comment-card {
            background: #fff;
            border: 1px solid #eee;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .comment-rating {
            font-size: 16px;
            color: #e2b007;
        }

        .comment-text {
            font-size: 14px;
            margin: 4px 0;
        }

        .comment-date {
            font-size: 11px;
            color: #888;
        }

        .error-msg {
            color: #b3261e;
            font-size: 13px;
            margin-top: 4px;
        }

        .rating-stars {
            display: inline-flex;
            flex-direction: row-reverse;
            gap: 4px;
            font-size: 22px;
            margin-bottom: 4px;
        }

        .rating-stars input {
            display: none;
        }

        .rating-stars label {
            cursor: pointer;
            color: #ccc;
            transition: color 0.15s ease;
        }

        .rating-stars label:hover,
        .rating-stars label:hover ~ label {
            color: #e2b007;
        }

        .rating-stars input:checked ~ label {
            color: #e2b007;
        }

        .delete-comment-btn {
            margin-top: 6px;
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid #b3261e;
            background: transparent;
            color: #b3261e;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
        }

        .delete-comment-btn:hover {
            background: #b3261e;
            color: #fff;
        }

        @media (max-width: 768px) {
            .product-container {
                flex-direction: column;
            }

            .product-image-large {
                max-width: 100%;
            }

            .product-image-large img {
                height: 360px;
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

<main class="product-page">

    <section class="product-container">
        <div class="product-image-large">
            <?php
    $imgSrc = !empty($izdelek['slika'])
        ? $izdelek['slika']
        : 'images/' . $izdelek['id'] . '.jpg';
?>
<img src="<?= htmlspecialchars($imgSrc) ?>"
     alt="<?= htmlspecialchars($izdelek['ime']) ?>">

        </div>

        <div class="product-info-block">
            <h2 class="product-title"><?= htmlspecialchars($izdelek['ime']) ?></h2>

            <?php if (!empty($izdelek['opis'])): ?>
                <p class="product-main-description">
                    <?= htmlspecialchars($izdelek['opis']) ?>
                </p>
            <?php endif; ?>

            <p class="product-price-large"><?= number_format($izdelek['cena'], 2) ?> €</p>

            <p class="avg-rating-line">
                <?php if ($rating_count > 0 && $avg_rating !== null): ?>
                    Povprečna ocena: <?= number_format($avg_rating, 1) ?>
                    <span class="star">★</span>
                <?php else: ?>
                    Povprečna ocena: še ni ocen.
                <?php endif; ?>
            </p>

            <div class="product-details-list">
                <?php if (!empty($tip_ime)): ?>
                    <div class="detail-row">
                        <span class="detail-label">Tip:</span>
                        <span class="detail-value"><?= htmlspecialchars($tip_ime) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($izdelek['material'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Material:</span>
                        <span class="detail-value"><?= htmlspecialchars($izdelek['material']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- DODAJ V KOŠARICO – OSTANE NA STRANI -->
            <form method="POST" action="" class="add-to-cart-form">
                <input type="hidden" name="action" value="add_to_cart">
                <input type="hidden" name="id" value="<?= $izdelek['id'] ?>">
                <button type="submit" class="add-to-cart-btn">Dodaj v košarico</button>
            </form>

            <?php if ($cartMessage): ?>
                <div class="cart-info-msg"><?= htmlspecialchars($cartMessage) ?></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="reviews-section">
        <h3 class="reviews-title">Recenzije</h3>

        <?php if (!empty($comment_error)): ?>
            <p class="error-msg"><?= htmlspecialchars($comment_error) ?></p>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="reviews-cta">
                <p class="reviews-cta-text">
                    Želite pustiti recenzijo tega izdelka? Prijavite se v svoj račun.
                </p>
                <a href="login.php" class="btn-primary-large">Prijava</a>
            </div>
        <?php else: ?>
            <div class="review-form-wrapper">
                <p class="review-form-title">Pustite svojo oceno in komentar</p>
                <form method="POST" class="comment-form">
                    <label>Vaša ocena:</label>
                    <div class="rating-stars">
                        <input type="radio" id="star5" name="rating" value="5" required>
                        <label for="star5">★</label>

                        <input type="radio" id="star4" name="rating" value="4">
                        <label for="star4">★</label>

                        <input type="radio" id="star3" name="rating" value="3">
                        <label for="star3">★</label>

                        <input type="radio" id="star2" name="rating" value="2">
                        <label for="star2">★</label>

                        <input type="radio" id="star1" name="rating" value="1">
                        <label for="star1">★</label>
                    </div>

                    <label>Komentar (neobvezno):</label>
                    <textarea name="comment"></textarea>

                    <button type="submit">Oddaj komentar</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="comments-list">
            <?php if (empty($comments)): ?>
                <p>Ni komentarjev za ta izdelek.</p>
            <?php else: ?>
                <?php foreach ($comments as $c): ?>
                    <div class="comment-card">
                        <div class="comment-rating">
                            <?= str_repeat("★", (int)$c['ocena']) ?>
                        </div>
                        <?php if (!empty($c['besedilo'])): ?>
                            <p class="comment-text"><?= htmlspecialchars($c['besedilo']) ?></p>
                        <?php endif; ?>
                        <p class="comment-date"><?= htmlspecialchars($c['created_at']) ?></p>

                        <?php if ($isAdmin): ?>
                            <form method="POST" style="margin-top: 4px;">
                                <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" name="delete_comment" class="delete-comment-btn">
                                    Izbriši komentar
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

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
