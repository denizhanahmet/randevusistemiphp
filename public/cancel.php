<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once __DIR__ . '/../src/config/database.php';

$db = getDatabaseConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Geçersiz istek (CSRF koruması)!');
    }

    $appointmentId = $_POST['appointment_id'] ?? null;
    $email = $_POST['email'] ?? null;

    if (!$appointmentId || !$email) {
        echo "Eksik bilgi gönderildi.";
        exit;
    }

    $stmt = $db->prepare("SELECT status FROM appointments WHERE id = :id AND email = :email");
    $stmt->execute([':id' => $appointmentId, ':email' => $email]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($appointment && $appointment['status'] !== 'customer_canceled') {
        $update = $db->prepare("UPDATE appointments SET status = 'customer_canceled' WHERE id = :id");
        $update->execute([':id' => $appointmentId]);
        echo "Randevunuz başarıyla iptal edildi.";
    } else {
        echo "İptal işlemi başarısız veya randevu zaten iptal edilmiş.";
    }
} else {
    echo "Geçersiz istek.";
}