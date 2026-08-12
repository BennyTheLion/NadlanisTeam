<?php
require __DIR__ . '/../includes/config.php';

unset($_SESSION['admin_logged_in'], $_SESSION['admin_user']);
session_regenerate_id(true);

header('Location: ' . url('admin/login.php'));
exit;
