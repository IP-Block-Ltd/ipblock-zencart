<?php
/**
 * IP Block - ip-block.com integration for Zen Cart (storefront observer).
 *
 * Notifier/observer implementation. The observer attaches to NOTIFY_HEADER_START
 * (an early, every-storefront-page notifier) and screens the visitor against the
 * ip-block.com service before the page is rendered.
 *
 * Only the storefront (catalog) instantiates this observer, so the admin area is
 * inherently skipped and the operator can never be locked out. The whitelist is
 * always honoured and skips the API call.
 *
 * Shared API contract:
 *   POST {api_url}  body: {"api_key","site_id","ip","user_agent","referrer"}
 *   Response: {"action":"allow"|"block"}  -> block only when "block".
 *   1s timeout; any error/timeout/non-2xx/missing action => fail mode (default open).
 *   Decisions cached by md5(ip|user_agent|referrer).
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class zcObserverIpBlock extends base
{
    protected bool $enabled = false;
    protected array $cfg = [];

    public function __construct()
    {
        // Read settings from the configuration constants Zen Cart defines at start-up.
        $this->cfg = $this->loadConfig();

        if (($this->cfg['enabled'] ?? 'false') !== 'true') {
            return; // Not enabled: stay dormant, attach to nothing.
        }
        $this->enabled = true;

        // Earliest reliable, every-page storefront notifier.
        $this->attach($this, ['NOTIFY_HEADER_START']);
    }

    /**
     * Notifier callback. Signature is tolerant of Zen Cart's variable arity.
     */
    public function update(&$callingClass, $eventID, $paramsArray = [])
    {
        if (!$this->enabled) {
            return;
        }
        try {
            $this->guard();
        } catch (\Throwable $e) {
            // Fail safe: never break the storefront because of the screening layer.
        }
    }

    /* --------------------------------------------------------------------- */
    /* Screening                                                             */
    /* --------------------------------------------------------------------- */

    protected function guard(): void
    {
        $ip = $this->clientIp(($this->cfg['behind_proxy'] ?? 'false') === 'true');
        if ($ip === '' || $this->isWhitelisted($ip)) {
            return;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer  = $_SERVER['HTTP_REFERER'] ?? '';

        if ($this->decide($ip, $userAgent, $referrer) === 'block') {
            $this->block();
        }
    }

    /* --------------------------------------------------------------------- */
    /* Configuration                                                         */
    /* --------------------------------------------------------------------- */

    protected function loadConfig(): array
    {
        return [
            'enabled'       => defined('IPBLOCK_ENABLED') ? IPBLOCK_ENABLED : 'false',
            'site_id'       => defined('IPBLOCK_SITE_ID') ? IPBLOCK_SITE_ID : '',
            'api_key'       => defined('IPBLOCK_API_KEY') ? IPBLOCK_API_KEY : '',
            'api_url'       => defined('IPBLOCK_API_URL') ? IPBLOCK_API_URL : 'https://api.ip-block.com/v1/check',
            'fail_open'     => defined('IPBLOCK_FAIL_OPEN') ? IPBLOCK_FAIL_OPEN : 'true',
            'cache_ttl'     => defined('IPBLOCK_CACHE_TTL') ? IPBLOCK_CACHE_TTL : '300',
            'behind_proxy'  => defined('IPBLOCK_BEHIND_PROXY') ? IPBLOCK_BEHIND_PROXY : 'false',
            'block_action'  => defined('IPBLOCK_BLOCK_ACTION') ? IPBLOCK_BLOCK_ACTION : 'redirect',
            'block_message' => defined('IPBLOCK_BLOCK_MESSAGE') ? IPBLOCK_BLOCK_MESSAGE : 'Access denied.',
            'whitelist'     => defined('IPBLOCK_WHITELIST') ? IPBLOCK_WHITELIST : '',
        ];
    }

    /* --------------------------------------------------------------------- */
    /* Client IP / whitelist                                                 */
    /* --------------------------------------------------------------------- */

    protected function clientIp(bool $behindProxy): string
    {
        if ($behindProxy) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                return trim($parts[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    protected function isWhitelisted(string $ip): bool
    {
        $lines = preg_split('/\r\n|\r|\n/', (string)($this->cfg['whitelist'] ?? ''));
        foreach ($lines as $line) {
            if (trim($line) === $ip) {
                return true;
            }
        }
        return false;
    }

    /* --------------------------------------------------------------------- */
    /* Decision + cache (Checker)                                            */
    /* --------------------------------------------------------------------- */

    protected function decide(string $ip, string $userAgent, string $referrer): string
    {
        $ttl = (int)($this->cfg['cache_ttl'] ?? 0);
        $key = md5($ip . '|' . $userAgent . '|' . $referrer);

        if ($ttl > 0 && ($cached = $this->cacheGet($key, $ttl)) !== null) {
            return $cached;
        }

        $decision = $this->apiCheck($ip, $userAgent, $referrer);

        if ($ttl > 0) {
            $this->cachePut($key, $decision);
        }
        return $decision;
    }

    protected function cacheDir(): string
    {
        // Prefer Zen Cart's cache directory when available; fall back to temp.
        $base = defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG . 'cache' : sys_get_temp_dir();
        $dir  = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'ipblock';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return is_dir($dir) ? $dir : rtrim(sys_get_temp_dir(), '/\\');
    }

    protected function cacheGet(string $key, int $ttl): ?string
    {
        $file = $this->cacheDir() . DIRECTORY_SEPARATOR . $key;
        if (is_file($file) && (time() - filemtime($file)) < $ttl) {
            $v = trim((string)@file_get_contents($file));
            if ($v === 'allow' || $v === 'block') {
                return $v;
            }
        }
        return null;
    }

    protected function cachePut(string $key, string $decision): void
    {
        @file_put_contents($this->cacheDir() . DIRECTORY_SEPARATOR . $key, $decision, LOCK_EX);
    }

    /* --------------------------------------------------------------------- */
    /* API client (Client)                                                   */
    /* --------------------------------------------------------------------- */

    protected function apiCheck(string $ip, string $userAgent, string $referrer): string
    {
        $failDecision = (($this->cfg['fail_open'] ?? 'true') === 'true') ? 'allow' : 'block';

        $payload = json_encode([
            'api_key'    => (string)($this->cfg['api_key'] ?? ''),
            'site_id'    => (string)($this->cfg['site_id'] ?? ''),
            'ip'         => $ip,
            'user_agent' => $userAgent,
            'referrer'   => $referrer,
        ]);

        $url    = (string)($this->cfg['api_url'] ?? 'https://api.ip-block.com/v1/check');
        $body   = null;
        $status = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 1, // hard 1 second budget
                CURLOPT_CONNECTTIMEOUT => 1,
            ]);
            $body   = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\n",
                'content'       => $payload,
                'timeout'       => 1,
                'ignore_errors' => true,
            ]]);
            $body = @file_get_contents($url, false, $ctx);
            if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                $status = (int)$m[1];
            }
        }

        if ($body === false || $body === null || $status < 200 || $status >= 300) {
            return $failDecision;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['action'])) {
            return $failDecision;
        }

        return ($data['action'] === 'block') ? 'block' : 'allow';
    }

    /* --------------------------------------------------------------------- */
    /* Block response                                                        */
    /* --------------------------------------------------------------------- */

    protected function block(): void
    {
        if (($this->cfg['block_action'] ?? 'redirect') === 'message') {
            if (!headers_sent()) {
                header('HTTP/1.1 403 Forbidden');
                header('Content-Type: text/html; charset=utf-8');
            }
            echo (string)($this->cfg['block_message'] ?? 'Access denied.');
            exit;
        }

        $url = 'https://www.ip-block.com/blocked.php';
        if (!headers_sent()) {
            header('Location: ' . $url);
        } else {
            echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '">';
        }
        exit;
    }
}
