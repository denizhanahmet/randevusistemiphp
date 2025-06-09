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
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 30px;
        background: #f7f9fc;
        color: #333;
    }

    h1 {
        color: #2c3e50;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        background: white;
    }

    th,
    td {
        padding: 12px 15px;
        border-bottom: 1px solid #ddd;
        text-align: left;
    }

    th {
        background: #2980b9;
        color: white;
    }

    tr:hover {
        background-color: #f1faff;
    }

    button {
        padding: 6px 12px;
        margin: 0 4px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
    }

    .approve-btn {
        background-color: #27ae60;
        color: white;
    }

    .cancel-btn {
        background-color: #c0392b;
        color: white;
    }

    form {
        display: inline;
    }

    .message {
        padding: 10px 20px;
        margin-bottom: 20px;
        border-radius: 5px;
        font-weight: bold;
        color: white;
        background-color: #27ae60;
    }

    .message.error {
        background-color: #c0392b;
    }

    label,
    input {
        font-size: 16px;
    }

    input[type=date] {
        padding: 6px;
        border: 1px solid #ccc;
        border-radius: 4px;
        margin-right: 10px;
    }

    .blocked-days-list {
        list-style: none;
        padding-left: 0;
    }

    .blocked-days-list li {
        margin-bottom: 8px;
    }
    </style>
</head>

<body>

    <h1>Admin Paneli</h1>

    <?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <h2>Randevular</h2>
    <table>
        <tr>
            <th>İsim</th>
            <th>Email</th>
            <th>Telefon</th>
            <th>Tarih</th>
            <th>Saat</th>
            <th>Durum</th>
            <th>İşlemler</th>
        </tr>
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
                    echo '<span style="color: red; font-weight: bold;">Müşteri tarafından iptal edildi</span>';
                } elseif ($status === 'cancelled') {
                    echo '<span style="color: red; font-weight: bold;">Admin tarafından iptal edildi</span>';
                } else {
                    $colors = ['pending' => '#f39c12', 'approved' => '#27ae60'];
                    $color = $colors[$status] ?? '#7f8c8d';
                    echo '<span style="color: ' . $color . '; font-weight: bold;">' . ucfirst($status) . '</span>';
                }
                ?>
            </td>
            <td>
                <?php if ($status !== 'approved'): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                    <button type="submit" name="action" value="approve" class="approve-btn">Onayla</button>
                </form>
                <?php endif; ?>

                <?php if ($status !== 'cancelled' && $status !== 'customer_canceled'): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                    <button type="submit" name="action" value="cancel" class="cancel-btn">İptal Et</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p>
        <a href="logout.php">Çıkış Yap</a>
        <button type="button" onclick="document.getElementById('changePassModal').style.display='block'"
            style="margin-left:15px;">Şifre Değiştir</button>
    </p>

    <!-- Şifre Değiştir Modalı -->
    <div id="changePassModal"
        style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9999;">
        <div
            style="background:#fff; max-width:400px; margin:100px auto; padding:30px; border-radius:8px; position:relative;">
            <form method="post" action="change_password.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <h3>Şifre Değiştir</h3>
                <div style="margin-bottom:10px;">
                    <label>Mevcut Şifre</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div style="margin-bottom:10px;">
                    <label>Yeni Şifre</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div style="margin-bottom:10px;">
                    <label>Yeni Şifre (Tekrar)</label>
                    <input type="password" name="new_password2" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Kaydet</button>
                <button type="button" onclick="document.getElementById('changePassModal').style.display='none'"
                    class="btn btn-secondary">Vazgeç</button>
            </form>
        </div>
    </div>

    <h2>Bloklu Günler</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <label>Yeni Bloklu Gün: <input type="date" name="block_date" required></label>
        <button type="submit">Günü Blokla</button>
    </form>

    <ul class="blocked-days-list">
        <?php foreach ($blockedDays as $b): ?>
        <li>
            <?= htmlspecialchars($b['date']) ?>
            <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="remove_date" value="<?= $b['date'] ?>">
                <button type="submit">Sil</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>

</body>

</html>