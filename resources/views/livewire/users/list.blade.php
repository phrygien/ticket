
<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Mary\Traits\Toast;

new class extends Component {

    use Toast;

    public array $users = [];
    public bool $myModal1 = false;
    public bool $updateModal = false;

    // Form fields
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $confirm_password = '';
    public string $role = '';
    public ?int $selectedUserId = null;


    public string $editname = '';
    public string $editemail = '';
    public string $editpassword = '';
    public string $editconfirm_password = '';
    public string $editrole = '';


    public function mount(): void
    {
        $this->fetchUsers();

        $this->role = session('role');
    }

    public function fetchUsers()
    {
        try {
            $token = session('token');

            if (!$token) {
                $token = $this->loginAndGetToken();
            }

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->get(env('API_REST') . '/user');

                if ($response->successful()) {
                    $this->users = $response->json();
                }
            }
        } catch (\Throwable $th) {
            $this->users = [];
        }
    }

    public function save()
    {
        // Validation
        $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'confirm_password' => 'required|same:password',
            'role' => 'required|in:super_admin,admin,simple_user',
        ], [
            'name.required' => 'Le nom est requis',
            'name.min' => 'Le nom doit contenir au moins 3 caractères',
            'email.required' => 'L\'email est requis',
            'email.email' => 'L\'email doit être valide',
            'password.required' => 'Le mot de passe est requis',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères',
            'confirm_password.required' => 'La confirmation du mot de passe est requise',
            'confirm_password.same' => 'Les mots de passe ne correspondent pas',
            'role.required' => 'Le rôle est requis',
        ]);

        try {
            $token = session('token');

            if (!$token) {
                $token = $this->loginAndGetToken();
            }

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->post(env('API_REST') . '/user', [
                            'name' => $this->name,
                            'email' => $this->email,
                            'password' => Hash::make($this->password),
                            'role' => $this->role,
                        ]);

                if ($response->successful()) {
                    $this->success('Utilisateur créé avec succès !');
                    $this->myModal1 = false;
                    $this->reset(['name', 'email', 'password', 'confirm_password', 'role']);
                    $this->fetchUsers();
                } else {
                    $this->error('Erreur lors de la création: ' . $response->body());
                }
            }
        } catch (\Throwable $th) {
            $this->error('Une erreur est survenue: ' . $th->getMessage());
        }
    }

    public function update()
    {
        $rules = [
            'editname' => 'required|min:3',
            'editemail' => 'required|email',
            'editrole' => 'required|in:super_admin,admin,simple_user',
        ];

        $messages = [
            'editname.required' => 'Le nom est requis',
            'editname.min' => 'Le nom doit contenir au moins 3 caractères',
            'editemail.required' => 'L\'email est requis',
            'editemail.email' => 'L\'email doit être valide',
            'editrole.required' => 'Le rôle est requis',
        ];

        if (!empty($this->password) || !empty($this->confirm_password)) {
            $rules['editpassword'] = 'required|min:6';
            $rules['editconfirm_password'] = 'required|same:password';
            $messages['editpassword.required'] = 'Le mot de passe est requis si vous souhaitez le changer';
            $messages['editpassword.min'] = 'Le mot de passe doit contenir au moins 6 caractères';
            $messages['editconfirm_password.required'] = 'La confirmation du mot de passe est requise';
            $messages['editconfirm_password.same'] = 'Les mots de passe ne correspondent pas';
        }

        $this->validate($rules, $messages);

        try {
            $token = session('token');

            if (!$token) {
                $token = $this->loginAndGetToken();
            }

            if ($token) {
                $url = 'https://dev-ia.astucom.com/n8n_cosmia/user/' . $this->selectedUserId;

                $data = [
                    'name' => $this->editname,
                    'email' => $this->editemail,
                    'role' => $this->editrole,
                ];

                if (!empty($this->editpassword)) {
                    $data['password'] = $this->editpassword;
                }
                $response = Http::withHeaders([
                    'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->put($url, $data);

                if ($response->successful()) {
                    $this->success('Utilisateur modifié avec succès !');
                    $this->updateModal = false;
                    $this->reset(['name', 'email', 'password', 'confirm_password', 'role', 'selectedUserId']);
                    $this->fetchUsers();
                } else {
                    $errorMessage = 'Erreur lors de la modification';
                    try {
                        $errorData = $response->json();
                        $errorMessage .= ': ' . ($errorData['message'] ?? $response->body());
                    } catch (\Exception $e) {
                        $errorMessage .= ': ' . $response->body();
                    }

                    $this->error($errorMessage);
                }
            }
        } catch (\Throwable $th) {
            $this->error('Une erreur est survenue: ' . $th->getMessage());
        }
    }

    public function editUser($userId)
    {
        $user = collect($this->users)->firstWhere('id', $userId);

        if ($user) {
            $this->selectedUserId = $user['id'];
            $this->editname = $user['name'];
            $this->editemail = $user['email'];
            $this->editrole = $user['role'];

            $this->editpassword = '';
            $this->editconfirm_password = '';

            $this->updateModal = true;

        } else {
            $this->error('Utilisateur introuvable');
        }
    }



    private function loginAndGetToken()
    {
    }

}; ?>

<div class="mx-auto w-full">
    <x-header title="Utilisateurs" subtitle="Gestion d'utilisateur" separator>
        <x-slot:middle class="!justify-end">

        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-user-plus" class="btn-primary" @click="$wire.myModal1 = true" label="Ajouter utilisateur" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @forelse($users as $user)
            <div
                wire:click="editUser({{ $user['id'] }})"
                class="relative flex items-center space-x-3 rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-xs focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 hover:border-gray-400 cursor-pointer transition-all">
                <div class="shrink-0">
                    <div class="size-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-semibold">
                        {{ strtoupper(substr($user['name'], 0, 2)) }}
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="focus:outline-hidden">
                        <p class="text-sm font-medium text-gray-900">{{ $user['name'] }}</p>
                        <p class="truncate text-sm text-gray-500">{{ $user['email'] }}</p>
                        <p class="truncate text-xs text-indigo-600 mt-1">{{ ucfirst(str_replace('_', ' ', $user['role'])) }}</p>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-2 text-center py-8 text-gray-500">
                Aucun utilisateur trouvé
            </div>
        @endforelse
    </div>

    <x-modal wire:model="myModal1" title="Ajouter un utilisateur" class="backdrop-blur">
        <x-form wire:submit="save">
            <fieldset class="fieldset w-full">
                <legend class="fieldset-legend">Rôle</legend>
                <select class="select w-full" wire:model="role">
                    <option value="" disabled selected>Choisir un rôle</option>
                    @if($role == 'super_admin')
                    <option value="super_admin">Super Admin</option>
                    @endif
                    <option value="admin">Admin</option>
                    <option value="simple_user">Utilisateur simple</option>
                </select>
                @error('role') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </fieldset>
            
            <x-input label="Nom / Prénoms" wire:model="name" />
            <x-input label="Mail" wire:model="email" type="email" />
            <x-password label="Mot de passe" hint="Mot de passe pour l'utilisateur" wire:model="password" clearable />
            <x-password label="Confirmer mot de passe" hint="Confirmer le mot de passe" wire:model="confirm_password" clearable />

            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.myModal1 = false" />
                <x-button label="Sauvegarder" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

<x-modal wire:model="updateModal" title="Modifier l'utilisateur" separator>
    <x-form wire:submit="update">
        <x-input 
            label="Nom" 
            wire:model="editname"
            placeholder="Nom complet de l'utilisateur"
            icon="o-user"
            hint="Minimum 3 caractères"
        />

        <x-input 
            label="Email" 
            wire:model="editemail"
            type="email"
            placeholder="email@exemple.com"
            icon="o-envelope"
            hint="L'email ne peut pas être modifié"
        />

        <x-select label="Rôle" wire:model="editrole" :options="array_filter([
                $role == 'super_admin' ? ['id' => 'super_admin', 'name' => 'Super Administrateur'] : null,
                ['id' => 'admin', 'name' => 'Administrateur'],
                ['id' => 'simple_user', 'name' => 'Utilisateur simple']
            ])" option-value="id" option-label="name" placeholder="Sélectionner un rôle"
            icon="o-shield-check" hint="Le rôle ne peut pas être modifié" />

        <x-slot:actions>
            <x-button label="Annuler" @click="$wire.updateModal = false" />
            <x-button label="Modifier" class="btn-primary" type="submit" spinner="update" />
        </x-slot:actions>
    </x-form>
</x-modal>
</div>