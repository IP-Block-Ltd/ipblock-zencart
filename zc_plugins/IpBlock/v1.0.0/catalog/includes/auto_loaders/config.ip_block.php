<?php
/**
 * IP Block - storefront autoloader registration.
 *
 * Loads and instantiates the observer early in the catalog page-load pipeline so
 * that it is attached and ready before the NOTIFY_HEADER_START notifier fires.
 *
 * Part of the "IP Block" plugin for Zen Cart (ip-block.com).
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

// Breakpoint 200: after the core/base classes are available, well before the
// header notifier is issued.
$autoLoadConfig[200][] = [
    'autoType' => 'class',
    'loadFile' => 'observers/class.ip_block_observer.php',
];
$autoLoadConfig[200][] = [
    'autoType'   => 'classInstantiate',
    'className'  => 'zcObserverIpBlock',
    'objectName' => 'zcObserverIpBlock',
];
