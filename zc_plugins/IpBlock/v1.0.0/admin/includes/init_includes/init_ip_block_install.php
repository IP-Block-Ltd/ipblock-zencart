<?php
/**
 * IP Block - one-time admin self-installer.
 *
 * Creates the "IP Block" configuration group and its settings and registers the
 * admin menu page. Idempotent: it does nothing once the group exists, so it is
 * safe to leave in place and safe to re-run.
 *
 * Part of the "IP Block" plugin for Zen Cart (ip-block.com).
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$check = $db->Execute(
    "SELECT configuration_group_id
       FROM " . TABLE_CONFIGURATION_GROUP . "
      WHERE configuration_group_title = 'IP Block'
      LIMIT 1"
);

if ($check->EOF) {
    // ----- Create the configuration group -----
    $db->Execute(
        "INSERT INTO " . TABLE_CONFIGURATION_GROUP . "
            (configuration_group_title, configuration_group_description, sort_order, visible)
         VALUES
            ('IP Block', 'ip-block.com IP screening settings', 900, 1)"
    );
    $cgId = (int)$db->insert_ID();

    // Make the group visible in the Configuration menu grouping.
    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION_GROUP . "
            SET sort_order = " . $cgId . "
          WHERE configuration_group_id = " . $cgId
    );

    // ----- Insert the settings -----
    $rows = [
        // key, title, value, description, sort, set_function, use_function
        ['IPBLOCK_ENABLED', 'Enable IP Block', 'false',
            'Screen storefront visitors through ip-block.com?', 1,
            "zen_cfg_select_option(array('true', 'false'), ", ''],
        ['IPBLOCK_SITE_ID', 'Site ID', '',
            'Your ip-block.com site identifier.', 2, '', ''],
        ['IPBLOCK_API_KEY', 'API Key', '',
            'Your ip-block.com API key (sent in the request body).', 3, '', ''],
        ['IPBLOCK_API_URL', 'API URL', 'https://api.ip-block.com/v1/check',
            'Screening endpoint. Change only if instructed by ip-block.com.', 4, '', ''],
        ['IPBLOCK_FAIL_OPEN', 'Fail Open', 'true',
            'On any error/timeout, allow the visitor (true) or block (false)?', 5,
            "zen_cfg_select_option(array('true', 'false'), ", ''],
        ['IPBLOCK_CACHE_TTL', 'Cache TTL (seconds)', '300',
            'How long to cache a decision, keyed by IP+User-Agent+Referrer. 0 = every request.', 6, '', ''],
        ['IPBLOCK_BEHIND_PROXY', 'Behind Proxy', 'false',
            'Determine the real client IP from CF-Connecting-IP / X-Forwarded-For headers?', 7,
            "zen_cfg_select_option(array('true', 'false'), ", ''],
        ['IPBLOCK_BLOCK_ACTION', 'Block Action', 'redirect',
            'Blocked visitor handling: redirect to the ip-block.com page, or show a message (HTTP 403).', 8,
            "zen_cfg_select_option(array('redirect', 'message'), ", ''],
        ['IPBLOCK_BLOCK_MESSAGE', 'Block Message', 'Access denied.',
            'Message shown when Block Action = message.', 9, '', ''],
        ['IPBLOCK_WHITELIST', 'IP Whitelist', '',
            'Always-allowed IP addresses, one per line. These skip the API call entirely.', 10,
            '', ''],
    ];

    foreach ($rows as $r) {
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_key, configuration_title, configuration_value, configuration_description,
                 configuration_group_id, sort_order, set_function, use_function, date_added)
             VALUES
                ('" . zen_db_input($r[0]) . "', '" . zen_db_input($r[1]) . "', '" . zen_db_input($r[2]) . "',
                 '" . zen_db_input($r[3]) . "', " . $cgId . ", " . (int)$r[4] . ",
                 '" . zen_db_input($r[5]) . "', '" . zen_db_input($r[6]) . "', NOW())"
        );
    }

    // Special-case the whitelist to render as a textarea.
    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION . "
            SET set_function = 'zen_cfg_textarea('
          WHERE configuration_key = 'IPBLOCK_WHITELIST'"
    );

    // ----- Register the admin menu page (Zen Cart 1.5.x / 2.x menu system) -----
    if (function_exists('zen_register_admin_page') && !zen_page_key_exists('configIpBlock')) {
        zen_register_admin_page(
            'configIpBlock',
            'BOX_CONFIGURATION_IP_BLOCK',
            'FILENAME_CONFIGURATION',
            'gID=' . $cgId,
            'configuration',
            'Y',
            $cgId
        );
    }
}
