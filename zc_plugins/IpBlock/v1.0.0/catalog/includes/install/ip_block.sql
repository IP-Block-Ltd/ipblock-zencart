-- =====================================================================
-- IP Block - ip-block.com integration for Zen Cart
-- Manual install alternative to the admin self-installer.
-- The plugin normally self-installs on first admin page load; use this file
-- only if you prefer to create the configuration group by hand.
-- =====================================================================

INSERT INTO configuration_group
    (configuration_group_title, configuration_group_description, sort_order, visible)
VALUES
    ('IP Block', 'ip-block.com IP screening settings', 900, 1);

SET @cg := LAST_INSERT_ID();
UPDATE configuration_group SET sort_order = @cg WHERE configuration_group_id = @cg;

INSERT INTO configuration
    (configuration_key, configuration_title, configuration_value, configuration_description,
     configuration_group_id, sort_order, set_function, date_added)
VALUES
    ('IPBLOCK_ENABLED', 'Enable IP Block', 'false',
     'Screen storefront visitors through ip-block.com?',
     @cg, 1, 'zen_cfg_select_option(array(\'true\', \'false\'), ', NOW()),

    ('IPBLOCK_SITE_ID', 'Site ID', '',
     'Your ip-block.com site identifier.', @cg, 2, NULL, NOW()),

    ('IPBLOCK_API_KEY', 'API Key', '',
     'Your ip-block.com API key (sent in the request body).', @cg, 3, NULL, NOW()),

    ('IPBLOCK_API_URL', 'API URL', 'https://api.ip-block.com/v1/check',
     'Screening endpoint. Change only if instructed by ip-block.com.', @cg, 4, NULL, NOW()),

    ('IPBLOCK_FAIL_OPEN', 'Fail Open', 'true',
     'On any error/timeout, allow the visitor (true) or block (false)?',
     @cg, 5, 'zen_cfg_select_option(array(\'true\', \'false\'), ', NOW()),

    ('IPBLOCK_CACHE_TTL', 'Cache TTL (seconds)', '300',
     'How long to cache a decision, keyed by IP+User-Agent+Referrer. 0 = every request.',
     @cg, 6, NULL, NOW()),

    ('IPBLOCK_BEHIND_PROXY', 'Behind Proxy', 'false',
     'Determine the real client IP from CF-Connecting-IP / X-Forwarded-For headers?',
     @cg, 7, 'zen_cfg_select_option(array(\'true\', \'false\'), ', NOW()),

    ('IPBLOCK_BLOCK_ACTION', 'Block Action', 'redirect',
     'Blocked visitor handling: redirect to the ip-block.com page, or show a message (HTTP 403).',
     @cg, 8, 'zen_cfg_select_option(array(\'redirect\', \'message\'), ', NOW()),

    ('IPBLOCK_BLOCK_MESSAGE', 'Block Message', 'Access denied.',
     'Message shown when Block Action = message.', @cg, 9, NULL, NOW()),

    ('IPBLOCK_WHITELIST', 'IP Whitelist', '',
     'Always-allowed IP addresses, one per line. These skip the API call entirely.',
     @cg, 10, 'zen_cfg_textarea(', NOW());
