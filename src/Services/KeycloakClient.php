<?php

namespace Nawasara\Keycloak\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Nawasara\Vault\Facades\Vault;

/**
 * Client Keycloak Admin REST API.
 *
 * Catatan soal penyusunan URL: `base_url` dan `realm` datang dari Vault, diisi
 * manusia, jadi trailing slash pasti muncul cepat atau lambat. Sejak Keycloak 25
 * server MENOLAK path yang tidak ternormalisasi — `https://host//realms/...`
 * dijawab `{"error":"missingNormalization"}` alih-alih dirapikan sendiri seperti
 * dulu. Gejalanya menyesatkan: pesannya muncul sebagai kegagalan mengambil admin
 * token, seolah kredensialnya salah, padahal kredensialnya benar.
 *
 * Karena itu kedua nilai dirapikan sekali di credentials(), bukan di tiap
 * pemanggil. Jangan hapus rtrim/trim itu tanpa memindahkannya ke tempat lain.
 */
class KeycloakClient
{
    protected ?string $baseUrl = null;
    protected ?string $realm = null;

    protected function credentials(): array
    {
        return [
            // base_url dan realm dirapikan di sini, sekali, supaya setiap
            // penyusun URL di bawah tidak perlu mengulang kehati-hatian yang
            // sama. Lihat catatan di normalize().
            'base_url' => $this->baseUrl ??= rtrim((string) Vault::get('keycloak', 'base_url'), '/'),
            'realm' => $this->realm ??= trim((string) Vault::get('keycloak', 'realm'), '/'),
            'client_id' => Vault::get('keycloak', 'client_id'),
            'client_secret' => Vault::get('keycloak', 'client_secret'),
        ];
    }

