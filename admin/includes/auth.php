<?php
require __DIR__ . '/../../includes/config.php';

$settings = get_settings();

if (!admin_exists()) {
    header('Location: ' . url('admin/setup.php'));
    exit;
}

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $current = $_SERVER['REQUEST_URI'] ?? url('admin/index.php');
    header('Location: ' . url('admin/login.php') . '?redirect=' . rawurlencode($current));
    exit;
}
