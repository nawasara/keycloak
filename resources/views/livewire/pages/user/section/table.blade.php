<div>
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari username, email, nama..." searchModel="search" />

    <x-nawasara-ui::table :headers="['Username', 'Email', 'Nama', 'Status', 'Dibuat', '']" title="Keycloak Users ({{ $this->userCount }} total)">
        <x-slot:table>
            @forelse ($this->users as $user)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">
                        {{ $user['username'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $user['email'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ ($user['firstName'] ?? '').' '.($user['lastName'] ?? '') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($user['enabled'] ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400">Enabled</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400">Disabled</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        @if (isset($user['createdTimestamp']))
                            {{ \Carbon\Carbon::createFromTimestampMs($user['createdTimestamp'])->format('d M Y') }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$user['id']" :items="[
                            ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail(\'' . $user['id'] . '\')', 'modal' => 'kc-user-detail', 'icon' => 'lucide-eye', 'permission' => 'keycloak.user.view'],
                            ['type' => 'click', 'label' => ($user['enabled'] ?? false) ? 'Disable' : 'Enable', 'wire:click' => 'toggleEnabled(\'' . $user['id'] . '\', ' . (($user['enabled'] ?? false) ? 'true' : 'false') . ')', 'icon' => ($user['enabled'] ?? false) ? 'lucide-user-x' : 'lucide-user-check', 'permission' => 'keycloak.user.manage'],
                            ['type' => 'click', 'label' => 'Reset Password', 'wire:click' => 'openResetPassword(\'' . $user['id'] . '\', \'' . $user['username'] . '\')', 'icon' => 'lucide-key-round', 'permission' => 'keycloak.user.reset_password'],
                            ['type' => 'click', 'label' => 'Logout', 'wire:click' => 'logoutUser(\'' . $user['id'] . '\', \'' . $user['username'] . '\')', 'icon' => 'lucide-log-out', 'confirm' => 'Logout semua session user ini?', 'permission' => 'keycloak.session.revoke'],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Tidak ada user ditemukan.
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            <div class="flex items-center justify-between px-4 py-3">
                <div class="text-sm text-gray-500">
                    Halaman {{ $page + 1 }} dari {{ max(1, ceil($this->userCount / $perPage)) }}
                </div>
                <div class="flex gap-2">
                    <button wire:click="previousPage" @disabled($page === 0)
                        class="px-3 py-1.5 text-sm border rounded-lg disabled:opacity-50 hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-700">
                        Prev
                    </button>
                    <button wire:click="nextPage" @disabled(($page + 1) * $perPage >= $this->userCount)
                        class="px-3 py-1.5 text-sm border rounded-lg disabled:opacity-50 hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-700">
                        Next
                    </button>
                </div>
            </div>
        </x-slot:footer>
    </x-nawasara-ui::table>

    {{-- Detail Modal --}}
    <x-nawasara-ui::modal id="kc-user-detail" maxWidth="2xl" :title="$detailUser['username'] ?? ''">
        @if ($detailUser)
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">Username:</span> <span class="font-medium">{{ $detailUser['username'] }}</span></div>
                    <div><span class="text-gray-500">Email:</span> <span class="font-medium">{{ $detailUser['email'] ?? '-' }}</span></div>
                    <div><span class="text-gray-500">Nama:</span> <span class="font-medium">{{ ($detailUser['firstName'] ?? '').' '.($detailUser['lastName'] ?? '') }}</span></div>
                    <div><span class="text-gray-500">Status:</span> <span class="font-medium {{ ($detailUser['enabled'] ?? false) ? 'text-green-600' : 'text-red-600' }}">{{ ($detailUser['enabled'] ?? false) ? 'Enabled' : 'Disabled' }}</span></div>
                    <div><span class="text-gray-500">Email Verified:</span> <span class="font-medium">{{ ($detailUser['emailVerified'] ?? false) ? 'Ya' : 'Tidak' }}</span></div>
                    <div><span class="text-gray-500">Dibuat:</span> <span class="font-medium">{{ isset($detailUser['createdTimestamp']) ? \Carbon\Carbon::createFromTimestampMs($detailUser['createdTimestamp'])->format('d M Y H:i') : '-' }}</span></div>
                </div>

                @if (!empty($detailSessions))
                    <div>
                        <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Active Sessions</h4>
                        <div class="space-y-2">
                            @foreach ($detailSessions as $session)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-neutral-700/50 rounded-lg text-sm">
                                    <div>
                                        <span class="text-gray-700 dark:text-neutral-300">{{ $session['ipAddress'] ?? '-' }}</span>
                                        <span class="text-gray-400 mx-1">|</span>
                                        <span class="text-gray-500">{{ isset($session['start']) ? \Carbon\Carbon::createFromTimestampMs($session['start'])->diffForHumans() : '-' }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-400">Tidak ada session aktif.</p>
                @endif
            </div>
            <x-slot:footer>
                <button wire:click="closeDetail" class="py-2 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">Tutup</button>
            </x-slot:footer>
        @endif
    </x-nawasara-ui::modal>

    {{-- Reset Password Modal --}}
    <x-nawasara-ui::modal id="kc-reset-password" maxWidth="md" :title="'Reset Password: '.$resetUserName">
        <form wire:submit="doResetPassword" id="kc-reset-pw-form" class="space-y-4">
            <x-nawasara-ui::form.input label="Password Baru" type="password"
                wire:model="newPassword" usePasswordField useError errorVariable="newPassword" />
            <x-nawasara-ui::form.checkbox label="Temporary (user harus ganti saat login)" wire:model="temporary" />
        </form>

        <x-slot:footer>
            <button type="button" @click="$dispatch('close-modal', 'kc-reset-password')" class="py-2.5 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">Batal</button>
            <x-nawasara-ui::button type="submit" form="kc-reset-pw-form" color="primary">Reset Password</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
