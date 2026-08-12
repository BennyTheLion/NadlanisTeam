<?php
require __DIR__ . '/../includes/config.php';

unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name']);
session_regenerate_id(true);

header('Location: ' . url('admin/login.php'));
exit;
