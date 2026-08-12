<?php
require __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['agent_logged_in']) || empty($_SESSION['agent_id'])) {
    $current = $_SERVER['REQUEST_URI'] ?? url('agent-portal/index.php');
    header('Location: ' . url('agent-portal/login.php') . '?redirect=' . rawurlencode($current));
    exit;
}

$currentAgent = find_agent((int) $_SESSION['agent_id']);
if (!$currentAgent || empty($currentAgent['active']) || empty($currentAgent['username'])) {
    unset($_SESSION['agent_logged_in'], $_SESSION['agent_id'], $_SESSION['agent_name']);
    header('Location: ' . url('agent-portal/login.php'));
    exit;
}

$agentId = (int) $currentAgent['id'];
