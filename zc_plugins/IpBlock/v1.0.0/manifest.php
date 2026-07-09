<?php
/**
 * IP Block - ip-block.com integration for Zen Cart.
 *
 * Encapsulated (zc_plugins) manifest, consumed by Admin -> Modules -> Plugin Manager.
 */
return [
    'pluginVersion'     => 'v1.0.0',
    'pluginName'        => 'IP Block',
    'pluginDescription' => 'Screens storefront visitors against the ip-block.com service and blocks disallowed IP addresses. Runs early via a notifier/observer, honours a whitelist, and never touches the admin.',
    'pluginAuthor'      => 'ip-block.com',
    'pluginId'          => 0, // ID from the Zen Cart forum (0 = not yet published)
    'zcVersions'        => ['v157', 'v158', 'v200', 'v210', 'v220'],
    'changelog'         => '',
    'github_repo'       => '',
    'pluginGroups'      => [],
];
