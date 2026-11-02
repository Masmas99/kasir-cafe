<?php

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Menu')] class extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $categoryFilter = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $category_id = null;

    public string $name = '';

    public string $description = '';

    public ?int $price = null;

    public bool $is_available = true;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $image = null;

    /**
     * Reset pagination when filters change.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryFilter'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Open an empty form for a new menu item.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    /**
     * Load a menu item into the form for editing.
     */
    public function edit(MenuItem $menuItem): void
    {
        $this->resetForm();
        $this->editingId = $menuItem->id;
        $this->category_id = $menuItem->category_id;
        $this->name = $menuItem->name;
        $this->description = (string) $menuItem->description;
        $this->price = $menuItem->price;
        $this->is_available = $menuItem->is_available;
        $this->showForm = true;
    }

    /**
     * Create or update the menu item.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'is_available' => ['boolean'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:5120'],
        ]);

        $menuItem = $this->editingId ? MenuItem::findOrFail($this->editingId) : new MenuItem;

        unset($validated['image']);
        $menuItem->fill($validated);

        if ($this->image) {
            if ($menuItem->image_path) {
                Storage::disk('public')->delete($menuItem->image_path);
            }

            $menuItem->image_path = $this->image->store('menu-items', 'public');
        }

        $menuItem->save();

        session()->flash('status', $this->editingId ? 'Menu diperbarui.' : 'Menu ditambahkan.');

        $this->resetForm();
    }

    /**
     * Toggle whether the menu item is available or sold out.
     */
    public function toggleAvailability(MenuItem $menuItem): void
    {
        $menuItem->update(['is_available' => ! $menuItem->is_available]);
    }

    /**
     * Delete the menu item and its image.
     */
    public function delete(MenuItem $menuItem): void
    {
        if ($menuItem->image_path) {
            Storage::disk('public')->delete($menuItem->image_path);
        }

        $menuItem->delete();

        session()->flash('status', 'Menu dihapus.');
    }

    /**
     * Close and reset the form.
     */
    public function resetForm(): void
    {
        $this->reset('showForm', 'editingId', 'category_id', 'name', 'description', 'price', 'is_available', 'image');
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
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'menuItems' => MenuItem::with('category')
                ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->categoryFilter, fn ($query) => $query->where('category_id', $this->categoryFilter))
                ->orderBy('name')
                ->paginate(15),
        ];
    }
}; ?>

<div>
    <x-page-header title="Menu" description="Kelola daftar menu, harga, foto, dan ketersediaan.">
        <x-slot name="actions">
            <x-primary-button type="button" wire:click="create">Tambah Menu</x-primary-button>
        </x-slot>
    </x-page-header>

    <div class="space-y-4 p-4 sm:p-6 lg:p-8">
        <x-flash-message />

        @if ($showForm)
            <x-card class="p-6">
                <h2 class="text-base font-semibold">{{ $editingId ? 'Ubah Menu' : 'Tambah Menu' }}</h2>

                <form wire:submit="save" class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="name" value="Nama Menu" />
                        <x-text-input wire:model="name" id="name" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="category_id" value="Kategori" />
                        <x-select-input wire:model="category_id" id="category_id" class="mt-1 block w-full" required>
                            <option value="">Pilih kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="price" value="Harga (Rp)" />
                        <x-text-input wire:model="price" id="price" type="number" min="0" step="500" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('price')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="image" value="Foto (maks. 2 MB)" />
                        <input wire:model="image" id="image" type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-600 file:me-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 dark:text-gray-300 dark:file:bg-gray-700 dark:file:text-gray-200" />
                        <x-input-error :messages="$errors->get('image')" class="mt-2" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="description" value="Deskripsi" />
                        <textarea wire:model="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2 text-sm md:col-span-2">
                        <input wire:model="is_available" type="checkbox" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900" />
                        Tersedia untuk dipesan
                    </label>

                    <div class="flex gap-2 md:col-span-2">
                        <x-primary-button wire:loading.attr="disabled">Simpan</x-primary-button>
                        <x-secondary-button wire:click="resetForm">Batal</x-secondary-button>
                    </div>
                </form>
            </x-card>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row">
            <x-text-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari menu..." class="w-full sm:max-w-xs" />
            <x-select-input wire:model.live="categoryFilter" class="w-full sm:max-w-xs">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </x-select-input>
        </div>

        <x-card class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Menu</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Harga</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($menuItems as $menuItem)
                        <tr wire:key="menu-item-{{ $menuItem->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($menuItem->image_url)
                                        <img src="{{ $menuItem->image_url }}" alt="{{ $menuItem->name }}" class="h-10 w-10 rounded-lg object-cover" />
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-700"><x-icon name="cup" /></div>
                                    @endif
                                    <span class="font-medium">{{ $menuItem->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $menuItem->category->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3"><x-rupiah :amount="$menuItem->price" /></td>
                            <td class="px-4 py-3">
                                <button wire:click="toggleAvailability({{ $menuItem->id }})" @class([
                                    'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-800 dark:bg-green-500/10 dark:text-green-400' => $menuItem->is_available,
                                    'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-400' => ! $menuItem->is_available,
                                ])>
                                    {{ $menuItem->is_available ? 'Tersedia' : 'Habis' }}
                                </button>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button wire:click="edit({{ $menuItem->id }})" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Ubah</button>
                                <button wire:click="delete({{ $menuItem->id }})" wire:confirm="Hapus menu ini?" class="ms-3 font-medium text-red-600 hover:underline dark:text-red-400">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Belum ada menu.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        {{ $menuItems->links() }}
    </div>
</div>
