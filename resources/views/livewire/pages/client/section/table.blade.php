<div>
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari client ID, nama..." searchModel="search" />

    <x-nawasara-ui::table :headers="['Client ID', 'Nama', 'Protocol', 'Status', 'Public', '']" title="Client Apps ({{ count($this->clients) }})">
        <x-slot:table>
            @forelse ($this->clients as $client)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">
                        {{ $client['clientId'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $client['name'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $client['protocol'] ?? '-' }}
                    </td>
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
                            ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail(\'' . $client['id'] . '\')', 'icon' => 'lucide-eye'],
                            ['type' => 'click', 'label' => ($client['enabled'] ?? false) ? 'Disable' : 'Enable', 'wire:click' => 'toggleEnabled(\'' . $client['id'] . '\', ' . (($client['enabled'] ?? false) ? 'true' : 'false') . ')', 'icon' => ($client['enabled'] ?? false) ? 'lucide-power-off' : 'lucide-power'],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Tidak ada client ditemukan.
                    </td>
                </tr>
            @endforelse
        </x-slot:table>
    </x-nawasara-ui::table>

    {{-- Detail Modal --}}
    @if ($showDetail && $detailClient)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeDetail">
            <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-neutral-200">
                        {{ $detailClient['clientId'] }}
                        @if ($detailClient['name'] ?? false)
                            <span class="text-sm font-normal text-gray-500">— {{ $detailClient['name'] }}</span>
                        @endif
                    </h3>
                    <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600"><x-lucide-x class="size-5" /></button>
                </div>
                <div class="px-6 py-4 max-h-[70vh] overflow-y-auto space-y-5">
                    {{-- Info --}}
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Client ID:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $detailClient['clientId'] }}</span></div>
                        <div><span class="text-gray-500">Protocol:</span> <span class="font-medium">{{ $detailClient['protocol'] ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Status:</span>
                            @if ($detailClient['enabled'] ?? false)
                                <span class="font-medium text-green-600">Enabled</span>
                            @else
                                <span class="font-medium text-red-600">Disabled</span>
                            @endif
                        </div>
                        <div><span class="text-gray-500">Tipe:</span> <span class="font-medium">{{ ($detailClient['publicClient'] ?? false) ? 'Public' : 'Confidential' }}</span></div>
                        <div><span class="text-gray-500">Root URL:</span> <span class="font-medium">{{ $detailClient['rootUrl'] ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Base URL:</span> <span class="font-medium">{{ $detailClient['baseUrl'] ?? '-' }}</span></div>
                        <div class="col-span-2">
                            <span class="text-gray-500">Redirect URIs:</span>
                            <div class="mt-1 space-y-1">
                                @foreach ($detailClient['redirectUris'] ?? [] as $uri)
                                    <div class="text-xs font-mono bg-gray-50 dark:bg-neutral-700/50 px-2 py-1 rounded">{{ $uri }}</div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Client Secret --}}
                    @if (!($detailClient['publicClient'] ?? false))
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Client Secret</h4>
                            @if ($secretRevealed && $detailSecret)
                                <div class="flex items-center gap-2">
                                    <code class="flex-1 text-sm bg-gray-50 dark:bg-neutral-700/50 px-3 py-2 rounded font-mono">{{ $detailSecret }}</code>
                                    <button wire:click="$set('secretRevealed', false)"
                                        class="text-gray-400 hover:text-gray-600">
                                        <x-lucide-eye-off class="size-4" />
                                    </button>
                                </div>
                            @else
                                <button wire:click="revealSecret"
                                    class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline">
                                    <x-lucide-eye class="size-4" />
                                    Tampilkan Secret
                                </button>
                            @endif
                        </div>
                    @endif

                    {{-- Roles --}}
                    @if (!empty($detailRoles))
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Client Roles</h4>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($detailRoles as $role)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                        {{ $role['name'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Active Sessions --}}
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
                                        <span class="text-xs text-gray-400">
                                            {{ isset($session['start']) ? \Carbon\Carbon::createFromTimestampMs($session['start'])->diffForHumans() : '-' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div class="px-6 py-3 border-t border-gray-200 dark:border-neutral-700 flex justify-end">
                    <button wire:click="closeDetail" class="py-2 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
