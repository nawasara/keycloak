<?php

namespace Nawasara\Keycloak\Livewire\Client\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\Keycloak\Services\KeycloakClient;

class Table extends Component
{
    public string $search = '';

    // Detail modal
    public bool $showDetail = false;
    public ?array $detailClient = null;
    public array $detailRoles = [];
    public array $detailSessions = [];
    public ?string $detailSecret = null;
    public bool $secretRevealed = false;

    protected KeycloakClient $keycloak;

    public function boot(KeycloakClient $keycloak)
    {
        $this->keycloak = $keycloak;
    }

    #[Computed]
    public function clients()
    {
        $clients = $this->keycloak->getClients();

        // Filter internal Keycloak clients, show only user-created ones
        $clients = array_filter($clients, function ($client) {
            // Skip Keycloak internal clients (account, admin-cli, broker, realm-management, etc.)
            $internals = ['account', 'admin-cli', 'broker', 'realm-management', 'security-admin-console'];
            if (in_array($client['clientId'] ?? '', $internals)) {
                return false;
            }

            // Search filter
            if ($this->search) {
                $searchLower = strtolower($this->search);
                $clientId = strtolower($client['clientId'] ?? '');
                $name = strtolower($client['name'] ?? '');
                return str_contains($clientId, $searchLower) || str_contains($name, $searchLower);
            }

            return true;
        });

        return array_values($clients);
    }

    public function openDetail(string $id)
    {
        $this->detailClient = $this->keycloak->getClient($id);
        $this->detailRoles = $this->keycloak->getClientRoles($id);
        $this->detailSessions = $this->keycloak->getClientSessions($id);
        $this->detailSecret = null;
        $this->secretRevealed = false;
        $this->showDetail = true;
    }

    public function revealSecret()
    {
        if ($this->detailClient) {
            $this->detailSecret = $this->keycloak->getClientSecret($this->detailClient['id']);
            $this->secretRevealed = true;
        }
    }

    public function closeDetail()
    {
        $this->showDetail = false;
        $this->detailClient = null;
        $this->detailRoles = [];
        $this->detailSessions = [];
        $this->detailSecret = null;
        $this->secretRevealed = false;
    }

    public function toggleEnabled(string $id, bool $currentlyEnabled)
    {
        if ($currentlyEnabled) {
            $this->keycloak->disableClient($id);
            toaster_success('Client berhasil di-disable');
        } else {
            $this->keycloak->enableClient($id);
            toaster_success('Client berhasil di-enable');
        }
        unset($this->clients);
    }

    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.client.section.table');
    }
}
