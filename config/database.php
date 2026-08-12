<?php
/**
 * Graduate Job Connect — config/database.php
 *
 * COMPATIBILITY SHIM — delegates to bootstrap.php.
 * Old pages that do `require_once '../config/database.php'` will still work
 * because bootstrap.php defines getDBConnection() and all auth helpers.
 *
 * New pages should load bootstrap.php directly instead.
 */

if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/bootstrap.php';
}
