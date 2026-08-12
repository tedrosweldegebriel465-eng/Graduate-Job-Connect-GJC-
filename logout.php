<?php
/**
 * Graduate Job Connect — Logout
 */

require_once __DIR__ . '/config/bootstrap.php';

session_unset();
session_destroy();

header('Location: ' . baseUrl('index.php'));
exit();