    protected function getToken(): string
    {
        return Cache::remember('keycloak_admin_token', config('nawasara-keycloak.token_ttl', 55), function () {
            $creds = $this->credentials();

            $response = Http::asForm()->post(
                "{$creds['base_url']}/realms/{$creds['realm']}/protocol/openid-connect/token",
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $creds['client_id'],
                    'client_secret' => $creds['client_secret'],
                ]
            );

            if ($response->failed()) {
                throw new \RuntimeException('Failed to obtain Keycloak admin token: '.$response->body());
            }

            return $response->json('access_token');
        });
    }

    protected function adminUrl(string $path = ''): string
    {
        $creds = $this->credentials();
        return "{$creds['base_url']}/admin/realms/{$creds['realm']}{$path}";
    }

    protected function api()
    {
        return Http::withToken($this->getToken())
            ->acceptJson()
            ->timeout(15);
    }

    public function isConfigured(): bool
    {
        return Vault::has('keycloak', 'base_url')
            && Vault::has('keycloak', 'client_id')
            && Vault::has('keycloak', 'client_secret');
    }

    /**
     * Dipanggil dari Vault credential list — tombol "Test Connection".
     * Coba grab admin token (client_credentials flow). Sukses = realm
     * reachable + client_id/secret valid + service account ke-grant
     * proper roles untuk admin API.
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Field Keycloak belum lengkap di Vault.'];
        }

        try {
            // Bypass cache supaya test selalu hit beneran ke Keycloak.
            Cache::forget('keycloak_admin_token');
            $token = $this->getToken();
            if (! $token) {
                return ['success' => false, 'message' => 'Token kosong dari Keycloak.'];
            }

            // Token didapat → coba call admin endpoint sederhana
            $response = $this->api()->get($this->adminUrl('/users/count'));
            if (! $response->successful()) {
                return ['success' => false, 'message' => 'Token valid tapi admin API ditolak: '.($response->json('error_description') ?? $response->body())];
            }

            $count = (int) $response->body();
            return ['success' => true, 'message' => "Connect ke Keycloak berhasil. Realm punya {$count} user."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Users ──────────────────────────────────────────

    public function getUsers(array $params = []): array
    {
        $defaults = [
            'first' => 0,
            'max' => config('nawasara-keycloak.users_per_page', 20),
        ];

        $response = $this->api()->get($this->adminUrl('/users'), array_merge($defaults, $params));

        return $response->successful() ? $response->json() : [];
    }

    public function getUserCount(array $params = []): int
    {
        $response = $this->api()->get($this->adminUrl('/users/count'), $params);

        return $response->successful() ? (int) $response->body() : 0;
    }

    public function getUser(string $id): ?array
    {
        $response = $this->api()->get($this->adminUrl("/users/{$id}"));

        return $response->successful() ? $response->json() : null;
    }

    public function enableUser(string $id): bool
    {
        return $this->api()->put($this->adminUrl("/users/{$id}"), ['enabled' => true])->successful();
    }

    public function disableUser(string $id): bool
    {
        return $this->api()->put($this->adminUrl("/users/{$id}"), ['enabled' => false])->successful();
    }

    public function resetPassword(string $id, string $password, bool $temporary = true): bool
    {
        return $this->api()->put($this->adminUrl("/users/{$id}/reset-password"), [
            'type' => 'password',
            'value' => $password,
            'temporary' => $temporary,
        ])->successful();
    }

    /**
     * Find a Keycloak user by exact username match.
     *
     * Returns the user representation array (with `id`, `attributes`, etc.)
     * or null if no exact match. Note: Keycloak's `/users` endpoint with
     * `username=foo` does a substring-or-exact lookup; we add `exact=true`
     * and then verify the username equals ours, because some Keycloak
     * versions still return partial matches even with the flag.
     */
    public function findUserByUsername(string $username): ?array
    {
        $users = $this->getUsers([
            'username' => $username,
            'exact' => 'true',
            'max' => 5,
        ]);

        foreach ($users as $u) {
            if (($u['username'] ?? null) === $username) {
                return $u;
            }
        }

        return null;
    }

    /**
     * Update arbitrary fields on a Keycloak user via PUT /users/{id}.
     *
     * Keycloak's update endpoint is full-replace on top-level fields, so
     * callers must pass through the full user representation. For attribute
     * merges (the common case), use {@see setUserAttribute()} which fetches
     * the existing user first, merges, then PUTs.
     */
    public function updateUser(string $id, array $data): bool
    {
        return $this->api()->put($this->adminUrl("/users/{$id}"), $data)->successful();
    }

    /**
     * Merge a single attribute into a user's `attributes` map.
     *
     * Keycloak stores user attributes as `attributes: {key: [value]}` — each
     * value is an array (multi-valued). This helper keeps the existing
     * attributes intact, sets/overrides just the named key, and PUTs the
     * full payload back. Pass null as value to remove the attribute.
     *
     * Examples:
     *   $client->setUserAttribute($id, 'kominfo_email', 'pringgo@kominfo.go.id');
     *   $client->setUserAttribute($id, 'kominfo_email', null); // remove
     *
     * Returns false if the user doesn't exist or the PUT failed.
     */
    public function setUserAttribute(string $id, string $key, ?string $value): bool
    {
        $user = $this->getUser($id);
        if (! $user) {
            return false;
        }

        $attributes = $user['attributes'] ?? [];

        if ($value === null) {
            unset($attributes[$key]);
        } else {
            // Keycloak expects array values for attributes (multi-valued
            // semantics). Single-string values are wrapped in a one-item array.
            $attributes[$key] = [$value];
        }

        return $this->updateUser($id, ['attributes' => $attributes]);
    }

    public function getUserGroups(string $id): array
    {
        $response = $this->api()->get($this->adminUrl("/users/{$id}/groups"));

        return $response->successful() ? $response->json() : [];
    }

    public function getUserRoleMappings(string $id): array
    {
        $response = $this->api()->get($this->adminUrl("/users/{$id}/role-mappings"));

        return $response->successful() ? $response->json() : [];
    }

    // ─── Sessions ───────────────────────────────────────

    public function getClientSessionStats(): array
    {
        $response = $this->api()->get($this->adminUrl('/client-session-stats'));

        return $response->successful() ? $response->json() : [];
    }

    public function getUserSessions(string $userId): array
    {
        $response = $this->api()->get($this->adminUrl("/users/{$userId}/sessions"));

        return $response->successful() ? $response->json() : [];
    }

    public function logoutUser(string $userId): bool
    {
        return $this->api()->post($this->adminUrl("/users/{$userId}/logout"))->successful();
    }

    public function deleteSession(string $sessionId): bool
    {
        return $this->api()->delete($this->adminUrl("/sessions/{$sessionId}"))->successful();
    }

    // ─── Clients ────────────────────────────────────────

    public function getClients(array $params = []): array
    {
        $response = $this->api()->get($this->adminUrl('/clients'), $params);

        return $response->successful() ? $response->json() : [];
    }

    public function getClient(string $id): ?array
    {
        $response = $this->api()->get($this->adminUrl("/clients/{$id}"));

        return $response->successful() ? $response->json() : null;
    }

    public function getClientSecret(string $id): ?string
    {
        $response = $this->api()->get($this->adminUrl("/clients/{$id}/client-secret"));

        return $response->successful() ? $response->json('value') : null;
    }

    public function getClientRoles(string $id): array
    {
        $response = $this->api()->get($this->adminUrl("/clients/{$id}/roles"));

        return $response->successful() ? $response->json() : [];
    }

    public function getClientSessions(string $id): array
    {
        $response = $this->api()->get($this->adminUrl("/clients/{$id}/user-sessions"));

        return $response->successful() ? $response->json() : [];
    }

    public function createClient(array $data): bool
    {
        return $this->api()->post($this->adminUrl('/clients'), $data)->successful();
    }

    public function updateClient(string $id, array $data): bool
    {
        return $this->api()->put($this->adminUrl("/clients/{$id}"), $data)->successful();
    }

    public function deleteClient(string $id): bool
    {
        return $this->api()->delete($this->adminUrl("/clients/{$id}"))->successful();
    }

    public function regenerateClientSecret(string $id): ?string
    {
        $response = $this->api()->post($this->adminUrl("/clients/{$id}/client-secret"));

        return $response->successful() ? $response->json('value') : null;
    }

    public function enableClient(string $id): bool
    {
        return $this->api()->put($this->adminUrl("/clients/{$id}"), ['enabled' => true])->successful();
    }

    public function disableClient(string $id): bool
    {
        return $this->api()->put($this->adminUrl("/clients/{$id}"), ['enabled' => false])->successful();
    }

    // ─── Events ─────────────────────────────────────────

    public function getEvents(array $params = []): array
    {
        $defaults = [
            'first' => 0,
            'max' => config('nawasara-keycloak.events_per_page', 25),
        ];

        $response = $this->api()->get($this->adminUrl('/events'), array_merge($defaults, $params));

        return $response->successful() ? $response->json() : [];
    }

    public function getAdminEvents(array $params = []): array
    {
        $defaults = [
            'first' => 0,
            'max' => config('nawasara-keycloak.events_per_page', 25),
        ];

        $response = $this->api()->get($this->adminUrl('/admin-events'), array_merge($defaults, $params));

        return $response->successful() ? $response->json() : [];
    }
}
