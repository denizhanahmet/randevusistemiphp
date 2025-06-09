<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require __DIR__ . '/../src/config/database.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once __DIR__ . '/../src/controllers/AdminController.php';

$db = getDatabaseConnection();
$controller = new AdminController($db);

$message = '';

// Durum güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Geçersiz istek (CSRF koruması)!');
    }

    if (isset($_POST['action']) && isset($_POST['appointment_id'])) {
        $id = (int)$_POST['appointment_id'];
        if ($_POST['action'] === 'approve') {
            if ($controller->updateStatus($id, 'approved')) {
                $message = "Randevu onaylandı.";
            } else {
                $message = "Onaylama işlemi başarısız.";
            }
        } elseif ($_POST['action'] === 'cancel') {
            if ($controller->updateStatus($id, 'cancelled')) { // <-- customer_canceled yerine cancelled
                $message = "Randevu iptal edildi.";
            } else {
                $message = "İptal işlemi başarısız.";
            }
        }
    }

    // Bloklu gün ekleme
    if (isset($_POST['block_date'])) {
        $date = $_POST['block_date'];
        if ($controller->addBlockedDay($date)) {
            $message = "Bloklu gün eklendi.";
        } else {
            $message = "Bloklu gün eklenirken hata oluştu.";
        }
    }

    // Bloklu gün silme
    if (isset($_POST['remove_date'])) {
        $date = $_POST['remove_date'];
        if ($controller->removeBlockedDay($date)) {
            $message = "Bloklu gün silindi.";
        } else {
            $message = "Bloklu gün silinirken hata oluştu.";
        }
    }
}

$appointments = $controller->listAppointments();
$blockedDays = $controller->listBlockedDays();
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8" />
    <title>Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .table-actions form {
        display: inline;
    }

    .modal-bg {
        background: rgba(0, 0, 0, 0.3);
    }
    </style>
</head>

<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary">Admin Paneli</h1>
            <div>
                <a href="logout.php" class="btn btn-outline-secondary">Çıkış Yap</a>
                <button type="button" class="btn btn-outline-primary ms-2"
                    onclick="document.getElementById('changePassModal').style.display='block'">Şifre Değiştir</button>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <h2 class="mb-3">Randevular</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle shadow-sm">
                <thead class="table-primary">
                    <tr>
                        <th>İsim</th>
                        <th>Email</th>
                        <th>Telefon</th>
                        <th>Tarih</th>
                        <th>Saat</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['name']) ?></td>
                        <td><?= htmlspecialchars($a['email']) ?></td>
                        <td><?= htmlspecialchars($a['phone']) ?></td>
                        <td><?= htmlspecialchars($a['appointment_date']) ?></td>
                        <td><?= htmlspecialchars($a['appointment_time']) ?></td>
                        <td>
                            <?php
                            $status = $a['status'] ?? 'pending';
                            if ($status === 'customer_canceled') {
                                echo '<span class="badge bg-danger">Müşteri tarafından iptal edildi</span>';
                            } elseif ($status === 'cancelled') {
                                echo '<span class="badge bg-danger">Admin tarafından iptal edildi</span>';
                            } else {
                                $colors = ['pending' => 'warning', 'approved' => 'success'];
                                $color = $colors[$status] ?? 'secondary';
                                echo '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
                            }
                            ?>
                        </td>
                        <td class="table-actions">
                            <?php if ($status !== 'approved'): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                <button type="submit" name="action" value="approve"
                                    class="btn btn-success btn-sm">Onayla</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($status !== 'cancelled' && $status !== 'customer_canceled'): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm">İptal
                                    Et</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="mt-5 mb-3">Bloklu Günler</h2>
        <form method="post" class="row g-2 mb-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="col-auto">
                <input type="date" name="block_date" class="form-control" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Günü Blokla</button>
            </div>
        </form>
        <ul class="list-group">
            <?php foreach ($blockedDays as $b): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($b['date']) ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="remove_date" value="<?= $b['date'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Sil</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Şifre Değiştir Modalı -->
    <div id="changePassModal" class="modal-bg position-fixed top-0 start-0 w-100 h-100 d-none" style="z-index:9999;">
        <div class="d-flex align-items-center justify-content-center h-100">
            <div class="bg-white p-4 rounded shadow" style="min-width:320px;max-width:400px;">
                <form method="post" action="change_password.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <h3 class="mb-3">Şifre Değiştir</h3>
                    <div class="mb-3">
                        <label>Mevcut Şifre</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Yeni Şifre</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Yeni Şifre (Tekrar)</label>
                        <input type="password" name="new_password2" class="form-control" required>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <button type="button" class="btn btn-secondary"
                            onclick="document.getElementById('changePassModal').style.display='none'">Vazgeç</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    // Modal aç/kapat
    document.querySelectorAll('button[onclick*="changePassModal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('changePassModal').style.display = 'block';
            document.getElementById('changePassModal').classList.remove('d-none');
        });
    });
    document.querySelectorAll('#changePassModal .btn-secondary').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('changePassModal').style.display = 'none';
            document.getElementById('changePassModal').classList.add('d-none');
        });
    });
    </script>
</body>

</html>