<?php

namespace Nawasara\Keycloak\Livewire\User\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;
use Nawasara\Keycloak\Models\KeycloakUser;
use Nawasara\Keycloak\Repositories\KeycloakUserRepository;
use Nawasara\Keycloak\Services\KeycloakClient;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;
use Nawasara\Ui\Livewire\Concerns\HasExport;

class Table extends Component
{
    use WithSudo;
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

    // Audit keamanan 15 Oktober 2026: id target hanya ditulis server lewat
    // openDetail()/openResetPassword(). Dikunci supaya payload wire tidak
    // dapat mengalihkan reset password ke user lain.

    // Detail modal
    #[Locked]
    public ?int $detailId = null;
    #[Locked]
    public array $detailSessions = [];

    // Reset password modal
    #[Locked]
    public string $resetUserId = '';
    #[Locked]
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
        // Audit keamanan 15 Oktober 2026 (R4): memanggil API Keycloak secara
        // langsung, jadi izin lihat diperiksa ulang.
        Gate::authorize('keycloak.user.view');

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

    // Sudo saat modal DIBUKA, bukan saat disimpan: step-up memakai redirect,
    // dan sandi yang sudah diketik akan hilang bila diminta di akhir. Mengganti
    // sandi orang lain sama dengan mengambil alih akunnya (audit keamanan
    // 15 Oktober 2026, S6).
    #[RequiresSudo(reason: 'mengganti sandi user Keycloak')]
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

    #[RequiresSudo(reason: 'mengganti sandi user Keycloak')]
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
        // Audit keamanan 15 Oktober 2026: export() dapat dipanggil langsung
        // lewat payload wire, jadi izin lihat diperiksa ulang di sini.
        Gate::authorize('keycloak.user.view');

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
