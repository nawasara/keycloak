<div>
    <x-nawasara-ui::table :headers="['Client', 'Active Sessions']" title="Session per Client App">
        <x-slot:table>
            @forelse ($this->activeSessions as $stat)
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-800 dark:text-neutral-200">
                        {{ $stat['clientId'] ?? $stat['id'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <x-nawasara-ui::badge :color="($stat['active'] ?? 0) > 0 ? 'success' : 'neutral'">
                            {{ $stat['active'] ?? 0 }} sessions
                        </x-nawasara-ui::badge>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">
                        <x-nawasara-ui::empty-state
                            icon="lucide-monitor-dot"
                            title="Tidak ada session aktif"
                            description="Tidak ada user yang sedang login ke aplikasi yang ter-integrate Keycloak."
                            inline />
                    </td>
                </tr>
            @endforelse
        </x-slot:table>
    </x-nawasara-ui::table>
</div>
