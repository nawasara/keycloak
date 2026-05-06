<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Keycloak Users</x-nawasara-ui::page.title>

        {{-- Hero stats — angka tidak di-filter oleh search/status di table.
             Caranya: derive di Index component (bukan di Section/Table) supaya
             tetap reflect total realitas. --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach ($this->stats as $stat)
                <x-nawasara-ui::stat-card
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :icon="$stat['icon']"
                    :color="$stat['color']"
                    :description="$stat['description'] ?? null"
                    accent />
            @endforeach
        </div>

        @livewire('nawasara-keycloak.user.section.table')
    </x-nawasara-ui::page.container>
</div>
