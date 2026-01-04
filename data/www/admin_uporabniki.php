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
    $userId = (int)$_POST['user_id'];
    if ($userId > 0 && $userId !== $_SESSION['user_id']) {
        $delStmt = $pdo->prepare("DELETE FROM oseba WHERE id = :id");
        $delStmt->execute([':id' => $userId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ne morete izbrisati sebe.']);
    }
    exit;
}

// Standardno brisanje (fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user' && !isset($_POST['ajax'])) {
    $userId = (int)$_POST['user_id'];
    if ($userId > 0 && $userId !== $_SESSION['user_id']) {
        $delStmt = $pdo->prepare("DELETE FROM oseba WHERE id = :id");
        $delStmt->execute([':id' => $userId]);
    }
    header("Location: admin_uporabniki.php");
    exit;
}

// Izvoz u pravi Excel (.xlsx) – zahteva PhpSpreadsheet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_excel') {
    require_once __DIR__ . '/vendor/autoload.php'; // Composer autoload

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Zaglavlje
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'Ime');
    $sheet->setCellValue('C1', 'Priimek');
    $sheet->setCellValue('D1', 'E-pošta');
    $sheet->setCellValue('E1', 'Tip ID');

    // Stil zaglavlja
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF111111']],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ];
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

    // Podaci
    $stmt = $pdo->query("SELECT id, ime, priimek, e_mail, tip_id FROM oseba ORDER BY id ASC");
    $row = 2;
    while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sheet->setCellValue('A' . $row, $u['id']);
        $sheet->setCellValue('B' . $row, $u['ime'] ?? '');
        $sheet->setCellValue('C' . $row, $u['priimek'] ?? '');
        $sheet->setCellValue('D' . $row, $u['e_mail'] ?? '');
        $sheet->setCellValue('E' . $row, $u['tip_id'] ?? 0);
        $row++;
    }

    // Auto-širina kolona
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = "uporabniki_" . date('Y-m-d') . ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Pridobimo vse uporabnike
$usersStmt = $pdo->query("SELECT id, ime, priimek, e_mail, tip_id FROM oseba ORDER BY id ASC");
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
        Tukaj lahko vidite seznam vseh registriranih uporabnikov in jih izbrišete (razen sebe). 
        Tip ID: 1 = Admin, 2 = Navadni uporabnik.
    </p>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Ime</th>
                <th>Priimek</th>
                <th>E-pošta</th>
                <th>Tip ID</th>
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
                    <td><?= (int)($u['tip_id'] ?? 0) ?></td>
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
            <button type="submit" class="export-btn">Izvozi u Excel (.xlsx)</button>
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
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.closest('tr').remove();
        } else {
            alert(data.message || 'Napaka pri brisanju.');
        }
    })
    .catch(() => alert('Napaka pri komunikaciji s strežnikom.'));
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