<?php
$dsn = 'mysql:host=podatkovna-baza;dbname=swarovski;charset=utf8mb4';
$user = 'root';
$pass = 'superVarnoGeslo';

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  exit('Povezava z bazo ni uspela: ' . $e->getMessage());
}
?>
