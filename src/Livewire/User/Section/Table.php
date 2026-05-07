<?php

namespace Nawasara\Keycloak\Livewire\User\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Keycloak\Models\KeycloakUser;
use Nawasara\Keycloak\Repositories\KeycloakUserRepository;
use Nawasara\Keycloak\Services\KeycloakClient;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;
use Nawasara\Ui\Livewire\Concerns\HasExport;

class Table extends Component
{
    use HasBrowserToast;
    use HasExport;
    use WithPagination;

    public string $search = '';

    /**
     * Multi-select status filter. Empty array == no filter; both
     * 'enabled' and 'disabled' selected also no-ops via the model scope.
     *
     * @var array<int, string>
     */
    public array $statusFilter = [];

    public int $perPage = 25;

    // Detail modal
    public ?int $detailId = null;
    public array $detailSessions = [];

    // Reset password modal
    public string $resetUserId = '';
    public string $resetUserName = '';
    public string $newPassword = '';
    public bool $temporary = true;

    protected KeycloakClient $keycloak;

    public function boot(KeycloakClient $keycloak)
    {
        $this->keycloak = $keycloak;
    }

    protected function repo(): KeycloakUserRepository
    {
        return new KeycloakUserRepository();
    }

    #[Computed]
    public function users()
    {
        return $this->repo()->list([
            'search' => $this->search ?: null,
            // Empty array passes through; the model scope is no-op on empty.
            'status' => $this->statusFilter,
        ], $this->perPage);
    }

    #[Computed]
    public function lastSyncedAt(): ?string
    {
        $when = $this->repo()->lastSyncedAt();
        return $when ? $when->diffForHumans() : null;
    }

    #[Computed]
    public function detail(): ?KeycloakUser
    {
        return $this->detailId ? KeycloakUser::find($this->detailId) : null;
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function refreshUsers(): void
    {
        Gate::authorize('keycloak.user.view');
        $this->repo()->syncNow();
        $this->toastSuccess('Sync dispatched. Refresh dalam beberapa detik.');
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $user = KeycloakUser::find($id);
        // Sessions adalah live data, tidak di-cache di DB
        $this->detailSessions = $user ? $this->keycloak->getUserSessions($user->user_id) : [];
        $this->dispatch('modal-open:kc-user-detail');
    }

    public function closeDetail(): void
    {
        $this->dispatch('modal-close:kc-user-detail');
        $this->detailId = null;
        $this->detailSessions = [];
    }

    public function toggleEnabled(int $id): void
    {
        Gate::authorize('keycloak.user.manage');

        $user = KeycloakUser::find($id);
        if (! $user) return;

        try {
            $this->repo()->update($id, ['enabled' => ! $user->enabled]);
            $this->toastSuccess($user->enabled ? "User {$user->username} sedang di-disable" : "User {$user->username} sedang di-enable");
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    public function openResetPassword(int $id): void
    {
        Gate::authorize('keycloak.user.reset_password');

        $user = KeycloakUser::find($id);
        if (! $user) return;

        $this->resetUserId = (string) $id;
        $this->resetUserName = $user->username;
        $this->newPassword = '';
        $this->temporary = true;
        $this->dispatch('modal-open:kc-reset-password');
    }

    public function doResetPassword(): void
    {
        Gate::authorize('keycloak.user.reset_password');

        $this->validate(['newPassword' => 'required|min:8']);

        try {
            $this->repo()->update((int) $this->resetUserId, [
                'password' => $this->newPassword,
                'temporary' => $this->temporary,
            ]);
            $this->toastSuccess("Password {$this->resetUserName} sedang di-reset");
            $this->dispatch('modal-close:kc-reset-password');
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    public function logoutUser(int $id): void
    {
        Gate::authorize('keycloak.session.revoke');

        $user = KeycloakUser::find($id);
        if (! $user) return;

        // Logout session adalah action live (tidak ada queue job, low risk)
        $this->keycloak->logoutUser($user->user_id);
        $this->toastSuccess("Semua session {$user->username} berhasil di-logout");
    }

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'keycloak-users';
    }

    /**
     * Export FULL Keycloak user dataset (no filter) per spec. Includes
     * core profile + auth flags so the spreadsheet can be used for
     * compliance audits without re-querying.
     */
    protected function exportData(): iterable
    {
        return KeycloakUser::query()
            ->orderBy('username')
            ->get()
            ->map(fn (KeycloakUser $u) => [
                'Username' => $u->username,
                'Email' => $u->email,
                'First Name' => $u->first_name,
                'Last Name' => $u->last_name,
                'Enabled' => $u->enabled ? 'Yes' : 'No',
                'Email Verified' => $u->email_verified ? 'Yes' : 'No',
                'TOTP' => $u->totp ? 'Yes' : 'No',
                'KC Created' => optional($u->kc_created_at)->format('Y-m-d H:i'),
                'Last Synced' => optional($u->last_synced_at)->format('Y-m-d H:i'),
            ]);
    }

    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.user.section.table');
    }
}
