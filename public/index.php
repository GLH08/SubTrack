<?php
require_once __DIR__ . '/../includes/checksession.php';

requireLogin();

// Redirect to dashboard
header('Location: dashboard.php');
exit;
