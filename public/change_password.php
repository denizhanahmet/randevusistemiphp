<?php

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Geçersiz istek (CSRF koruması)!');
}

require_once __DIR__ . '/../src/config/database.php';
$db = getDatabaseConnection();

$current = $_POST['current_password'] ?? '';
$new1 = $_POST['new_password'] ?? '';
$new2 = $_POST['new_password2'] ?? '';

if (!$current || !$new1 || !$new2) {
    $msg = "Tüm alanları doldurun!";
} elseif ($new1 !== $new2) {
    $msg = "Yeni şifreler eşleşmiyor!";
} elseif (strlen($new1) < 6) {
    $msg = "Yeni şifre en az 6 karakter olmalı!";
} else {
    // Sadece tek admin varsa:
    $stmt = $db->query("SELECT * FROM admins LIMIT 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !password_verify($current, $admin['password'])) {
        $msg = "Mevcut şifre yanlış!";
    } else {
        $hash = password_hash($new1, PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE admins SET password = :pass WHERE id = :id");
        $update->execute([':pass' => $hash, ':id' => $admin['id']]);
        $msg = "Şifreniz başarıyla değiştirildi!";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Şifre Değiştir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="2;url=admin.php">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="alert alert-info"><?= htmlspecialchars($msg) ?><br>Yönlendiriliyorsunuz...</div>
    </div>
</body>

</html>