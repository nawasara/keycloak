<div>
    {{-- Sync info bar --}}
    <div class="mb-3 flex items-center justify-between text-xs text-gray-500 dark:text-neutral-400">
        <div class="flex items-center gap-3">
            @if ($this->lastSyncedAt)
                <span><x-lucide-clock class="size-3 inline" /> Last sync: {{ $this->lastSyncedAt }}</span>
            @else
                <span class="text-amber-700 dark:text-amber-400">Belum pernah di-sync. Klik "Sync Sekarang".</span>
            @endif
        </div>
        <a href="{{ url('admin/sync/jobs') }}" wire:navigate class="text-emerald-700 dark:text-emerald-400 hover:underline font-medium">
            Lihat Sync Jobs →
        </a>
    </div>

    @php
        $statusOptions = ['enabled' => 'Enabled', 'disabled' => 'Disabled'];
    @endphp

    {{-- Toolbar — Status filter + search + sync button + export.
         No <x-page-header> here because the index page above already
         renders title + hero stats; layering another title would crowd
         the chrome. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['statusFilter' => $statusFilter]"
                    :multiple="['statusFilter']"
                    :labels="['statusFilter' => $statusOptions]"
                    :dimensions="['statusFilter' => 'Status']">
                    <x-nawasara-ui::filter-group label="Status" model="statusFilter" :items="$statusOptions" icon="lucide-circle-check" />
                </x-nawasara-ui::filter-panel>
            </div>

            <div class="relative w-full md:flex-1 md:min-w-0">
                <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-3.5">
                    <x-lucide-search class="shrink-0 size-4 text-gray-400 dark:text-neutral-500" />
                </div>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Cari username, email, atau nama..."
                    class="h-10 ps-10 pe-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-emerald-600 focus:ring-emerald-600 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-200 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" />
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <x-nawasara-ui::tooltip text="Sync ulang dari Keycloak" placement="bottom">
                    <button type="button" wire:click="refreshUsers"
                        wire:loading.attr="disabled" wire:target="refreshUsers"
                        aria-label="Sync Sekarang"
                        class="inline-flex items-center justify-center size-10 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-700 shadow-sm transition-colors disabled:opacity-50 disabled:pointer-events-none">
                        <x-lucide-refresh-cw class="size-4" wire:loading.class="animate-spin" wire:target="refreshUsers" />
                    </button>
                </x-nawasara-ui::tooltip>

                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor user list"
                    permission="keycloak.user.view" />
            </div>
        </div>

        <div wire:ignore data-filter-chips></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    <x-nawasara-ui::table
        stickyLast
        :headers="['Username', 'Email', 'Nama', 'Status', 'Sync', 'Dibuat', '']"
        :title="'Keycloak Users ('.$this->users->total().' total)'">
        <x-slot:table>
            @forelse ($this->users as $user)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">
                        {{ $user->username }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $user->email ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $user->full_name ?: '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($user->enabled)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400">Enabled</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400">Disabled</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <x-nawasara-sync::sync-badge :status="$user->sync_status" :error="$user->sync_error" />
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $user->kc_created_at?->format('d M Y') ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$user->id" :items="[
                            ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail('.$user->id.')', 'modal' => 'kc-user-detail', 'icon' => 'lucide-eye', 'permission' => 'keycloak.user.view'],
                            ['type' => 'click', 'label' => $user->enabled ? 'Disable' : 'Enable', 'wire:click' => 'toggleEnabled('.$user->id.')', 'icon' => $user->enabled ? 'lucide-user-x' : 'lucide-user-check', 'permission' => 'keycloak.user.manage'],
                            ['type' => 'click', 'label' => 'Reset Password', 'wire:click' => 'openResetPassword('.$user->id.')', 'modal' => 'kc-reset-password', 'icon' => 'lucide-key-round', 'permission' => 'keycloak.user.reset_password'],
                            ['type' => 'click', 'label' => 'Logout', 'wire:click' => 'logoutUser('.$user->id.')', 'icon' => 'lucide-log-out', 'confirm' => 'Logout semua session user ini?', 'permission' => 'keycloak.session.revoke'],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        @if ($this->lastSyncedAt === null)
                            {{-- Belum pernah sync — DB benar-benar kosong --}}
                            <x-nawasara-ui::empty-state
                                icon="lucide-users"
                                title="Database user masih kosong"
                                description="Klik tombol Sync Sekarang di atas untuk fetch user dari Keycloak."
                                inline />
                        @elseif ($search !== '' || ! empty($statusFilter))
                            {{-- Sync sudah pernah, tapi filter habiskan results --}}
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada user yang cocok"
                                description="Coba ubah filter atau hapus search keyword."
                                variant="filter"
                                inline />
                        @else
                            {{-- Sync sukses tapi DB memang tidak ada user --}}
                            <x-nawasara-ui::empty-state
                                icon="lucide-users"
                                title="Belum ada user terdaftar"
                                description="Tambah user di Keycloak admin console, lalu sync ulang."
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->users->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>

    {{-- Detail Modal --}}
    <x-nawasara-ui::modal id="kc-user-detail" maxWidth="2xl" :title="$this->detail?->username ?? ''">
        @if ($this->detail)
            @php $u = $this->detail; @endphp
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">Username:</span> <span class="font-medium">{{ $u->username }}</span></div>
                    <div><span class="text-gray-500">Email:</span> <span class="font-medium">{{ $u->email ?? '-' }}</span></div>
                    <div><span class="text-gray-500">Nama:</span> <span class="font-medium">{{ $u->full_name ?: '-' }}</span></div>
                    <div><span class="text-gray-500">Status:</span> <span class="font-medium {{ $u->enabled ? 'text-green-600' : 'text-red-600' }}">{{ $u->enabled ? 'Enabled' : 'Disabled' }}</span></div>
                    <div><span class="text-gray-500">Email Verified:</span> <span class="font-medium">{{ $u->email_verified ? 'Ya' : 'Tidak' }}</span></div>
                    <div><span class="text-gray-500">2FA (TOTP):</span> <span class="font-medium">{{ $u->totp ? 'Aktif' : 'Tidak' }}</span></div>
                    <div><span class="text-gray-500">Dibuat:</span> <span class="font-medium">{{ $u->kc_created_at?->format('d M Y H:i') ?? '-' }}</span></div>
                    <div><span class="text-gray-500">Last Synced:</span> <span class="font-medium">{{ $u->last_synced_at?->diffForHumans() ?? '-' }}</span></div>
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
                <x-nawasara-ui::button color="neutral" variant="outline" wire:click="closeDetail">Tutup</x-nawasara-ui::button>
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
            <x-nawasara-ui::button color="neutral" variant="outline" @click="$dispatch('close-modal', 'kc-reset-password')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button type="submit" form="kc-reset-pw-form" color="primary">Reset Password</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
