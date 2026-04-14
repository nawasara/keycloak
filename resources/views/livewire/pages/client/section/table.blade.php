<div>
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari client ID, nama..." searchModel="search" />

    <x-nawasara-ui::table :headers="['Client ID', 'Nama', 'Protocol', 'Status', 'Tipe', '']" title="Client Apps ({{ count($this->clients) }})">
        <x-slot:table>
            @forelse ($this->clients as $client)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">{{ $client['clientId'] ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">{{ $client['name'] ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">{{ $client['protocol'] ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($client['enabled'] ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400">Enabled</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400">Disabled</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($client['publicClient'] ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Public</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400">Confidential</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$client['id']" :items="[
                            ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail(\'' . $client['id'] . '\')', 'icon' => 'lucide-eye', 'permission' => 'keycloak.client.view'],
                            ['type' => 'click', 'label' => 'Edit', 'wire:click' => 'openEdit(\'' . $client['id'] . '\')', 'icon' => 'lucide-pencil', 'permission' => 'keycloak.client.manage'],
                            ['type' => 'click', 'label' => ($client['enabled'] ?? false) ? 'Disable' : 'Enable', 'wire:click' => 'toggleEnabled(\'' . $client['id'] . '\', ' . (($client['enabled'] ?? false) ? 'true' : 'false') . ')', 'icon' => ($client['enabled'] ?? false) ? 'lucide-power-off' : 'lucide-power', 'permission' => 'keycloak.client.manage'],
                            ['type' => 'click', 'label' => 'Hapus', 'wire:click' => 'deleteClient(\'' . $client['id'] . '\', \'' . ($client['clientId'] ?? '') . '\')', 'icon' => 'lucide-trash-2', 'confirm' => 'Yakin ingin menghapus client ini?', 'permission' => 'keycloak.client.manage'],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">Tidak ada client ditemukan.</td>
                </tr>
            @endforelse
        </x-slot:table>
    </x-nawasara-ui::table>

    {{-- Form Modal --}}
    <x-nawasara-ui::modal wire:model="showForm" :title="$editingId ? 'Edit Client' : 'Tambah Client'">
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
            <button type="button" wire:click="$set('showForm', false)" class="py-2.5 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">Batal</button>
            <x-nawasara-ui::button type="submit" form="kc-client-form" color="primary">Simpan</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>

    {{-- Detail Modal --}}
    <x-nawasara-ui::modal wire:model="showDetail" maxWidth="2xl" :title="$detailClient['clientId'] ?? ''" :subtitle="$detailClient['name'] ?? null">
        @if ($detailClient)
            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500 dark:text-neutral-400">Client ID:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $detailClient['clientId'] }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Protocol:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $detailClient['protocol'] ?? '-' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Status:</span> <span class="font-medium {{ ($detailClient['enabled'] ?? false) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ ($detailClient['enabled'] ?? false) ? 'Enabled' : 'Disabled' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Tipe:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ ($detailClient['publicClient'] ?? false) ? 'Public' : 'Confidential' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Root URL:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $detailClient['rootUrl'] ?? '-' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Base URL:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $detailClient['baseUrl'] ?? '-' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Standard Flow:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ ($detailClient['standardFlowEnabled'] ?? false) ? 'Ya' : 'Tidak' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Service Account:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ ($detailClient['serviceAccountsEnabled'] ?? false) ? 'Ya' : 'Tidak' }}</span></div>
                    <div class="col-span-2">
                        <span class="text-gray-500 dark:text-neutral-400">Redirect URIs:</span>
                        <div class="mt-1 space-y-1">
                            @foreach ($detailClient['redirectUris'] ?? [] as $uri)
                                <div class="text-xs font-mono bg-gray-50 dark:bg-neutral-700/50 px-2 py-1 rounded text-gray-700 dark:text-neutral-300">{{ $uri }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if (!($detailClient['publicClient'] ?? false))
                    <div>
                        <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Client Secret</h4>
                        @if ($secretRevealed && $detailSecret)
                            <div class="flex items-center gap-2">
                                <code class="flex-1 text-sm bg-gray-50 dark:bg-neutral-700/50 px-3 py-2 rounded font-mono break-all text-gray-800 dark:text-neutral-200">{{ $detailSecret }}</code>
                                <button wire:click="$set('secretRevealed', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-neutral-300"><x-lucide-eye-off class="size-4" /></button>
                            </div>
                            @can('keycloak.client.manage')
                                <button wire:click="regenerateSecret" wire:confirm="Regenerate secret? Client lama akan berhenti bekerja." class="mt-2 inline-flex items-center gap-1.5 text-xs text-orange-600 dark:text-orange-400 hover:underline">
                                    <x-lucide-refresh-cw class="size-3" /> Regenerate
                                </button>
                            @endcan
                        @else
                            @can('keycloak.client.reveal_secret')
                                <button wire:click="revealSecret" class="inline-flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    <x-lucide-eye class="size-4" /> Tampilkan Secret
                                </button>
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
                <button wire:click="closeDetail" class="py-2 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">Tutup</button>
            </x-slot:footer>
        @endif
    </x-nawasara-ui::modal>
</div>
