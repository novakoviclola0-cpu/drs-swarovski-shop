<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/cart_functions.php';

// inicializacija košarice za prikaz v meniju
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Če je uporabnik prijavljen, naloži košarico iz baze
if (isset($_SESSION['user_id'])) {
    $dbCart = loadCartFromDB($pdo, $_SESSION['user_id']);
    // Spoji session in bazo (prioriteta ima session)
    foreach ($_SESSION['cart'] as $cartId => $qty) {
        $dbCart[$cartId] = $qty;
    }
    $_SESSION['cart'] = $dbCart;
}

$cartCount = array_sum($_SESSION['cart']);

// PAGINACIJA
$items_per_page = 3;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// FILTRIRANJE PO KATEGORIJI
$tip = isset($_GET['tip']) ? (int)$_GET['tip'] : 0;

// ISKANJE & RAZVRŠČANJE
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort   = isset($_GET['sort']) ? $_GET['sort'] : '';

$whereParts = [];
$params = [];

if ($tip > 0) {
    $whereParts[] = "tip_id = :tip";
    $params[':tip'] = $tip;
}

if ($search !== '') {
    $whereParts[] = "ime LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

$where = '';
if (!empty($whereParts)) {
    $where = ' WHERE ' . implode(' AND ', $whereParts);
}

// število vseh izdelkov
$total_sql = "SELECT COUNT(*) FROM izdelek" . $where;
$total_stmt = $pdo->prepare($total_sql);

foreach ($params as $key => $value) {
    $type = ($key === ':tip') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $total_stmt->bindValue($key, $value, $type);
}
$total_stmt->execute();
$total_items = (int)$total_stmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_items / $items_per_page));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $items_per_page;
}

// razvrščanje
$orderBy = '';
if ($sort === 'price_asc') {
    $orderBy = ' ORDER BY cena ASC';
} elseif ($sort === 'price_desc') {
    $orderBy = ' ORDER BY cena DESC';
}

// pridobivanje izdelkov
$data_sql = "SELECT * FROM izdelek" . $where . $orderBy . " LIMIT :offset, :limit";
$data_stmt = $pdo->prepare($data_sql);

foreach ($params as $key => $value) {
    $type = ($key === ':tip') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $data_stmt->bindValue($key, $value, $type);
}

$data_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$data_stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$data_stmt->execute();
$products = $data_stmt->fetchAll();

// query string za paginacijo
$query_extra = '';
if ($tip)           $query_extra .= '&tip=' . $tip;
if ($search !== '') $query_extra .= '&q=' . urlencode($search);
if ($sort !== '')   $query_extra .= '&sort=' . urlencode($sort);
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Swarovski</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .theme-toggle {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid #e5e5e5;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .theme-toggle:hover {
            background: #f5f5f5;
        }
        body.dark-theme {
            background: #1a1a1a;
            color: #e0e0e0;
        }
        body.dark-theme .main-header {
            background: #2a2a2a;
            border-bottom-color: #3a3a3a;
        }
        body.dark-theme .category-bar {
            background: #2a2a2a;
            border-bottom-color: #3a3a3a;
        }
        body.dark-theme .product-box {
            background: #2a2a2a;
            border-color: #3a3a3a;
        }
        body.dark-theme .product-box h3,
        body.dark-theme .product-box .price {
            color: #e0e0e0;
        }
        body.dark-theme .logo-title,
        body.dark-theme .best-title h2 {
            color: #e0e0e0;
        }
        body.dark-theme .right-menu a {
            color: #d0d0d0;
        }
        body.dark-theme .category-bar a {
            color: #b0b0b0;
        }
        body.dark-theme .category-bar a.active-cat {
            color: #e0e0e0;
            border-bottom-color: #e0e0e0;
        }
        body.dark-theme .theme-toggle {
            background: #2a2a2a;
            border-color: #3a3a3a;
            color: #e0e0e0;
        }
        body.dark-theme .theme-toggle:hover {
            background: #3a3a3a;
        }
    </style>
</head>

<body>

<div class="theme-toggle" id="themeToggle" onclick="toggleTheme()">
    <span id="themeIcon">🌙</span>
    <span id="themeText">Tamna</span>
</div>

<header class="main-header">
    <div class="pink-bg"></div>
    <div class="header-content">
        <h1 class="logo-title">Swarovski</h1>

        <nav class="right-menu">
            <a href="index.php" class="active-link">Domov</a>
            <a href="kosarica.php">
                Košarica<?= $cartCount ? " ($cartCount)" : "" ?>
            </a>

            <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@gmail.com'): ?>
                <a href="admin_slike.php">Urejanje slik</a>
                <a href="admin_statistika.php">Statistika</a>
                <a href="admin_dodaj_izdelek.php">Dodaj izdelek</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@gmail.com'): ?>
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

