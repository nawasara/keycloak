<?php

namespace Nawasara\Keycloak\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nawasara\Keycloak\Jobs\User\ResetKeycloakPasswordJob;
use Nawasara\Keycloak\Jobs\User\SyncKeycloakUsersJob;
use Nawasara\Keycloak\Jobs\User\ToggleKeycloakUserJob;
use Nawasara\Keycloak\Models\KeycloakUser;
use Nawasara\Sync\Concerns\TracksLastSync;
use Nawasara\Sync\Contracts\SyncedRepository;
use Nawasara\Sync\Models\SyncJob;

class KeycloakUserRepository implements SyncedRepository
{
    use TracksLastSync;

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('username')->paginate($perPage);
    }

    public function find(string|int $id): ?Model
    {
        if (is_numeric($id)) {
            return KeycloakUser::find($id);
        }
        return KeycloakUser::where('user_id', $id)->orWhere('username', $id)->first();
    }

    public function all(array $filters = []): Collection
    {
        return $this->query($filters)->orderBy('username')->get();
    }

    public function create(array $data): ?SyncJob
    {
        // Keycloak user creation typically managed via SSO provider — out of MVP scope
        throw new \BadMethodCallException('Creating Keycloak users via Nawasara is not supported.');
    }

    public function update(string|int $id, array $data): ?SyncJob
    {
        $user = $this->find($id);
        if (! $user) {
            throw new \InvalidArgumentException("User not found: {$id}");
        }

        $user->markPending(KeycloakUser::SYNC_PENDING_UPDATE);

        if (isset($data['enabled'])) {
            ToggleKeycloakUserJob::dispatch(
                payload: ['user_id' => $user->user_id, 'enabled' => (bool) $data['enabled']],
                expectedHash: $user->content_hash,
            );
        }

        if (isset($data['password'])) {
            ResetKeycloakPasswordJob::dispatch(
                payload: [
                    'user_id' => $user->user_id,
                    'password' => $data['password'],
                    'temporary' => $data['temporary'] ?? true,
                ],
                expectedHash: $user->content_hash,
            );
        }

        return SyncJob::query()
            ->where('service', 'keycloak')
            ->where('target_id', $user->user_id)
            ->latest('id')
            ->first();
    }

    public function delete(string|int $id): ?SyncJob
    {
        throw new \BadMethodCallException('Deleting Keycloak users via Nawasara is not supported.');
    }

    public function syncNow(): ?SyncJob
    {
        SyncKeycloakUsersJob::dispatch(triggerSource: 'manual');
        return SyncJob::query()
            ->where('service', 'keycloak')
            ->where('action', 'sync_users')
            ->latest('id')
            ->first();
    }

    public function lastSyncedAt(): ?Carbon
    {
        return $this->lastSuccessfulSyncAt('keycloak', 'sync_users');
    }

    /**
     * Aggregate stats untuk hero stats row di Users page.
     *
     * Single-pass query (selectRaw) supaya tidak perlu 4 round-trip ke DB.
     * Caller tetap bisa wrap di Cache kalau page di-poll heavy — tapi default
     * page user keycloak tidak di-poll, cuma sync manual, jadi cache ringan
     * di Livewire #[Computed] sudah cukup.
     *
     * Return shape:
     *   ['total' => int, 'enabled' => int, 'disabled' => int, 'totp' => int]
     */
    public function stats(): array
    {
        $row = KeycloakUser::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END) as enabled_count')
            ->selectRaw('SUM(CASE WHEN enabled = 0 THEN 1 ELSE 0 END) as disabled_count')
            ->selectRaw('SUM(CASE WHEN totp = 1 AND enabled = 1 THEN 1 ELSE 0 END) as totp_count')
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'enabled' => (int) ($row?->enabled_count ?? 0),
            'disabled' => (int) ($row?->disabled_count ?? 0),
            'totp' => (int) ($row?->totp_count ?? 0),
        ];
    }

    protected function query(array $filters = [])
    {
        return KeycloakUser::query()
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null);
    }
}
