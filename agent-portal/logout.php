<?php
require __DIR__ . '/../includes/config.php';

unset($_SESSION['agent_logged_in'], $_SESSION['agent_id'], $_SESSION['agent_name']);
session_regenerate_id(true);

header('Location: ' . url('agent-portal/login.php'));
exit;
