<?php

namespace Nawasara\Keycloak\Livewire\Session\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;
use Nawasara\Keycloak\Services\KeycloakClient;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class Table extends Component
{
    use HasBrowserToast;
    use WithSudo;

    public string $search = '';

    protected KeycloakClient $keycloak;

    public function boot(KeycloakClient $keycloak)
    {
        $this->keycloak = $keycloak;
    }

    #[Computed]
    public function stats()
    {
        return $this->keycloak->getClientSessionStats();
    }

    #[Computed]
    public function activeSessions()
    {
        // Get all users with active sessions by fetching users and checking sessions
        // Keycloak doesn't have a "list all sessions" endpoint, so we use client-session-stats
        return $this->stats;
    }

    // Audit keamanan 15 Oktober 2026 (S6): wajib sudo (AGENTS.md §6).
    #[RequiresSudo(reason: 'mencabut sesi Keycloak')]
    public function deleteSession(string $sessionId)
    {
        Gate::authorize('keycloak.session.revoke');

        $this->keycloak->deleteSession($sessionId);
        $this->toastSuccess('Session berhasil di-revoke');
        unset($this->stats, $this->activeSessions);
    }

    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.session.section.table');
    }
}
