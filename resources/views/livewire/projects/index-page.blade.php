<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {

    public $projects = [];
    public $chartData = [];

    public function mount(): void
    {
        $this->allProject();
        $this->loadTicketSummary();
    }

    // Liste des projets
    public function allProject()
    {
        try {
            $token = session('token');

            if (!$token) {
                $token = $this->loginAndGetToken();
            }

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->get('https://dev-ia.astucom.com/n8n_cosmia/project');

                if ($response->successful()) {
                    $this->projects = $response->json();
                }
            }
        } catch (\Throwable $th) {
            $this->projects = [];
        }
    }

    // Charger le résumé des tickets (chart)
    public function loadTicketSummary()
    {
        try {
            $token = session('token');
            if (!$token) {
                $token = $this->loginAndGetToken();
            }

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post('https://dev-ia.astucom.com/n8n_cosmia/dash/getticketsummary', [
                    "ticket_status" => "all",
                    "date_range"    => 1,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (!empty($data['details'])) {
                        $this->chartData = [
                            'labels' => collect($data['details'])->pluck('period')->toArray(),
                            'values' => collect($data['details'])->pluck('nombre')->toArray(),
                        ];

                    } else {
                        $this->chartData = ['labels' => [], 'values' => []];
                    }
                }

            }
        } catch (\Throwable $th) {
            $this->chartData = [];
        }
    }

    public function with(): array
    {
        return [
            'page_title' => 'Tous les projets'
        ];
    }
};
?>

<div class="mx-auto max-w-9xl">
    <x-header title="Projet" subtitle="Nos projet sur N8N" separator />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Liste des projets -->
        <div class="grid grid-cols-1 space-y-6">
            @foreach($projects as $project)
                <div class="bg-white rounded-md shadow-sm sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">{{ $project['code'] }}</h3>
                        <div class="mt-2 max-w-xl text-sm text-gray-500">
                            <p>{{ $project['name'] }}</p>
                        </div>
                        <div class="mt-3 text-sm/6">
                            <a href="{{ route('project.view', ['id' => $project['id']]) }}" wire:navigate
                                class="font-semibold text-indigo-600 hover:text-indigo-500">
                                en savoir plus
                                <span aria-hidden="true"> &rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Chart -->
        <x-card subtitle="Statistics par mois">
            <canvas id="ticketLineChart"></canvas>
        </x-card>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    function renderTicketChart(labels, values) {
        const ctx = document.getElementById('ticketLineChart')?.getContext('2d');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Tickets par période',
                    data: values,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    x: { title: { display: true, text: 'Date' } },
                    y: { beginAtZero: true, title: { display: true, text: 'Nombre de tickets' } }
                }
            }
        });
    }

    // 🔹 Exécuter au premier chargement
    document.addEventListener("DOMContentLoaded", () => {
        renderTicketChart(@json($chartData['labels'] ?? []), @json($chartData['values'] ?? []));
    });

    // 🔹 Réexécuter après navigation Livewire
    document.addEventListener("livewire:navigated", () => {
        renderTicketChart(@json($chartData['labels'] ?? []), @json($chartData['values'] ?? []));
    });
</script>