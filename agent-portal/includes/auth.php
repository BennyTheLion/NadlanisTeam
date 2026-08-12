<?php
require __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'agent' || empty($_SESSION['agent_id'])) {
    $current = $_SERVER['REQUEST_URI'] ?? url('agent-portal/index.php');
    header('Location: ' . url('agent-portal/login.php') . '?redirect=' . rawurlencode($current));
    exit;
}

$currentAgent = find_agent((int) $_SESSION['agent_id']);
$currentUser = find_user((int) $_SESSION['user_id']);
if (!$currentAgent || empty($currentAgent['active']) || !$currentUser || empty($currentUser['active'])) {
    unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name'], $_SESSION['agent_id']);
    header('Location: ' . url('agent-portal/login.php'));
    exit;
}

$agentId = (int) $currentAgent['id'];
