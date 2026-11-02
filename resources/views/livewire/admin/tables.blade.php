<?php

use App\Enums\TableStatus;
use App\Models\DiningTable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Meja & QR')] class extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public bool $is_active = true;

    /**
     * Load a table into the form for editing.
     */
    public function edit(DiningTable $diningTable): void
    {
        $this->editingId = $diningTable->id;
        $this->name = $diningTable->name;
        $this->is_active = $diningTable->is_active;
        $this->resetValidation();
    }

    /**
     * Create or update the table.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('dining_tables', 'name')->ignore($this->editingId)],
            'is_active' => ['boolean'],
        ]);

        DiningTable::updateOrCreate(['id' => $this->editingId], $validated);

        session()->flash('status', $this->editingId ? 'Meja diperbarui.' : 'Meja ditambahkan.');

        $this->cancel();
    }

    /**
     * Generate a new QR token so previously printed QR codes stop working.
     */
    public function regenerateCode(DiningTable $diningTable): void
    {
        $diningTable->update(['code' => DiningTable::generateCode()]);

        session()->flash('status', "QR {$diningTable->name} dibuat ulang. Cetak ulang QR meja ini.");
    }

    /**
     * Delete the table when it is not in use and has no order history.
     */
    public function delete(DiningTable $diningTable): void
    {
        if (! $diningTable->canBeDeleted()) {
            session()->flash('error', 'Meja sedang digunakan atau memiliki riwayat pesanan. Nonaktifkan meja sebagai gantinya.');

            return;
        }

        $diningTable->delete();

        session()->flash('status', 'Meja dihapus.');
    }

    /**
     * Reset the form.
     */
    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'is_active');
        $this->resetValidation();
    }

    /**
     * Get the data passed to the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'tables' => DiningTable::orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Meja & QR" description="Tambah, ubah, atau hapus meja, lalu unduh dan cetak QR setiap meja.">
        <x-slot name="actions">
            <a href="{{ route('admin.tables.qr.print') }}" target="_blank" class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                <x-icon name="qr" class="h-4 w-4" /> Cetak Semua QR
            </a>
        </x-slot>
    </x-page-header>

    <div class="grid gap-6 p-4 sm:p-6 lg:grid-cols-3 lg:p-8">
        <x-card class="p-6 lg:order-last">
            <h2 class="text-base font-semibold">{{ $editingId ? 'Ubah Meja' : 'Tambah Meja' }}</h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="name" value="Nomor / Nama Meja" />
                    <x-text-input wire:model="name" id="name" class="mt-1 block w-full" placeholder="Meja 1" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input wire:model="is_active" type="checkbox" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900" />
                    Meja aktif (QR dapat dipakai memesan)
                </label>

                <div class="flex gap-2">
                    <x-primary-button>Simpan</x-primary-button>
                    @if ($editingId)
                        <x-secondary-button wire:click="cancel">Batal</x-secondary-button>
                    @endif
                </div>
            </form>
        </x-card>

        <div class="space-y-4 lg:col-span-2">
            <x-flash-message />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($tables as $table)
                    <x-card wire:key="table-{{ $table->id }}" class="flex flex-col p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="font-semibold">{{ $table->name }}</h3>
                                @unless ($table->is_active)
                                    <p class="text-xs text-red-600 dark:text-red-400">Nonaktif</p>
                                @endunless
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $table->status->badgeClasses() }}">{{ $table->status->label() }}</span>
                        </div>

                        <div class="mx-auto my-4 rounded-lg bg-white p-2">
                            {!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(120)->margin(0)->generate($table->orderUrl()) !!}
                        </div>

                        <div class="mt-auto flex flex-wrap gap-x-3 gap-y-1 text-sm">
                            <a href="{{ route('admin.tables.qr.download', $table) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Unduh</a>
                            <a href="{{ route('admin.tables.qr.print', ['table' => $table->id]) }}" target="_blank" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Cetak</a>
                            <button wire:click="edit({{ $table->id }})" class="font-medium text-gray-600 hover:underline dark:text-gray-300">Ubah</button>
                            <button wire:click="regenerateCode({{ $table->id }})" wire:confirm="QR lama tidak akan berlaku lagi. Lanjutkan?" class="font-medium text-gray-600 hover:underline dark:text-gray-300">Buat Ulang QR</button>
                            <button wire:click="delete({{ $table->id }})" wire:confirm="Hapus meja ini?" class="font-medium text-red-600 hover:underline dark:text-red-400">Hapus</button>
                        </div>
                    </x-card>
                @empty
                    <x-card class="p-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:col-span-2 xl:col-span-3">Belum ada meja.</x-card>
                @endforelse
            </div>
        </div>
    </div>
</div>
