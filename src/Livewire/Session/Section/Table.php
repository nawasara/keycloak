<?php

namespace Nawasara\Keycloak\Livewire\Session\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\Keycloak\Services\KeycloakClient;

class Table extends Component
{
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

    public function deleteSession(string $sessionId)
    {
        $this->keycloak->deleteSession($sessionId);
        toaster_success('Session berhasil di-revoke');
        unset($this->stats, $this->activeSessions);
    }

    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.session.section.table');
    }
}
