# IP Block — Zen Cart plugin (Zen Cart 2.2.2)

Screens storefront visitors against the **ip-block.com** service and blocks flagged
IP addresses. Built as a modern `zc_plugins` package using Zen Cart's notifier/observer
system.

## Install
1. Copy the `IpBlock` folder into your store's `zc_plugins/` directory
   (so you have `zc_plugins/IpBlock/v1.0.0/...`).
2. In **Admin → Plugin Manager**, install **IP Block**. (The config group also
   self-installs on the next admin page load; a manual `catalog/includes/install/ip_block.sql`
   is provided as an alternative.)
3. Configure under **Admin → Configuration → IP Block**.

## Settings (Admin → Configuration → IP Block)
| Key | Meaning |
|-----|---------|
| `IPBLOCK_ENABLED` | Master on/off (`true`/`false`) |
| `IPBLOCK_SITE_ID` | Your 12-character ip-block.com site identifier |
| `IPBLOCK_API_KEY` | Your 48-character API key (sent in the request body) |
| `IPBLOCK_API_URL` | Endpoint, default `https://api.ip-block.com/v1/check` |
| `IPBLOCK_FAIL_OPEN` | On error/timeout: allow (`true`) or block (`false`) |
| `IPBLOCK_CACHE_TTL` | Seconds to cache a decision (key = IP+UA+Referrer); `0` = every request |
| `IPBLOCK_BEHIND_PROXY` | Read real IP from CF-Connecting-IP / X-Forwarded-For |
| `IPBLOCK_BLOCK_ACTION` | `redirect` to ip-block.com, or `message` (HTTP 403) |
| `IPBLOCK_BLOCK_MESSAGE` | Text shown when Block Action = message |
| `IPBLOCK_WHITELIST` | Always-allowed IPs, one per line (skip the API entirely) |

## How it works
- A catalog-side observer watches an early page-load notifier and runs the check before
  the storefront renders. The admin is never screened (catalog observer only).
- Decisions are cached in the Zen Cart cache; the whitelist is honoured first; on any
  API error the configured fail mode applies (fail-open by default).
- The `Client` POSTs `{api_key, site_id, ip, user_agent, referrer}` to
  `/v1/check` (1-second timeout) and blocks only when the response is `{"action":"block"}`.

## Note
Allowlist your own IP before enabling so you cannot lock yourself out of the storefront.
