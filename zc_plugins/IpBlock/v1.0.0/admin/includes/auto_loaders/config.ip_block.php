<?php
/**
 * IP Block - admin autoloader registration.
 *
 * Runs the one-time self-installer that creates the "IP Block" configuration
 * group, its settings, and the admin menu entry. Runs early so the settings are
 * present the first time the operator opens the admin.
 *
 * Part of the "IP Block" plugin for Zen Cart (ip-block.com).
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$autoLoadConfig[1][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_ip_block_install.php',
];
