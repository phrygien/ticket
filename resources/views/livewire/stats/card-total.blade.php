<?php

use Illuminate\Support\Facades\Http;
use Livewire\Volt\Component;

new class extends Component {
    public $redundantCount = 0;
    public $totalEmails = 0;

    public function mount(): void
    {
        $this->loadRedundantData();
    }

    public function loadRedundantData(): void
    {

        try {
            $token = session('token') ?: $this->loginAndGetToken();

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post('https://dev-ia.astucom.com/n8n_cosmia/dash/getRedudantRequest', [
                            'ticket_status' => 'all',
                            'date_range' => 1,
                        ]);

                if ($response->successful()) {
                    $data = $response->json();

                    // Récupérer les valeurs depuis details[0]
                    $details = $data['details'][0] ?? [];
                    $this->redundantCount = $details['nb_reccurent'] ?? 0;
                    $this->totalEmails = $details['total_mail_trigered'] ?? 0;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error loading redundant data: ' . $e->getMessage());
            $this->redundantCount = 0;
            $this->totalEmails = 0;
        } finally {
            $this->loadingRedundant = false;
        }
    }

}; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-3 mb-3">
    <!-- Stats Mail avec Meme Probleme !-->
    <x-stat title="Nombre d'envois récurrents" value="{{ number_format($this->redundantCount ?? 0) }}" icon="o-envelope"
        color="text-primary" />
    
    <x-stat title="Total des mails envoyés automatiquement" value="{{ number_format($this->totalEmails ?? 0) }}"
        icon="o-envelope" color="text-pink-500" />
</div>
