<div>
    @php
        $typeOptions = [
            'LOGIN' => 'Login',
            'LOGIN_ERROR' => 'Login Error',
            'LOGOUT' => 'Logout',
            'REGISTER' => 'Register',
            'CODE_TO_TOKEN' => 'Code to Token',
            'CODE_TO_TOKEN_ERROR' => 'Token Error',
        ];
    @endphp

    {{-- Page header — title left, time-window right. Events are sourced
         live from Keycloak admin API; the trait's resolveTimeWindow()
         translates the active preset into dateFrom/dateTo params. --}}
    <x-nawasara-ui::page-header
        title="Keycloak Event Log"
        description="Login, logout, register events dari Keycloak Realm. Pastikan Event Logging di-enable di Realm Settings.">
        <x-nawasara-ui::time-window :window="$window" :from="$from" :to="$to" />
    </x-nawasara-ui::page-header>

    {{-- Toolbar — Event Type filter (single-select; the Keycloak API
         takes one type per call). --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['typeFilter' => $typeFilter]"
                    :labels="['typeFilter' => $typeOptions]"
                    :dimensions="['typeFilter' => 'Type']">
                    <x-nawasara-ui::filter-group label="Event Type" model="typeFilter" :items="$typeOptions" icon="lucide-zap" />
                </x-nawasara-ui::filter-panel>
            </div>
        </div>

        <div wire:ignore data-filter-chips></div>
    </div>

    {{-- No stickyLast: event log is read-only, no per-row action column. --}}
    <x-nawasara-ui::table :headers="['Type', 'User', 'IP Address', 'Client', 'Detail', 'Waktu']">
        <x-slot:table>
            @forelse ($this->events as $event)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @php
                            $isError = str_contains($event['type'] ?? '', 'ERROR');
                            $badgeClass = $isError
                                ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                : (($event['type'] ?? '') === 'LOGOUT'
                                    ? 'bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400'
                                    : 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400');
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                            {{ $event['type'] ?? '-' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $event['userId'] ?? '-' }}
                        @if (isset($event['details']['username']))
                            <span class="text-gray-400">({{ $event['details']['username'] }})</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $event['ipAddress'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $event['clientId'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-neutral-400 max-w-xs truncate">
                        @if (isset($event['error']))
                            <span class="text-red-600">{{ $event['error'] }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        @if (isset($event['time']))
                            {{ \Carbon\Carbon::createFromTimestampMs($event['time'])->format('d M Y H:i:s') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        @if ($typeFilter || $window !== '7d' || $from || $to)
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada event yang cocok"
                                description="Coba ubah periode/filter type."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-scroll-text"
                                title="Tidak ada event 7 hari terakhir"
                                description="Pilih periode lebih panjang, atau pastikan Event Logging di-enable di Keycloak Realm Settings (Events > Login Events Settings)."
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            <div class="flex items-center justify-between px-4 py-3">
                <div class="text-sm text-gray-500">Halaman {{ $page + 1 }}</div>
                <div class="flex gap-2">
                    <x-nawasara-ui::button color="neutral" variant="outline" size="sm"
                        wire:click="previousPage" :disabled="$page === 0">
                        Prev
                    </x-nawasara-ui::button>
                    <x-nawasara-ui::button color="neutral" variant="outline" size="sm"
                        wire:click="nextPage" :disabled="count($this->events) < $perPage">
                        Next
                    </x-nawasara-ui::button>
                </div>
            </div>
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
