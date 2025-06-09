<?php
require_once __DIR__ . '/../config/database.php';

class UserController {
    private $db;

    public function __construct() {
        $this->db = getDatabaseConnection();
    }

    public function showForm($errors = [], $old = []) {
        ?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Randevu Al</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5" style="max-width:600px;">
        <h1 class="mb-4">Randevu Al</h1>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="mb-3">
                <label for="name" class="form-label">İsim</label>
                <input type="text" class="form-control" id="name" name="name"
                    value="<?= htmlspecialchars($old['name'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">Telefon Numarası</label>
                <input type="text" class="form-control" id="phone" name="phone"
                    value="<?= htmlspecialchars($old['phone'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="appointment_date" class="form-label">Tarih</label>
                <input type="date" class="form-control" id="appointment_date" name="appointment_date"
                    value="<?= htmlspecialchars($old['appointment_date'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="appointment_time" class="form-label">Saat</label>
                <input type="time" class="form-control" id="appointment_time" name="appointment_time"
                    value="<?= htmlspecialchars($old['appointment_time'] ?? '') ?>" required>
            </div>

            <button type="submit" class="btn btn-primary">Randevu Al</button>
        </form>
    </div>
</body>

</html>
<?php
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
            $this->handleCancel($_GET['token']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                die('Geçersiz istek (CSRF koruması)!');
            }
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $date = trim($_POST['appointment_date'] ?? '');
        $time = trim($_POST['appointment_time'] ?? '');

        $errors = [];

        if (!$name) $errors[] = "İsim boş olamaz.";
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Geçerli bir email giriniz.";
        if (!$phone || !preg_match('/^[0-9\+\-\s]{10,15}$/', $phone)) $errors[] = "Geçerli bir telefon numarası giriniz.";
        if (!$date) $errors[] = "Tarih seçmelisiniz.";
        if (!$time) $errors[] = "Saat seçmelisiniz.";

        if ($date && strtotime($date) < strtotime(date('Y-m-d'))) {
            $errors[] = "Geçmiş tarih seçilemez.";
        }

        if ($date && $this->isBlockedDay($date)) {
            $errors[] = "Seçtiğiniz gün bloklanmış, başka bir gün deneyin.";
        }

        if ($date && $time && $this->hasAppointment($date, $time)) {
            $errors[] = "Bu tarih ve saat dolu, başka saat deneyin.";
        }

        if ($errors) {
            $this->showForm($errors, $_POST);
            return;
        }

        $cancelToken = bin2hex(random_bytes(16));

        $stmt = $this->db->prepare("INSERT INTO appointments (name, email, phone, appointment_date, appointment_time, cancel_token) VALUES (:name, :email, :phone, :date, :time, :token)");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':date' => $date,
            ':time' => $time,
            ':token' => $cancelToken
        ]);

        echo '<div class="container mt-5">
                <div class="alert alert-success">
                    Randevunuz başarıyla kaydedildi!<br>
                    Randevunuzu iptal etmek isterseniz buraya tıklayın: <br>
                    <a href="?token=' . htmlspecialchars($cancelToken) . '">Randevumu İptal Et</a>
                </div>
              </div>';

        $this->showForm();
    }

    public function handleCancel($token) {
        $stmt = $this->db->prepare("SELECT * FROM appointments WHERE cancel_token = :token");
        $stmt->execute([':token' => $token]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($appointment) {
            if ($appointment['status'] === 'customer_canceled') {
                echo '<div class="container mt-5"><div class="alert alert-warning">Bu randevu zaten iptal edilmiş.</div></div>';
            } else {
                $update = $this->db->prepare("UPDATE appointments SET status = 'customer_canceled' WHERE id = :id");
                $update->execute([':id' => $appointment['id']]);
                echo '<div class="container mt-5"><div class="alert alert-success">Randevunuz başarıyla iptal edildi.</div></div>';
            }
        } else {
            echo '<div class="container mt-5"><div class="alert alert-danger">Geçersiz veya bulunamayan iptal bağlantısı.</div></div>';
        }
    }

    private function isBlockedDay($date) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM blocked_days WHERE date = :date");
        $stmt->execute([':date' => $date]);
        return $stmt->fetchColumn() > 0;
    }

    private function hasAppointment($date, $time) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = :date AND appointment_time = :time AND status != 'customer_canceled'");
        $stmt->execute([':date' => $date, ':time' => $time]);
        return $stmt->fetchColumn() > 0;
    }
}