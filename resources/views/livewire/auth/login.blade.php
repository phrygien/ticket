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

    /**
     * Handle an incoming authentication request.
     */
    public function login()
    {
        $this->validate();

        try {
            $response = Http::withHeaders([
                'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
                'Accept'       => 'application/json',
            ])->post('https://dev-ia.astucom.com/n8n_cosmia/auth/login', [
                'email'    => $this->email,
                'password' => $this->password,
            ]);

            $data = $response->json();

            // Succès => token trouvé
            if (!empty($data['token'])) {
                session(['token' => $data['token']]);
                return redirect()->route('project.index');
            }

            // Erreur connue (ex: Invalid credentials)
            $this->errorMessage = $data['error'] ?? 'Identifiants incorrects.';

        } catch (\Throwable $e) {
            // Erreur technique (ex: serveur injoignable)
            $this->errorMessage = 'Erreur de connexion au serveur : ' . $e->getMessage();
        }
    }


    /**
     * Gère les erreurs de connexion et met à jour les messages Livewire
     */
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

    /**
     * Ensure the authentication request is not rate limited.
     */
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

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
};
?>


<div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8">
  <div class="sm:mx-auto sm:w-full sm:max-w-sm">
    <img src="https://raw.githubusercontent.com/n8n-io/n8n/master/assets/n8n-logo.png" alt="Your Company" class="mx-auto h-10 w-auto" />
    {{-- <h2 class="mt-10 text-center text-2xl/9 font-bold tracking-tight text-gray-900">{{ __('TICKET - N8N')}}</h2> --}}
  </div>

  <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">

        <!-- Session Status -->
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


    <x-form wire:submit="login" no-separator>
        <x-input label="Email" wire:model="email" />
        <x-input type="password" label="Mot de passe" wire:model="password" />
        <x-slot:actions>
            <x-button label="Login" class="btn-primary" type="submit" spinner="login" />
        </x-slot:actions>
    </x-form>

  </div>
</div>
