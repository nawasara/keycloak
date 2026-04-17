<?php

namespace Nawasara\Keycloak\Livewire\User\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\Keycloak\Services\KeycloakClient;

class Table extends Component
{
    public string $search = '';
    public int $page = 0;
    public int $perPage = 20;

    // Detail modal
    public ?array $detailUser = null;
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

    #[Computed]
    public function users()
    {
        $params = [
            'first' => $this->page * $this->perPage,
            'max' => $this->perPage,
        ];

        if ($this->search) {
            $params['search'] = $this->search;
        }

        return $this->keycloak->getUsers($params);
    }

    #[Computed]
    public function userCount()
    {
        $params = $this->search ? ['search' => $this->search] : [];
        return $this->keycloak->getUserCount($params);
    }

    public function updatedSearch()
    {
        $this->page = 0;
    }

    public function previousPage()
    {
        $this->page = max(0, $this->page - 1);
        unset($this->users, $this->userCount);
    }

    public function nextPage()
    {
        $this->page++;
        unset($this->users, $this->userCount);
    }

    public function openDetail(string $userId)
    {
        $this->detailUser = $this->keycloak->getUser($userId);
        $this->detailSessions = $this->keycloak->getUserSessions($userId);
        $this->dispatch('modal-open:kc-user-detail');
    }

    public function closeDetail()
    {
        $this->dispatch('modal-close:kc-user-detail');
        $this->detailUser = null;
        $this->detailSessions = [];
    }

    public function toggleEnabled(string $userId, bool $currentlyEnabled)
    {
        Gate::authorize('keycloak.user.manage');

        if ($currentlyEnabled) {
            $this->keycloak->disableUser($userId);
            toaster_success('User berhasil di-disable');
        } else {
            $this->keycloak->enableUser($userId);
            toaster_success('User berhasil di-enable');
        }
        unset($this->users);
    }

    public function openResetPassword(string $userId, string $username)
    {
        Gate::authorize('keycloak.user.reset_password');

        $this->resetUserId = $userId;
        $this->resetUserName = $username;
        $this->newPassword = '';
        $this->temporary = true;
        $this->dispatch('modal-open:kc-reset-password');
    }

    public function doResetPassword()
    {
        Gate::authorize('keycloak.user.reset_password');

        $this->validate([
            'newPassword' => 'required|min:8',
        ]);

        $this->keycloak->resetPassword($this->resetUserId, $this->newPassword, $this->temporary);
        toaster_success("Password {$this->resetUserName} berhasil di-reset");
        $this->dispatch('modal-close:kc-reset-password');
    }

    public function logoutUser(string $userId, string $username)
    {
        Gate::authorize('keycloak.session.revoke');

        $this->keycloak->logoutUser($userId);
        toaster_success("Semua session {$username} berhasil di-logout");
        unset($this->users);
    }

    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.user.section.table');
    }
}
