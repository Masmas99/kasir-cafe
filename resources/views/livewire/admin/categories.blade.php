<?php

use App\Models\Category;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Kategori Menu')] class extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public int $sort_order = 0;

    /**
     * Load a category into the form for editing.
     */
    public function edit(Category $category): void
    {
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->sort_order = $category->sort_order;
        $this->resetValidation();
    }

    /**
     * Create or update the category.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($this->editingId)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        Category::updateOrCreate(['id' => $this->editingId], $validated);

        session()->flash('status', $this->editingId ? 'Kategori diperbarui.' : 'Kategori ditambahkan.');

        $this->cancel();
    }

    /**
     * Delete the category when it has no menu items.
     */
    public function delete(Category $category): void
    {
        if ($category->menuItems()->exists()) {
            session()->flash('error', 'Kategori masih memiliki menu dan tidak dapat dihapus.');

            return;
        }

        $category->delete();

        session()->flash('status', 'Kategori dihapus.');
    }

    /**
     * Reset the form.
     */
    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'sort_order');
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
            'categories' => Category::withCount('menuItems')->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Kategori Menu" description="Kelompokkan menu agar mudah dicari pelanggan." />

    <div class="grid gap-6 p-4 sm:p-6 lg:grid-cols-3 lg:p-8">
        <x-card class="p-6 lg:order-last">
            <h2 class="text-base font-semibold">{{ $editingId ? 'Ubah Kategori' : 'Tambah Kategori' }}</h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="name" value="Nama" />
                    <x-text-input wire:model="name" id="name" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="sort_order" value="Urutan Tampil" />
                    <x-text-input wire:model="sort_order" id="sort_order" type="number" min="0" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                </div>

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

            <x-card class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Urutan</th>
                            <th class="px-4 py-3">Jumlah Menu</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($categories as $category)
                            <tr wire:key="category-{{ $category->id }}">
                                <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                                <td class="px-4 py-3">{{ $category->sort_order }}</td>
                                <td class="px-4 py-3">{{ $category->menu_items_count }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button wire:click="edit({{ $category->id }})" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Ubah</button>
                                    <button wire:click="delete({{ $category->id }})" wire:confirm="Hapus kategori ini?" class="ms-3 font-medium text-red-600 hover:underline dark:text-red-400">Hapus</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Belum ada kategori.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>
</div>
