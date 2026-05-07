<div>
    <x-nawasara-ui::sync-info-bar :lastSyncedAt="$this->lastSyncedAt" />

    {{-- Toolbar — search + sync button + export. No filter dimensions
         (search-only UI). --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <x-nawasara-ui::search-input model="search" placeholder="Cari client ID atau nama..." />

            <div class="flex items-center gap-2 shrink-0">
                <x-nawasara-ui::icon-button icon="refresh-cw" tooltip="Sync ulang dari Keycloak" wire:click="refreshClients" loadingTarget="refreshClients" />

                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor client list"
                    permission="keycloak.client.view" />
            </div>
        </div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    <x-nawasara-ui::table
        stickyLast
        :headers="['Client ID', 'Nama', 'Protocol', 'Status', 'Tipe', 'Sync', '']"
        :title="'Client Apps ('.$this->clients->total().' total)'">
        <x-slot:table>
            @forelse ($this->clients as $client)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">
                        {{ $client->client_id }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $client->name ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $client->protocol ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($client->enabled)
                            <x-nawasara-ui::badge color="success">Enabled</x-nawasara-ui::badge>
                        @else
                            <x-nawasara-ui::badge color="danger">Disabled</x-nawasara-ui::badge>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($client->public_client)
                            <x-nawasara-ui::badge color="info">Public</x-nawasara-ui::badge>
                        @else
                            <x-nawasara-ui::badge color="neutral">Confidential</x-nawasara-ui::badge>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <x-nawasara-sync::sync-badge :status="$client->sync_status" :error="$client->sync_error" />
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$client->id" :items="[
                            ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail('.$client->id.')', 'modal' => 'kc-client-detail', 'icon' => 'lucide-eye', 'permission' => 'keycloak.client.view'],
                            ['type' => 'click', 'label' => 'Edit', 'wire:click' => 'openEdit('.$client->id.')', 'modal' => 'kc-client-form', 'icon' => 'lucide-pencil', 'permission' => 'keycloak.client.manage'],
                            ['type' => 'click', 'label' => $client->enabled ? 'Disable' : 'Enable', 'wire:click' => 'toggleEnabled('.$client->id.')', 'icon' => $client->enabled ? 'lucide-power-off' : 'lucide-power', 'permission' => 'keycloak.client.manage'],
                            ['type' => 'click', 'label' => 'Hapus', 'wire:click' => 'deleteClient('.$client->id.')', 'icon' => 'lucide-trash-2', 'confirm' => 'Yakin ingin menghapus client ini?', 'permission' => 'keycloak.client.manage'],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        @if ($this->lastSyncedAt === null)
                            <x-nawasara-ui::empty-state
                                icon="lucide-app-window"
                                title="Database client masih kosong"
                                description="Klik tombol Sync Sekarang untuk fetch client dari Keycloak."
                                inline />
                        @elseif ($search !== '')
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada client yang cocok"
                                description="Coba ubah search keyword."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-app-window"
                                title="Belum ada client terdaftar"
                                description="Buat OIDC/SAML client di Keycloak admin console, lalu sync ulang."
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->clients->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>

    {{-- Form Modal --}}
    <x-nawasara-ui::modal id="kc-client-form" :title="$editingId ? 'Edit Client' : 'Tambah Client'">
        <form wire:submit="saveClient" id="kc-client-form" class="space-y-4">
            <x-nawasara-ui::form.input label="Client ID" placeholder="my-app" wire:model="formClientId" useError errorVariable="formClientId" :disabled="(bool) $editingId" />
            <x-nawasara-ui::form.input label="Nama (opsional)" placeholder="My Application" wire:model="formName" />
            <x-nawasara-ui::form.input label="Root URL (opsional)" placeholder="https://myapp.example.com" wire:model="formRootUrl" useError errorVariable="formRootUrl" />
            <div>
                <x-nawasara-ui::form.label value="Redirect URIs (satu per baris)" />
                <x-nawasara-ui::form.textarea wire:model="formRedirectUris" placeholder="https://myapp.example.com/*" rows="3" />
            </div>
            <div>
                <x-nawasara-ui::form.label value="Web Origins (satu per baris)" />
                <x-nawasara-ui::form.textarea wire:model="formWebOrigins" placeholder="https://myapp.example.com" rows="2" />
            </div>
            <hr class="dark:border-neutral-700">
            <div class="space-y-3">
                <x-nawasara-ui::form.checkbox label="Enabled" wire:model="formEnabled" />
                <x-nawasara-ui::form.checkbox label="Public Client (no client secret)" wire:model="formPublicClient" />
                <x-nawasara-ui::form.checkbox label="Standard Flow (Authorization Code)" wire:model="formStandardFlowEnabled" />
                <x-nawasara-ui::form.checkbox label="Service Accounts Enabled" wire:model="formServiceAccountsEnabled" />
                <x-nawasara-ui::form.checkbox label="Direct Access Grants" wire:model="formDirectAccessGrantsEnabled" />
            </div>
        </form>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" @click="$dispatch('close-modal', 'kc-client-form')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button type="submit" form="kc-client-form" color="primary">Simpan</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>

    {{-- Detail Modal --}}
    <x-nawasara-ui::modal id="kc-client-detail" maxWidth="2xl" :title="$this->detail?->client_id ?? ''" :subtitle="$this->detail?->name">
        @if ($this->detail)
            @php $c = $this->detail; @endphp
            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500 dark:text-neutral-400">Client ID:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $c->client_id }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Protocol:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $c->protocol ?? '-' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Status:</span> <span class="font-medium {{ $c->enabled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $c->enabled ? 'Enabled' : 'Disabled' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Tipe:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $c->public_client ? 'Public' : 'Confidential' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Root URL:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $c->root_url ?? '-' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Base URL:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $c->base_url ?? '-' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Standard Flow:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $c->standard_flow_enabled ? 'Ya' : 'Tidak' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Service Account:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $c->service_accounts_enabled ? 'Ya' : 'Tidak' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Last Synced:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $c->last_synced_at?->diffForHumans() ?? '-' }}</span></div>
                    <div class="col-span-2">
                        <span class="text-gray-500 dark:text-neutral-400">Redirect URIs:</span>
                        <div class="mt-1 space-y-1">
                            @forelse ($c->redirect_uris ?? [] as $uri)
                                <div class="text-xs font-mono bg-gray-50 dark:bg-neutral-700/50 px-2 py-1 rounded text-gray-700 dark:text-neutral-300">{{ $uri }}</div>
                            @empty
                                <div class="text-xs text-gray-400 italic">-</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if (! $c->public_client)
                    <div>
                        <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Client Secret</h4>
                        @if ($secretRevealed && $detailSecret)
                            <div class="flex items-center gap-2">
                                <code class="flex-1 text-sm bg-gray-50 dark:bg-neutral-700/50 px-3 py-2 rounded font-mono break-all text-gray-800 dark:text-neutral-200">{{ $detailSecret }}</code>
                                <x-nawasara-ui::button variant="ghost" color="neutral" size="sm"
                                    wire:click="$set('secretRevealed', false)">
                                    <x-slot:icon><x-lucide-eye-off /></x-slot:icon>
                                </x-nawasara-ui::button>
                            </div>
                            @can('keycloak.client.manage')
                                <x-nawasara-ui::button variant="link" color="warning" size="sm"
                                    wire:click="regenerateSecret"
                                    wire:confirm="Regenerate secret? Client lama akan berhenti bekerja."
                                    class="mt-2 text-xs">
                                    <x-slot:icon><x-lucide-refresh-cw /></x-slot:icon>
                                    Regenerate
                                </x-nawasara-ui::button>
                            @endcan
                        @else
                            @can('keycloak.client.reveal_secret')
                                <x-nawasara-ui::button variant="link" color="primary" size="sm"
                                    wire:click="revealSecret">
                                    <x-slot:icon><x-lucide-eye /></x-slot:icon>
                                    Tampilkan Secret
                                </x-nawasara-ui::button>
                            @else
                                <p class="text-xs text-gray-400 italic">Tidak punya permission untuk melihat secret</p>
                            @endcan
                        @endif
                    </div>
                @endif

                @if (!empty($detailRoles))
                    <div>
                        <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Client Roles</h4>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($detailRoles as $role)
                                <x-nawasara-ui::badge color="purple">{{ $role['name'] }}</x-nawasara-ui::badge>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (!empty($detailSessions))
                    <div>
                        <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Active Sessions ({{ count($detailSessions) }})</h4>
                        <div class="space-y-1.5">
                            @foreach ($detailSessions as $session)
                                <div class="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-neutral-700/50 rounded-lg text-sm">
                                    <div>
                                        <span class="font-medium text-gray-700 dark:text-neutral-300">{{ $session['username'] ?? '-' }}</span>
                                        <span class="text-gray-400 mx-1">|</span>
                                        <span class="text-gray-500">{{ $session['ipAddress'] ?? '-' }}</span>
                                    </div>
                                    <span class="text-xs text-gray-400">{{ isset($session['start']) ? \Carbon\Carbon::createFromTimestampMs($session['start'])->diffForHumans() : '-' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <x-slot:footer>
                <x-nawasara-ui::button color="neutral" variant="outline" wire:click="closeDetail">Tutup</x-nawasara-ui::button>
            </x-slot:footer>
        @endif
    </x-nawasara-ui::modal>
</div>
