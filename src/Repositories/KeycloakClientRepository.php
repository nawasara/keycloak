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
