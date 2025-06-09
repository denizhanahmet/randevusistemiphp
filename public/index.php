<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/controllers/UserController.php';

$db = getDatabaseConnection();
$controller = new UserController($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->handleRequest();
} elseif (isset($_GET['token'])) {
    // GET ile token geldiyse iptal işlemini çalıştır
    $controller->handleCancel($_GET['token']);
} else {
    $controller->showForm();
}