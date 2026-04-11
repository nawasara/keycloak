<?php

namespace Nawasara\Keycloak\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Nawasara\Vault\Facades\Vault;

class KeycloakClient
{
    protected ?string $baseUrl = null;
    protected ?string $realm = null;

    protected function credentials(): array
    {
        return [
            'base_url' => $this->baseUrl ??= Vault::get('keycloak', 'base_url'),
            'realm' => $this->realm ??= Vault::get('keycloak', 'realm'),
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
