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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        /* Specifični stilovi samo za sekciju Moja lokacija */
        .location-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #e5e5e5;
        }
        .location-section h2 {
            font-family: "Playfair Display", serif;
            font-size: 26px;
            color: #111111;
            margin-bottom: 16px;
        }
        body.dark-theme .location-section h2 {
            color: #e0e0e0;
        }
        .location-btn {
            padding: 10px 20px;
            background: #111111;
            color: #ffffff;
            border: none;
            border-radius: 999px;
            font-size: 13px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .location-btn:hover {
            background: #ffffff;
            color: #111111;
            border: 1px solid #111111;
        }
        body.dark-theme .location-btn {
            background: #e0e0e0;
            color: #111111;
        }
        body.dark-theme .location-btn:hover {
            background: #111111;
            color: #e0e0e0;
            border-color: #e0e0e0;
        }
        #map {
            height: 400px;
            margin-top: 20px;
            border-radius: 16px;
            border: 1px solid #e5e5e5;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
        }
        body.dark-theme #map {
            border-color: #3a3a3a;
        }
        .location-note {
            font-size: 13px;
            color: #777777;
            margin-top: 10px;
        }
        body.dark-theme .location-note {
            color: #b0b0b0;
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

        <section class="locations-section" style="max-width: 980px; margin: 40px 0; padding: 0;">
            <h2 style="margin-bottom: 10px;">Naše lokacije</h2>
            <p style="margin-bottom: 18px;">Spodaj si lahko ogledate nekaj naših lokacij po svetu.</p>

            <h3 style="margin: 22px 0 10px;">New York</h3>
            <img loading="lazy" decoding="async"
                 src="https://mma.prnewswire.com/media/2296722/2023_NYFLAG_ARCHSHOT_ENTRANCE_GL_300DPI_CMYK.jpg"
                 alt="Swarovski lokacija – New York (1)"
                 style="width:100%; max-width:980px; height:420px; object-fit:cover; display:block; margin:12px 0; border-radius:14px; background:#f0f0f0;">
            <img loading="lazy" decoding="async"
                 src="https://media.cnn.com/api/v1/images/stellar/prod/231207183854-02-swarovski-new-store-120723.jpg?c=original"
                 alt="Swarovski lokacija – New York (2)"
                 style="width:100%; max-width:980px; height:420px; object-fit:cover; display:block; margin:12px 0; border-radius:14px; background:#f0f0f0;">

            <h3 style="margin: 26px 0 10px;">Dunaj</h3>
            <img loading="lazy" decoding="async"
                 src="https://www.viennadesignweek.at/site/assets/files/7740/vdw_15_programmpartner_swarovski_kistallwelten-c-swarovski-wien.1200x0.1532475587.jpg"
                 alt="Swarovski lokacija – Dunaj (1)"
                 style="width:100%; max-width:980px; height:420px; object-fit:cover; display:block; margin:12px 0; border-radius:14px; background:#f0f0f0;">
            <img loading="lazy" decoding="async"
                 src="https://c8.alamy.com/comp/2J491DD/view-of-the-entrance-of-a-swarovski-flagship-store-in-the-historic-center-of-vienna-austria-at-shopping-street-krntner-strae-by-night-2J491DD.jpg"
                 alt="Swarovski lokacija – Dunaj (2)"
                 style="width:100%; max-width:980px; height:420px; object-fit:cover; display:block; margin:12px 0; border-radius:14px; background:#f0f0f0;">
        </section>

        <!-- Sekcija Moja lokacija – sada unutar about-box -->
        <div class="location-section">
            <h2>Moja lokacija</h2>
            <p>Pritisnite gumb, da prikažete vašo trenutno lokacijo na zemljevidu, skupaj s Swarovski trgovinami v Sloveniji.</p>
            <button id="getLocation" class="location-btn">Dostop do lokacije</button>
            <p class="location-note">Zahteva se pošlje samo enkrat in se uporablja samo za prikaz na zemljevidu. Swarovski trgovine so označene z rdečimi oznakami.</p>
            <div id="map" style="display: none;"></div>
        </div>

        <p style="margin-top: 40px;">
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

// Geolocation i mapa
document.getElementById('getLocation').addEventListener('click', function() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition, showError);
    } else {
        alert("Geolokacija ni podprta v tem brskalniku.");
    }
});

function showPosition(position) {
    const lat = position.coords.latitude;
    const lon = position.coords.longitude;
    
    document.getElementById('map').style.display = 'block';
    
    const map = L.map('map').setView([lat, lon], 14);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    L.marker([lat, lon]).addTo(map)
        .bindPopup('<strong>Vaša lokacija</strong>')
        .openPopup();

    // Custom icon za Swarovski prodavnice (crveni marker)
    const storeIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    // Seznam Swarovski trgovin v Sloveniji s koordinatami in naslovi
    const stores = [
        {lat: 46.06796, lon: 14.54219, address: 'Citypark, Šmartinska c. 152g, 1000 Ljubljana'},
        {lat: 46.05256, lon: 14.50383, address: 'Hotel Slon, Slovenska cesta 34, 1000 Ljubljana'},
        {lat: 46.05459, lon: 14.50396, address: 'Gosposvetska cesta 5, 1000 Ljubljana'},
        {lat: 46.24115, lon: 15.27806, address: 'Citycenter, Mariborska cesta 100, 3000 Celje'},
        {lat: 46.55386, lon: 15.64767, address: 'Europark, Pobreška cesta 18, 2000 Maribor'},
        {lat: 46.547, lon: 15.625, address: 'Qlandia, Cesta Proletarskih Brigad 100, 2000 Maribor'}, // približno
        {lat: 46.548, lon: 15.611, address: 'Leclerc, Tržaška cesta 67a, 2000 Maribor'}, // približno
        {lat: 45.482, lon: 13.718, address: 'Mercator, Dolinska cesta 1a, 6000 Koper'}, // približno
        {lat: 45.484, lon: 13.716, address: 'Tuš, Ankaranska cesta 2, 6000 Koper'}, // približno
        {lat: 45.485, lon: 13.715, address: 'Supernova, Ankaranska cesta 3a, 6000 Koper'}, // približno
        {lat: 45.659, lon: 15.191, address: 'Qlandia, Otoška cesta 5, 8000 Novo Mesto'}, // približno
        {lat: 46.322, lon: 14.146, address: 'Qlandia, Cesta 1. maja 77, 4000 Kranj'} // približno
    ];

    // Dodaj oznake za trgovine
    for (let i = 0; i < stores.length; i++) {
        L.marker([stores[i].lat, stores[i].lon], {icon: storeIcon}).addTo(map)
            .bindPopup('Swarovski - ' + stores[i].address);
    }
}

function showError(error) {
    let message = "";
    switch(error.code) {
        case error.PERMISSION_DENIED:
            message = "Uporabnik je zavrnil zahtevo za geolokacijo.";
            break;
        case error.POSITION_UNAVAILABLE:
            message = "Informacije o lokaciji niso na voljo.";
            break;
        case error.TIMEOUT:
            message = "Zahteva za lokacijo je potekla.";
            break;
        default:
            message = "Neznana napaka pri geolokaciji.";
    }
    alert(message);
}
</script>

</body>
</html>