<div>
    <x-nawasara-ui::table :headers="['Client', 'Active Sessions']" title="Session per Client App">
        <x-slot:table>
            @forelse ($this->activeSessions as $stat)
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-800 dark:text-neutral-200">
                        {{ $stat['clientId'] ?? $stat['id'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ ($stat['active'] ?? 0) > 0 ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600' }}">
                            {{ $stat['active'] ?? 0 }} sessions
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Tidak ada session aktif saat ini.
                    </td>
                </tr>
            @endforelse
        </x-slot:table>
    </x-nawasara-ui::table>
</div>
