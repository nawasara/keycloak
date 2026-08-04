# Nawasara Keycloak

Keycloak SSO admin dashboard for the Nawasara superapp framework. Manage users and clients without leaving Nawasara, backed by local DB snapshots for speed and queue jobs for write auditability.

## Features

- **Users** — list, search, view detail (sessions, roles, attributes), enable/disable, and reset password
- **Clients** — list, search, create, edit, and delete client applications; reveal and regenerate client secret on demand
- **DB-cached + queue pattern** — list pages read from `nawasara_keycloak_users` / `nawasara_keycloak_clients` snapshots; mutations dispatch through queue jobs that update Keycloak and the local snapshot atomically with content-hash conflict detection
- **Sync info bar** — shows last successful sync time, pending mutations, and a link to the audit log
- **Test connection** — Vault credential page exposes a one-click test that obtains an admin token and queries `/users/count` to confirm realm reachability and admin-API access
- **Staff directory API** — read-only endpoints so other applications can look people up instead of asking users to retype their own details

## Staff directory API

Requires [`nawasara/api`](../nawasara-api). When that package is absent the routes are simply not mounted; nothing else in this package changes.

Served from the local `nawasara_keycloak_users` snapshot, not from Keycloak itself — the realm stays out of the request path, so a slow or unreachable auth server cannot take consumers down with it. The snapshot refreshes hourly, so callers needing second-fresh data should query Keycloak directly.

**Read-only by design.** Enabling, disabling, and password resets stay in the Nawasara UI, where they are audit-logged and sudo-gated.

### Scope

| Scope | Grants |
|---|---|
| `keycloak.user.read` | Search and read staff directory entries |

Assign it to a token under **Pengaturan → API Token**. A token without it gets `403 insufficient_scope`.

### Endpoints

All paths are prefixed `/api/v1/keycloak`. Authenticate with `Authorization: Bearer nws_…` or `X-API-Key: nws_…`.

| Method | Path | Notes |
|---|---|---|
| GET | `/users` | `q`, `status` (`enabled` default \| `disabled` \| `all`), `per_page` (1–100, default 50) |
| GET | `/users/{id}` | `id` is the Keycloak UUID returned as `id` by the list endpoint |
| GET | `/users/by-username/{username}` | For callers holding a username (NIP) but no UUID |

```bash
curl -H "Authorization: Bearer nws_xxx" \
  "https://nawasara.ponorogo.go.id/api/v1/keycloak/users?q=budi&per_page=10"
```

```json
{
  "data": [
    {
      "id": "8b085300-dc0d-497a-9f4a-47a6de80444f",
      "username": "198106102003122002",
      "name": "DIAN SULISTYOWATI WATIK SH",
      "first_name": "DIAN",
      "last_name": "SULISTYOWATI WATIK SH",
      "nip": "198106102003122002",
      "email": "diansulistyowati681@gmail.com",
      "email_verified": false,
      "enabled": true,
      "created_at": "2025-10-09T03:30:09+00:00"
    }
  ],
  "meta": { "total": 1, "per_page": 10, "current_page": 1, "last_page": 1 }
}
```

Store `id` rather than `username` when linking a person across systems: usernames get renamed, the UUID does not.

### What the API never returns

The response is an allow-list, not a filtered dump. Deliberately excluded:

- **`whatsapp_number`** — a personal number; no consumer has needed it, and it cannot be recalled once released
- **`attributes`** (the raw blob) — a free-form bag whose contents can grow through Keycloak configuration alone; exposing it means exposing whatever gets added later, without anyone deciding to
- **`required_actions`, `totp`, sessions** — account-security state; leaking it hands over a map of who has yet to enable 2FA

Widening this list is a deliberate decision, not a convenience: edit `KeycloakUserResource` and say why in the same commit.

### Restrict tokens by IP

Directory data is more sensitive than the camera or WiFi endpoints this API pattern was first built for. Set an **IP allow-list** on any token carrying `keycloak.user.read`, so a leaked token is useless off your network.

## Installation

```bash
composer require nawasara/keycloak
php artisan migrate
php artisan db:seed --class="Nawasara\Keycloak\Database\Seeders\PermissionSeeder" --force
```

Auto-discovered by Laravel.

## Keycloak setup

The package authenticates to Keycloak via the **client credentials** flow with a confidential client that has admin privileges on the target realm.

1. In the Keycloak admin console, open the realm you want to manage.
2. **Clients → Create client**:
   - Client type: `OpenID Connect`
   - Client ID: e.g. `nawasara-admin`
   - Client authentication: `On`
   - Authentication flow: tick `Service accounts roles` only
3. After creation, open the new client → **Service Account Roles** tab → Assign role:
   - `realm-management` → grant `manage-users`, `manage-clients`, `view-users`, `view-clients`, `view-realm`, `view-events` (and any others you need)
4. Open the **Credentials** tab and copy the client secret.

## Storing credentials in Vault

1. Open Nawasara → `/nawasara-vault`
2. Select the **Keycloak SSO** group
3. Fill in:
   - **Base URL** — e.g. `https://sso.kominfo.go.id`
   - **Realm** — the realm you grant admin access to (e.g. `master`, `kominfo`)
   - **Client ID** — from step 2 above
   - **Client Secret** — from step 4 above
4. Save

Click **Test Connection** in the credential dropdown to verify. A successful test reports the realm's user count.

## Pages

| Route | Permission |
|-------|-----------|
| `/nawasara-keycloak/users` | `keycloak.user.view` |
| `/nawasara-keycloak/clients` | `keycloak.client.view` |
| `/nawasara-keycloak/sessions` | `keycloak.session.view` |

## Permissions

| Permission | Description |
|---|---|
| `keycloak.user.view` | View user list and detail |
| `keycloak.user.manage` | Toggle enable/disable, reset password |
| `keycloak.user.reset_password` | Reset a user's password |
| `keycloak.client.view` | View client list and detail |
| `keycloak.client.manage` | Create / edit / delete client, regenerate secret |
| `keycloak.client.reveal_secret` | Reveal client secret |
| `keycloak.session.view` | View active sessions |
| `keycloak.session.revoke` | Revoke a session |

## Author

**Pringgo J. Saputro** &lt;odyinggo@gmail.com&gt;

## License

MIT
