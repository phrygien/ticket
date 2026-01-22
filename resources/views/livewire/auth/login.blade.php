<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public array $errorList = [];
    public string $errorMessage = '';

    public function login()
    {
        $this->validate();

        try {
            $response = Http::withHeaders([
                'x-secret-key' => env('X_SECRET_KEY'),
                'Accept'       => 'application/json',
            ])->post(env('API_REST') .'/auth/login', [
                'email'    => $this->email,
                'password' => $this->password,
            ]);

            $data = $response->json();

            if (!empty($data['token'])) {
                session([
                'token' => $data['token'],
                'name'  => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'role'  => $data['role'] ?? null,
            ]);
                return redirect()->route('project.index');
            }

            $this->errorMessage = $data['error'] ?? 'Identifiants incorrects.';

        } catch (\Throwable $e) {
            $this->errorMessage = 'Erreur de connexion au serveur : ' . $e->getMessage();
        }
    }

    protected function handleErrors(array $data): void
    {
        $errors = $data['data']['error'] ?? null;
        $rootError = $data['error'] ?? null;

        if (is_array($errors)) {
            $this->errorList = collect($errors)->flatten()->toArray();
            $this->errorMessage = '';
        } elseif ($rootError) {
            $this->errorList = [];
            $this->errorMessage = $rootError;
        } else {
            $this->errorList = [];
            $this->errorMessage = $errors ?? ($data['msg'] ?? 'Erreur de connexion.');
        }
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
};
?>


<div class="flex h-screen w-screen">
    <div class="flex-1 flex justify-center items-center bg-white">
        <div class="w-96 max-w-full space-y-6 px-6">
            <!-- Logo -->
            <div class="flex justify-center opacity-50">
                <a href="/" class="group flex items-center gap-3">
                    <svg class="h-4 text-zinc-800" viewBox="0 0 18 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g>
                            <line x1="1" y1="5" x2="1" y2="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <line x1="5" y1="1" x2="5" y2="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <line x1="9" y1="5" x2="9" y2="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <line x1="13" y1="1" x2="13" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <line x1="17" y1="5" x2="17" y2="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </g>
                    </svg>
                    <span class="text-xl font-bold text-zinc-800">COSM</span> <span class="text-xl font-bold text-amber-800">IA</span>
                </a>
            </div>


            <h2 class="text-center text-2xl font-bold text-gray-900">Bienvenue à nouveau</h2>

            @if ($errorList)
                <div class="bg-red-100 text-red-700 text-sm px-4 py-2 rounded space-y-1">
                    @foreach ($errorList as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @elseif ($errorMessage)
                <div class="bg-red-100 text-red-700 text-sm px-4 py-2 rounded">
                    {{ $errorMessage }}
                </div>
            @endif



            <x-form wire:submit="login">
                <x-input label="Email" wire:model.live="email" placeholder="" icon="o-user" hint="Votre adresse email" />

                <x-password label="Mot de passe" wire:model.lazy="password" placeholder="" clearable hint="Votre mot de passe" />

                <x-slot:actions>
                    <x-button label="Connexion" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md text-sm" type="submit" spinner="login" />
                </x-slot:actions>
            </x-form>

        </div>
    </div>

    <div class="flex-1 hidden lg:flex">
        <div class="relative h-full w-full bg-zinc-900 text-white flex flex-col justify-end items-start p-16"
             style="background-image: url('https://images.pexels.com/photos/8850706/pexels-photo-8850706.jpeg'); background-size: cover; background-position: center;">

            <blockquote class="mb-6 italic font-light text-2xl xl:text-3xl">
                “Gérez vos tickets plus efficacement que jamais grâce à une solution intuitive et performante.”
            </blockquote>

            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-white flex items-center justify-center">
                    <svg class="w-8 h-8 text-zinc-800" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 11l-3 3-1.5-1.5L3 16l3 3 5-5-2-2zm0-6l-3 3-1.5-1.5L3 10l3 3 5-5-2-2zm5 2h7v2h-7V7zm0 6h7v2h-7v-2zm0 6h7v2h-7v-2z"/>
                    </svg>

                </div>

                <div>
                    <div class="text-lg font-medium">Task Management</div>
                    <div class="text-sm text-zinc-300">Astucom - Communication - LTD</div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- @push('script') -->

<script>
    (function () {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        // Option 1 : Cookie (simple et efficace)
        document.cookie = "timezone=" + tz + "; path=/";
    })();
</script>

<!-- @endpush -->
