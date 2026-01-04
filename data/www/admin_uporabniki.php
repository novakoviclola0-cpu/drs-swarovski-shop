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

// AJAX brisanje korisnika
if (isset($_POST['ajax']) && $_POST['ajax'] == '1' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    header('Content-Type: application/json');
    try {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== $_SESSION['user_id']) {
            // Najpre obriši komentarje uporabnika
            $delComments = $pdo->prepare("DELETE FROM komentar WHERE user_id = :id");
            $delComments->execute([':id' => $userId]);
            
            // Nato obriši košarico uporabnika (če obstaja tabela košarica)
            try {
                $delCart = $pdo->prepare("DELETE FROM kosarica WHERE user_id = :id");
                $delCart->execute([':id' => $userId]);
            } catch (Exception $e) {
                // Ignoriraj, če tabela ne obstaja
            }
            
            // Končno obriši uporabnika
            $delStmt = $pdo->prepare("DELETE FROM oseba WHERE id = :id");
            $delStmt->execute([':id' => $userId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ne morete izbrisati sebe.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Napaka pri brisanju: ' . $e->getMessage()]);
    }
    exit;
}

// Standardno brisanje (fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user' && !isset($_POST['ajax'])) {
    $userId = (int)$_POST['user_id'];
    if ($userId > 0 && $userId !== $_SESSION['user_id']) {
        // Najpre obriši komentarje uporabnika
        $delComments = $pdo->prepare("DELETE FROM komentar WHERE user_id = :id");
        $delComments->execute([':id' => $userId]);
        
        // Nato obriši košarico uporabnika (če obstaja tabela košarica)
        try {
            $delCart = $pdo->prepare("DELETE FROM kosarica WHERE user_id = :id");
            $delCart->execute([':id' => $userId]);
        } catch (Exception $e) {
            // Ignoriraj, če tabela ne obstaja
        }
        
        // Končno obriši uporabnika
        $delStmt = $pdo->prepare("DELETE FROM oseba WHERE id = :id");
        $delStmt->execute([':id' => $userId]);
    }
    header("Location: admin_uporabniki.php");
    exit;
}

// Izvoz u Excel format (Excel XML - ne zahteva dodatne knjižnice)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_excel') {
    $filename = "uporabniki_" . date('Y-m-d') . ".xls";
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Excel XML zaglavlje
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
    echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
    echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<Worksheet ss:Name="Uporabniki">' . "\n";
    echo '<Table>' . "\n";
    
    // Zaglavlje
    echo '<Row>' . "\n";
    echo '<Cell><Data ss:Type="String">ID</Data></Cell>' . "\n";
    echo '<Cell><Data ss:Type="String">Ime</Data></Cell>' . "\n";
    echo '<Cell><Data ss:Type="String">Priimek</Data></Cell>' . "\n";
    echo '<Cell><Data ss:Type="String">E-pošta</Data></Cell>' . "\n";
    echo '</Row>' . "\n";
    
    // Podatki
    $stmt = $pdo->query("SELECT id, ime, priimek, e_mail FROM oseba ORDER BY id ASC");
    while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo '<Row>' . "\n";
        echo '<Cell><Data ss:Type="Number">' . (int)$u['id'] . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($u['ime'] ?? '') . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($u['priimek'] ?? '') . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($u['e_mail'] ?? '') . '</Data></Cell>' . "\n";
        echo '</Row>' . "\n";
    }
    
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>' . "\n";
    exit;
}

// Pridobimo vse uporabnike
$usersStmt = $pdo->query("SELECT id, ime, priimek, e_mail FROM oseba ORDER BY id ASC");
$users = $usersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Uporabniki - Admin</title>
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
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .admin-table th,
        .admin-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }
        .admin-table th {
            background: #f9f9f9;
            font-weight: 600;
        }
        .admin-table tr:hover {
            background: #f9f9f9;
        }
        .delete-btn {
            background: #ff4d4d;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover {
            background: #e60000;
        }
        .export-form {
            text-align: right;
        }
        .export-btn {
            background: #111;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 14px;
        }
        .export-btn:hover {
            background: #333;
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

<main class="admin-page">
    <h2>Upravljanje uporabnikov</h2>
    <p class="admin-note">
    </p>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Ime</th>
                <th>Priimek</th>
                <th>E-pošta</th>
                <th>Akcija</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= htmlspecialchars($u['ime'] ?? '') ?></td>
                    <td><?= htmlspecialchars($u['priimek'] ?? '') ?></td>
                    <td><?= htmlspecialchars($u['e_mail'] ?? '') ?></td>
                    <td>
                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                            <button type="button" class="delete-btn" onclick="deleteUser(<?= $u['id'] ?>, this)">
                                Izbriši
                            </button>
                        <?php else: ?>
                            <span style="color: #888; font-size: 12px;">(Vi)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="export-form">
        <form method="POST">
            <input type="hidden" name="action" value="export_excel">
            <button type="submit" class="export-btn">Izvozi v Excel</button>
        </form>
    </div>
</main>

<footer>
    <div class="footer-bg"></div>
</footer>

<script>
function deleteUser(userId, button) {
    if (!confirm('Ste prepričani, da želite izbrisati tega uporabnika?')) return;

    fetch('admin_uporabniki.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_user&user_id=' + userId + '&ajax=1'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            button.closest('tr').remove();
        } else {
            alert(data.message || 'Napaka pri brisanju.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Napaka pri komunikaciji s strežnikom: ' + error.message);
    });
}
</script>

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