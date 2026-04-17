<?php

namespace Nawasara\Keycloak\Livewire\Client\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Nawasara\Keycloak\Services\KeycloakClient;

class Table extends Component
{
    public string $search = '';

    // Detail modal
    public ?array $detailClient = null;
    public array $detailRoles = [];
    public array $detailSessions = [];
    public ?string $detailSecret = null;
    public bool $secretRevealed = false;

    // Form modal (create/edit)
    public ?string $editingId = null;
    public string $formClientId = '';
    public string $formName = '';
    public string $formRootUrl = '';
    public string $formRedirectUris = '';
    public string $formWebOrigins = '';
    public string $formProtocol = 'openid-connect';
    public bool $formPublicClient = false;
    public bool $formEnabled = true;
    public bool $formServiceAccountsEnabled = false;
    public bool $formStandardFlowEnabled = true;
    public bool $formDirectAccessGrantsEnabled = false;

    protected KeycloakClient $keycloak;

    public function boot(KeycloakClient $keycloak)
    {
        $this->keycloak = $keycloak;
    }

    #[Computed]
    public function clients()
    {
        $clients = $this->keycloak->getClients();

        $clients = array_filter($clients, function ($client) {
            $internals = ['account', 'admin-cli', 'broker', 'realm-management', 'security-admin-console'];
            if (in_array($client['clientId'] ?? '', $internals)) {
                return false;
            }

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

    // ─── Detail ─────────────────────────────────────────

    public function openDetail(string $id)
    {
        $this->detailClient = $this->keycloak->getClient($id);
        $this->detailRoles = $this->keycloak->getClientRoles($id);
        $this->detailSessions = $this->keycloak->getClientSessions($id);
        $this->detailSecret = null;
        $this->secretRevealed = false;
        $this->dispatch('modal-open:kc-client-detail');
    }

    public function revealSecret()
    {
        Gate::authorize('keycloak.client.reveal_secret');

        if ($this->detailClient) {
            $this->detailSecret = $this->keycloak->getClientSecret($this->detailClient['id']);
            $this->secretRevealed = true;
        }
    }

    public function regenerateSecret()
    {
        Gate::authorize('keycloak.client.manage');

        if ($this->detailClient) {
            $this->detailSecret = $this->keycloak->regenerateClientSecret($this->detailClient['id']);
            $this->secretRevealed = true;
            toaster_success('Client secret berhasil di-regenerate');
        }
    }

    public function closeDetail()
    {
        $this->dispatch('modal-close:kc-client-detail');
        $this->detailClient = null;
        $this->detailRoles = [];
        $this->detailSessions = [];
        $this->detailSecret = null;
        $this->secretRevealed = false;
    }

    // ─── Create / Edit ──────────────────────────────────

    #[On('openCreateClient')]
    public function openCreate()
    {
        Gate::authorize('keycloak.client.manage');

        $this->resetForm();
        $this->dispatch('modal-open:kc-client-form');
    }

    public function openEdit(string $id)
    {
        Gate::authorize('keycloak.client.manage');

        $client = $this->keycloak->getClient($id);
        if (! $client) return;

        $this->editingId = $id;
        $this->formClientId = $client['clientId'] ?? '';
        $this->formName = $client['name'] ?? '';
        $this->formRootUrl = $client['rootUrl'] ?? '';
        $this->formRedirectUris = implode("\n", $client['redirectUris'] ?? []);
        $this->formWebOrigins = implode("\n", $client['webOrigins'] ?? []);
        $this->formProtocol = $client['protocol'] ?? 'openid-connect';
        $this->formPublicClient = $client['publicClient'] ?? false;
        $this->formEnabled = $client['enabled'] ?? true;
        $this->formServiceAccountsEnabled = $client['serviceAccountsEnabled'] ?? false;
        $this->formStandardFlowEnabled = $client['standardFlowEnabled'] ?? true;
        $this->formDirectAccessGrantsEnabled = $client['directAccessGrantsEnabled'] ?? false;
        $this->dispatch('modal-open:kc-client-form');
    }

    public function saveClient()
    {
        Gate::authorize('keycloak.client.manage');

        $this->validate([
            'formClientId' => 'required|max:255',
            'formName' => 'nullable|max:255',
            'formRootUrl' => 'nullable|url|max:500',
        ]);

        $redirectUris = array_filter(array_map('trim', explode("\n", $this->formRedirectUris)));
        $webOrigins = array_filter(array_map('trim', explode("\n", $this->formWebOrigins)));

        $payload = [
            'clientId' => $this->formClientId,
            'name' => $this->formName ?: null,
            'rootUrl' => $this->formRootUrl ?: null,
            'redirectUris' => $redirectUris ?: ['*'],
            'webOrigins' => $webOrigins ?: ['*'],
            'protocol' => $this->formProtocol,
            'publicClient' => $this->formPublicClient,
            'enabled' => $this->formEnabled,
            'serviceAccountsEnabled' => $this->formServiceAccountsEnabled,
            'standardFlowEnabled' => $this->formStandardFlowEnabled,
            'directAccessGrantsEnabled' => $this->formDirectAccessGrantsEnabled,
        ];

        if ($this->editingId) {
            $this->keycloak->updateClient($this->editingId, $payload);
            toaster_success('Client berhasil diperbarui');
        } else {
            $this->keycloak->createClient($payload);
            toaster_success('Client berhasil dibuat');
        }

        $this->dispatch('modal-close:kc-client-form');
        $this->resetForm();
        unset($this->clients);
    }

    private function resetForm()
    {
        $this->editingId = null;
        $this->formClientId = '';
        $this->formName = '';
        $this->formRootUrl = '';
        $this->formRedirectUris = '';
        $this->formWebOrigins = '';
        $this->formProtocol = 'openid-connect';
        $this->formPublicClient = false;
        $this->formEnabled = true;
        $this->formServiceAccountsEnabled = false;
        $this->formStandardFlowEnabled = true;
        $this->formDirectAccessGrantsEnabled = false;
    }

    // ─── Actions ────────────────────────────────────────

    public function toggleEnabled(string $id, bool $currentlyEnabled)
    {
        Gate::authorize('keycloak.client.manage');

        if ($currentlyEnabled) {
            $this->keycloak->disableClient($id);
            toaster_success('Client berhasil di-disable');
        } else {
            $this->keycloak->enableClient($id);
            toaster_success('Client berhasil di-enable');
        }
        unset($this->clients);
    }

    public function deleteClient(string $id, string $clientId)
    {
        Gate::authorize('keycloak.client.manage');

        $this->keycloak->deleteClient($id);
        toaster_success("Client {$clientId} berhasil dihapus");
        unset($this->clients);
    }

    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.client.section.table');
    }
}
