<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // blocked_days tablosu yoksa oluştur
    $db->exec("
        CREATE TABLE IF NOT EXISTS blocked_days (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL UNIQUE
        );
    ");

    // appointments tablosu yoksa oluştur
    $db->exec("
        CREATE TABLE IF NOT EXISTS appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            appointment_date TEXT NOT NULL,
            appointment_time TEXT NOT NULL
        );
    ");

    // appointments tablosundaki mevcut sütunları kontrol et
    $columns = $db->query("PRAGMA table_info(appointments)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');

    if (!in_array('phone', $columnNames)) {
        $db->exec("ALTER TABLE appointments ADD COLUMN phone TEXT");
        echo "phone sütunu eklendi.<br>";
    }

    if (!in_array('cancel_token', $columnNames)) {
        $db->exec("ALTER TABLE appointments ADD COLUMN cancel_token TEXT");
        echo "cancel_token sütunu eklendi.<br>";
    }

    if (!in_array('status', $columnNames)) {
        $db->exec("ALTER TABLE appointments ADD COLUMN status TEXT DEFAULT 'pending'");
        echo "status sütunu eklendi.<br>";
    }

    // admins tablosu yoksa oluştur
    $db->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL
        );
    ");

    // İlk admini ekle
    $hash = password_hash('password', PASSWORD_DEFAULT);
    $db->exec("INSERT OR IGNORE INTO admins (username, password) VALUES ('admin', '$hash')");
    echo "Varsayılan admin eklendi.<br>";

    echo "Tüm tablolar hazır.<br>";
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
}