<?php
function getDatabaseConnection() {
    $dbPath = __DIR__ . '/../../database.sqlite'; // Yolunu doğru yazmışsın
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}