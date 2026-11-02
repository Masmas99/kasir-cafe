<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Pengguna')] class extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'kasir';

    public string $password = '';

    /**
     * Load a user into the form for editing.
     */
    public function edit(User $user): void
    {
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->password = '';
        $this->resetValidation();
    }

    /**
     * Create or update a staff user account.
     */
    public function save(): void
    {
        $passwordRules = $this->editingId
            ? ['nullable', 'string', Password::defaults()]
            : ['required', 'string', Password::defaults()];

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => $passwordRules,
        ]);

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = UserRole::from($validated['role']);
        $user->email_verified_at ??= now();

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        session()->flash('status', $this->editingId ? 'Akun pengguna diperbarui.' : 'Akun pengguna ditambahkan.');

        $this->cancel();
    }

    /**
     * Delete a user account (except the currently logged-in user).
     */
    public function delete(User $user): void
    {
        if ($user->id === Auth::id()) {
            session()->flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');

            return;
        }

        $user->delete();

        session()->flash('status', 'Akun pengguna dihapus.');
    }

    /**
     * Reset the form.
     */
    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'email', 'role', 'password');
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
            'users' => User::orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Manajemen Pengguna" description="Kelola akun Kasir dan Super Admin serta hak aksesnya." />

    <div class="grid gap-6 p-4 sm:p-6 lg:grid-cols-3 lg:p-8">
        <x-card class="p-6 lg:order-last">
            <h2 class="text-base font-semibold">{{ $editingId ? 'Ubah Pengguna' : 'Tambah Pengguna' }}</h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="name" value="Nama" />
                    <x-text-input wire:model="name" id="name" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="role" value="Peran (Role)" />
                    <x-select-input wire:model="role" id="role" class="mt-1 block w-full">
                        @foreach ($roles as $roleOption)
                            <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('role')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="password" :value="$editingId ? 'Password Baru (kosongkan jika tetap)' : 'Password'" />
                    <x-text-input wire:model="password" id="password" type="password" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
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
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($users as $userItem)
                            <tr wire:key="user-row-{{ $userItem->id }}">
                                <td class="px-4 py-3 font-medium">{{ $userItem->name }}</td>
                                <td class="px-4 py-3">{{ $userItem->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold dark:bg-gray-700">
                                        {{ $userItem->role->label() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button wire:click="edit({{ $userItem->id }})" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Ubah</button>
                                    @if ($userItem->id !== auth()->id())
                                        <button wire:click="delete({{ $userItem->id }})" wire:confirm="Hapus akun pengguna ini?" class="ms-3 font-medium text-red-600 hover:underline dark:text-red-400">Hapus</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>
</div>
