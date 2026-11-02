<?php

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Pengaturan Cafe')] class extends Component
{
    public string $cafe_name = '';

    public int $tax_percent = 0;

    public int $service_percent = 0;

    /**
     * Mount the component with current cafe settings.
     */
    public function mount(): void
    {
        $this->cafe_name = (string) Setting::getValue('cafe_name', config('app.name', 'Kasir Meja Cafe'));
        $this->tax_percent = (int) Setting::getValue('tax_percent', '0');
        $this->service_percent = (int) Setting::getValue('service_percent', '0');
    }

    /**
     * Save the cafe settings.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'cafe_name' => ['required', 'string', 'max:120'],
            'tax_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'service_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        Setting::setValue('cafe_name', $validated['cafe_name']);
        Setting::setValue('tax_percent', (string) $validated['tax_percent']);
        Setting::setValue('service_percent', (string) $validated['service_percent']);

        session()->flash('status', 'Pengaturan cafe berhasil disimpan.');
    }
}; ?>

<div>
    <x-page-header title="Pengaturan Cafe" description="Atur nama cafe, pajak, dan service charge." />

    <div class="max-w-2xl space-y-4 p-4 sm:p-6 lg:p-8">
        <x-flash-message />

        <x-card class="p-6">
            <form wire:submit="save" class="space-y-4">
                <div>
                    <x-input-label for="cafe_name" value="Nama Cafe" />
                    <x-text-input wire:model="cafe_name" id="cafe_name" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('cafe_name')" class="mt-1" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="tax_percent" value="Pajak (%)" />
                        <x-text-input wire:model="tax_percent" id="tax_percent" type="number" min="0" max="100" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('tax_percent')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="service_percent" value="Service Charge (%)" />
                        <x-text-input wire:model="service_percent" id="service_percent" type="number" min="0" max="100" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('service_percent')" class="mt-1" />
                    </div>
                </div>

                <x-primary-button>Simpan Pengaturan</x-primary-button>
            </form>
        </x-card>
    </div>
</div>
