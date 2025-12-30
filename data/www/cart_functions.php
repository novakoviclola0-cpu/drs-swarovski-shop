<?php
// Helper funkcije za perzistenciju košarice u bazi

/**
 * Učitava košaricu iz baze za određenog korisnika
 */
function loadCartFromDB($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT izdelek_id, kolicina 
        FROM kosarica_postavka 
        WHERE oseba_id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cart = [];
    foreach ($rows as $row) {
        $cart[$row['izdelek_id']] = (int)$row['kolicina'];
    }
    
    return $cart;
}

/**
 * Čuva košaricu u bazu za određenog korisnika
 */
function saveCartToDB($pdo, $user_id, $cart) {
    // Prvo obrišemo sve postojeće stavke
    $del = $pdo->prepare("DELETE FROM kosarica_postavka WHERE oseba_id = :user_id");
    $del->execute([':user_id' => $user_id]);
    
    // Zatim dodamo nove stavke
    if (!empty($cart)) {
        $ins = $pdo->prepare("
            INSERT INTO kosarica_postavka (oseba_id, izdelek_id, kolicina) 
            VALUES (:user_id, :izdelek_id, :kolicina)
        ");
        
        foreach ($cart as $izdelek_id => $kolicina) {
            if ($kolicina > 0) {
                $ins->execute([
                    ':user_id' => $user_id,
                    ':izdelek_id' => $izdelek_id,
                    ':kolicina' => $kolicina
                ]);
            }
        }
    }
}

/**
 * Sinhronizuje session košaricu sa bazom podataka
 */
function syncCartWithDB($pdo, $user_id, &$sessionCart) {
    // Učitaj iz baze
    $dbCart = loadCartFromDB($pdo, $user_id);
    
    // Spoji session i bazu (prioritet session)
    foreach ($sessionCart as $id => $qty) {
        $dbCart[$id] = $qty;
    }
    
    // Sačuvaj u bazu
    saveCartToDB($pdo, $user_id, $dbCart);
    
    // Ažuriraj session
    $_SESSION['cart'] = $dbCart;
}