<!-- KATEGORIJE -->
<div class="category-bar">
    <a href="index.php" class="<?= $tip === 0 ? 'active-cat' : '' ?>">Vsi izdelki</a>
    <a href="index.php?tip=1" class="<?= $tip === 1 ? 'active-cat' : '' ?>">Ogrlice</a>
    <a href="index.php?tip=3" class="<?= $tip === 3 ? 'active-cat' : '' ?>">Uhani</a>
    <a href="index.php?tip=2" class="<?= $tip === 2 ? 'active-cat' : '' ?>">Prstani</a>
    <a href="index.php?tip=4" class="<?= $tip === 4 ? 'active-cat' : '' ?>">Figurice</a>
</div>

<section class="best-title">
    <h2>
        <?php
        if     ($tip === 1) echo "Ogrlice";
        elseif ($tip === 2) echo "Prstani";
        elseif ($tip === 3) echo "Uhani";
        elseif ($tip === 4) echo "Figurice";
        else                echo "Vsi izdelki";
        ?>
    </h2>
</section>

<!-- ISKANJE + RAZVRŠČANJE -->
<section class="products-toolbar">
    <div class="toolbar-inner">
        <div class="toolbar-left">
            <?php if ($total_items > 0): ?>
                <?php
                $shown_from = $offset + 1;
                $shown_to   = $offset + count($products);
                ?>
                <p class="results-count">
                </p>
            <?php else: ?>
                <p class="results-count">Ni najdenih izdelkov.</p>
            <?php endif; ?>
        </div>

        <div class="toolbar-right">
            <form method="get" class="filter-form">
                <input type="hidden" name="page" value="1">
                <?php if ($tip): ?>
                    <input type="hidden" name="tip" value="<?= $tip ?>">
                <?php endif; ?>

                <div class="search-wrapper">
                    <input
                        type="text"
                        name="q"
                        placeholder="Išči po imenu"
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>

                <div class="sort-wrapper">
                    <select name="sort">
                        <option value="">Razvrsti</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>
                            Cena: od najnižje
                        </option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>
                            Cena: od najvišje
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn-apply">Uporabi</button>
            </form>
        </div>
    </div>
</section>

<!-- SEZNAM IZDELKOV -->
<section class="products-list">
    <div class="product-row">
        <?php if (empty($products)): ?>
            <p class="no-products">Ni izdelkov za izbrane filtre.</p>
        <?php else: ?>
            <?php foreach ($products as $p): ?>
                <?php
                    $imgSrc = !empty($p['slika'])
                        ? $p['slika']
                        : 'images/' . $p['id'] . '.jpg';
                ?>
                <a href="izdelek.php?id=<?= $p['id'] ?>">
                    <div class="product-box">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($p['ime']) ?>" loading="lazy" decoding="async">
                        <h3><?= htmlspecialchars($p['ime']) ?></h3>
                        <p class="price"><?= number_format($p['cena'], 2) ?> €</p>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- PAGINACIJA -->
<div class="pagination">
    <?php if ($page > 1): ?>
        <a class="page-btn"
           href="?page=<?= $page-1 ?><?= $query_extra ?>">◀ Prejšnja</a>
    <?php endif; ?>

    <span class="current-page">Stran <?= $page ?>/<?= $total_pages ?></span>

    <?php if ($page < $total_pages): ?>
        <a class="page-btn"
           href="?page=<?= $page+1 ?><?= $query_extra ?>">Naslednja ▶</a>
    <?php endif; ?>
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
        document.getElementById('themeIcon').textContent = '☀️';
        document.getElementById('themeText').textContent = 'Svetla';
    }
})();

function toggleTheme() {
    const body = document.body;
    const isDark = body.classList.contains('dark-theme');
    const icon = document.getElementById('themeIcon');
    const text = document.getElementById('themeText');
    
    if (isDark) {
        body.classList.remove('dark-theme');
        localStorage.setItem('theme', 'light');
        icon.textContent = '🌙';
        text.textContent = 'Tamna';
    } else {
        body.classList.add('dark-theme');
        localStorage.setItem('theme', 'dark');
        icon.textContent = '☀️';
        text.textContent = 'Svetla';
    }
}
</script>

</body>
</html>
