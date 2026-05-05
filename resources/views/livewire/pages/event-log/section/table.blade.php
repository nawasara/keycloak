<div>
    <x-nawasara-ui::filter-bar>
        <x-nawasara-ui::filter-dropdown label="Event Type" model="typeFilter"
            :items="[
                'all' => 'Semua',
                'LOGIN' => 'Login',
                'LOGIN_ERROR' => 'Login Error',
                'LOGOUT' => 'Logout',
                'REGISTER' => 'Register',
                'CODE_TO_TOKEN' => 'Code to Token',
                'CODE_TO_TOKEN_ERROR' => 'Token Error',
            ]" />

        <x-slot:chips>
            @if ($typeFilter)
                <x-nawasara-ui::filter-chip label="Type: {{ $typeFilter }}" model="typeFilter" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table :headers="['Type', 'User', 'IP Address', 'Client', 'Detail', 'Waktu']" title="Login Events">
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
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Tidak ada event. Pastikan Event Logging di-enable di Keycloak Realm Settings.
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
