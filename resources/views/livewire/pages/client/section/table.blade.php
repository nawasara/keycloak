<div>
    {{-- Sync info bar --}}
    <div class="mb-3 flex items-center justify-between text-xs text-gray-500 dark:text-neutral-400">
        <div class="flex items-center gap-3">
            @if ($this->lastSyncedAt)
                <span><x-lucide-clock class="size-3 inline" /> Last sync: {{ $this->lastSyncedAt }}</span>
            @else
                <span class="text-yellow-600">Belum pernah di-sync. Klik "Sync Sekarang".</span>
            @endif
        </div>
        <a href="{{ url('admin/sync/jobs') }}" wire:navigate class="text-blue-600 hover:underline">
            Lihat Sync Jobs →
        </a>
    </div>

    <x-nawasara-ui::filter-bar searchPlaceholder="Cari client ID, nama..." searchModel="search">
        <x-slot:actions>
            <x-nawasara-ui::button color="neutral" variant="outline" size="sm" wire:click="refreshClients">
                <x-slot:icon>
                    <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="refreshClients" />
                </x-slot:icon>
                Sync Sekarang
            </x-nawasara-ui::button>
        </x-slot:actions>

        <x-slot:chips>
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table
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
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400">Enabled</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400">Disabled</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($client->public_client)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Public</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400">Confidential</span>
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
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        @if ($this->lastSyncedAt === null)
                            Database masih kosong. Klik <strong>Sync Sekarang</strong>.
                        @else
                            Tidak ada client ditemukan.
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
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">{{ $role['name'] }}</span>
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
