<?php

namespace Nawasara\Keycloak\Livewire\Client\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Keycloak\Models\KeycloakClient as KcClientModel;
use Nawasara\Keycloak\Repositories\KeycloakClientRepository;
use Nawasara\Keycloak\Services\KeycloakClient;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class Table extends Component
{
    use HasBrowserToast;
    use WithPagination;

    public string $search = '';
    public int $perPage = 25;

    // Detail modal
    public ?int $detailId = null;
    public array $detailRoles = [];
    public array $detailSessions = [];
    public ?string $detailSecret = null;
    public bool $secretRevealed = false;

    // Form modal
    public ?int $editingId = null;
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

    protected function repo(): KeycloakClientRepository
    {
        return new KeycloakClientRepository();
    }

    public function updatedSearch(): void { $this->resetPage(); }

    #[Computed]
    public function clients()
    {
        return $this->repo()->list([
            'search' => $this->search ?: null,
        ], $this->perPage);
    }

    #[Computed]
    public function lastSyncedAt(): ?string
    {
        $when = $this->repo()->lastSyncedAt();
        return $when ? $when->diffForHumans() : null;
    }

    #[Computed]
    public function detail(): ?KcClientModel
    {
        return $this->detailId ? KcClientModel::find($this->detailId) : null;
    }

    public function refreshClients(): void
    {
        Gate::authorize('keycloak.client.view');
        $this->repo()->syncNow();
        $this->toastSuccess('Sync dispatched. Refresh dalam beberapa detik.');
    }

    // ─── Detail ─────────────────────────────────────────

    public function openDetail(int $id): void
    {
        $client = KcClientModel::find($id);
        if (! $client) return;

        $this->detailId = $id;
        // Roles & sessions are live data
        $this->detailRoles = $this->keycloak->getClientRoles($client->client_uuid);
        $this->detailSessions = $this->keycloak->getClientSessions($client->client_uuid);
        $this->detailSecret = null;
        $this->secretRevealed = false;
        $this->dispatch('modal-open:kc-client-detail');
    }

    public function revealSecret(): void
    {
        Gate::authorize('keycloak.client.reveal_secret');

        if ($this->detail) {
            $this->detailSecret = $this->keycloak->getClientSecret($this->detail->client_uuid);
            $this->secretRevealed = true;
        }
    }

    public function regenerateSecret(): void
    {
        Gate::authorize('keycloak.client.manage');

        if ($this->detail) {
            $this->detailSecret = $this->keycloak->regenerateClientSecret($this->detail->client_uuid);
            $this->secretRevealed = true;
            $this->toastSuccess('Client secret berhasil di-regenerate');
        }
    }

    public function closeDetail(): void
    {
        $this->dispatch('modal-close:kc-client-detail');
        $this->detailId = null;
        $this->detailRoles = [];
        $this->detailSessions = [];
        $this->detailSecret = null;
        $this->secretRevealed = false;
    }

    // ─── Create / Edit ──────────────────────────────────

    #[On('openCreateClient')]
    public function openCreate(): void
    {
        Gate::authorize('keycloak.client.manage');
        $this->resetForm();
        $this->dispatch('modal-open:kc-client-form');
    }

    public function openEdit(int $id): void
    {
        Gate::authorize('keycloak.client.manage');

        $client = KcClientModel::find($id);
        if (! $client) return;

        $this->editingId = $id;
        $this->formClientId = $client->client_id;
        $this->formName = $client->name ?? '';
        $this->formRootUrl = $client->root_url ?? '';
        $this->formRedirectUris = implode("\n", $client->redirect_uris ?? []);
        $this->formWebOrigins = implode("\n", $client->web_origins ?? []);
        $this->formProtocol = $client->protocol ?? 'openid-connect';
        $this->formPublicClient = $client->public_client;
        $this->formEnabled = $client->enabled;
        $this->formServiceAccountsEnabled = $client->service_accounts_enabled;
        $this->formStandardFlowEnabled = $client->standard_flow_enabled;
        $this->formDirectAccessGrantsEnabled = $client->direct_access_grants_enabled;
        $this->dispatch('modal-open:kc-client-form');
    }

    public function saveClient(): void
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

        try {
            if ($this->editingId) {
                $this->repo()->update($this->editingId, $payload);
                $this->toastSuccess('Client sedang di-update');
            } else {
                $this->repo()->create($payload);
                $this->toastSuccess('Client sedang dibuat');
            }
            $this->dispatch('modal-close:kc-client-form');
            $this->resetForm();
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    private function resetForm(): void
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

    public function toggleEnabled(int $id): void
    {
        Gate::authorize('keycloak.client.manage');

        $client = KcClientModel::find($id);
        if (! $client) return;

        try {
            $this->repo()->update($id, ['enabled' => ! $client->enabled]);
            $this->toastSuccess($client->enabled ? "Client {$client->client_id} sedang di-disable" : "Client {$client->client_id} sedang di-enable");
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    public function deleteClient(int $id): void
    {
        Gate::authorize('keycloak.client.manage');

        try {
            $this->repo()->delete($id);
            $this->toastSuccess('Client delete dispatched');
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.client.section.table');
    }
}
