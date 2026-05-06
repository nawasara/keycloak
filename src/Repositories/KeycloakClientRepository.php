<?php

namespace Nawasara\Keycloak\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nawasara\Keycloak\Jobs\Client\CreateKeycloakClientJob;
use Nawasara\Keycloak\Jobs\Client\DeleteKeycloakClientJob;
use Nawasara\Keycloak\Jobs\Client\SyncKeycloakClientsJob;
use Nawasara\Keycloak\Jobs\Client\UpdateKeycloakClientJob;
use Nawasara\Keycloak\Models\KeycloakClient;
use Nawasara\Sync\Concerns\TracksLastSync;
use Nawasara\Sync\Contracts\SyncedRepository;
use Nawasara\Sync\Models\SyncJob;

class KeycloakClientRepository implements SyncedRepository
{
    use TracksLastSync;

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('client_id')->paginate($perPage);
    }

    public function find(string|int $id): ?Model
    {
        if (is_numeric($id)) {
            return KeycloakClient::find($id);
        }
        return KeycloakClient::where('client_uuid', $id)->orWhere('client_id', $id)->first();
    }

    public function all(array $filters = []): Collection
    {
        return $this->query($filters)->orderBy('client_id')->get();
    }

    public function create(array $data): ?SyncJob
    {
        CreateKeycloakClientJob::dispatch(payload: $data);
        return SyncJob::query()
            ->where('service', 'keycloak')
            ->where('action', 'client_create')
            ->latest('id')
            ->first();
    }

    public function update(string|int $id, array $data): ?SyncJob
    {
        $client = $this->find($id);
        if (! $client) {
            throw new \InvalidArgumentException("Client not found: {$id}");
        }

        $client->markPending(KeycloakClient::SYNC_PENDING_UPDATE);

        UpdateKeycloakClientJob::dispatch(
            payload: array_merge(['client_uuid' => $client->client_uuid], $data),
            expectedHash: $client->content_hash,
        );

        return SyncJob::query()
            ->where('service', 'keycloak')
            ->where('action', 'client_update')
            ->where('target_id', $client->client_uuid)
            ->latest('id')
            ->first();
    }

    public function delete(string|int $id): ?SyncJob
    {
        $client = $this->find($id);
        if (! $client) {
            throw new \InvalidArgumentException("Client not found: {$id}");
        }

        $client->markPending(KeycloakClient::SYNC_PENDING_DELETE);

        DeleteKeycloakClientJob::dispatch(
            payload: ['client_uuid' => $client->client_uuid],
            expectedHash: $client->content_hash,
        );

        return SyncJob::query()
            ->where('service', 'keycloak')
            ->where('action', 'client_delete')
            ->where('target_id', $client->client_uuid)
            ->latest('id')
            ->first();
    }

    public function syncNow(): ?SyncJob
    {
        SyncKeycloakClientsJob::dispatch(triggerSource: 'manual');
        return SyncJob::query()
            ->where('service', 'keycloak')
            ->where('action', 'sync_clients')
            ->latest('id')
            ->first();
    }

    public function lastSyncedAt(): ?Carbon
    {
        return $this->lastSuccessfulSyncAt('keycloak', 'sync_clients');
    }

    /**
     * Aggregate stats untuk hero stats row di Clients page.
     *
     * Hitung breakdown per tipe client yang relevan untuk admin:
     * - total: semua client terdaftar
     * - enabled: client aktif (bisa terima request)
     * - public: client SPA/native (tanpa client_secret) — penting untuk
     *   visibility security posture, public client lebih sensitif
     * - service_account: client dengan service-account-flow (machine-to-machine)
     *
     * Return shape:
     *   ['total' => int, 'enabled' => int, 'public' => int, 'service_account' => int]
     */
    public function stats(): array
    {
        $row = KeycloakClient::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END) as enabled_count')
            ->selectRaw('SUM(CASE WHEN public_client = 1 THEN 1 ELSE 0 END) as public_count')
            ->selectRaw('SUM(CASE WHEN service_accounts_enabled = 1 THEN 1 ELSE 0 END) as sa_count')
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'enabled' => (int) ($row?->enabled_count ?? 0),
            'public' => (int) ($row?->public_count ?? 0),
            'service_account' => (int) ($row?->sa_count ?? 0),
        ];
    }

    protected function query(array $filters = [])
    {
        $q = KeycloakClient::query()
            ->search($filters['search'] ?? null);

        if (isset($filters['enabled'])) {
            $q->where('enabled', $filters['enabled']);
        }

        return $q;
    }
}
