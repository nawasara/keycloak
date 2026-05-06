<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Keycloak Clients</x-nawasara-ui::page.title>

        <x-slot name="actions">
            <x-nawasara-ui::page.actions>
                <x-nawasara-ui::button wire:click="$dispatch('openCreateClient')" color="success"
                    @click="$dispatch('open-modal', 'kc-client-form')"
                    permission="keycloak.client.manage">
                    <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                    Tambah Client
                </x-nawasara-ui::button>
            </x-nawasara-ui::page.actions>
        </x-slot>

        {{-- Hero stats — derive di Index component supaya tidak ter-filter
             oleh search di table di bawahnya. --}}
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

        @livewire('nawasara-keycloak.client.section.table')
    </x-nawasara-ui::page.container>
</div>
