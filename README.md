# Nawasara Keycloak

Keycloak SSO admin dashboard for the Nawasara superapp framework. Manage users and clients without leaving Nawasara, backed by local DB snapshots for speed and queue jobs for write auditability.

## Features

- **Users** — list, search, view detail (sessions, roles, attributes), enable/disable, and reset password
- **Clients** — list, search, create, edit, and delete client applications; reveal and regenerate client secret on demand
- **DB-cached + queue pattern** — list pages read from `nawasara_keycloak_users` / `nawasara_keycloak_clients` snapshots; mutations dispatch through queue jobs that update Keycloak and the local snapshot atomically with content-hash conflict detection
- **Sync info bar** — shows last successful sync time, pending mutations, and a link to the audit log
- **Test connection** — Vault credential page exposes a one-click test that obtains an admin token and queries `/users/count` to confirm realm reachability and admin-API access

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
