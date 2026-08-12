<?php
require __DIR__ . '/../../includes/config.php';

$settings = get_settings();

if (empty($settings['admin_hash'])) {
    header('Location: ' . url('admin/setup.php'));
    exit;
}

if (empty($_SESSION['admin_logged_in'])) {
    $current = $_SERVER['REQUEST_URI'] ?? url('admin/index.php');
    header('Location: ' . url('admin/login.php') . '?redirect=' . rawurlencode($current));
    exit;
}
