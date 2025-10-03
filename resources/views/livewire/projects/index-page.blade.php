<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {

    public $projects = [];
    public $chartData = []; 
    public string $ticketStatus = 'all';
    public $daterange = 0;

    public function mount(): void
    {
        $this->allProject();
        $this->loadTicketSummary();
    }

    public function updatedTicketStatus()
    {
        $this->refreshChart();
    }

    public function updatedDaterange()
    {
        if (!is_numeric($this->daterange) || $this->daterange < 1) {
            $this->daterange = 0;
        }
        $this->refreshChart();
    }

    private function refreshChart()
    {
        $this->loadTicketSummary();
        $this->dispatch('chart-updated', [
            'labels' => $this->chartData['labels'] ?? [],
            'values' => $this->chartData['values'] ?? [],
            'status' => $this->ticketStatus,
            'days' => $this->daterange
        ]);
    }

    public function allProject()
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
                ])->get(env('API_REST') . '/project');

                if ($response->successful()) {
                    $this->projects = $response->json();
                }
            }
        } catch (\Throwable $th) {
            $this->projects = [];
        }
    }

    public function loadTicketSummary()
    {
        try {
            $token = session('token') ?: $this->loginAndGetToken();

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post(env('API_REST') .'/dash/getticketsummary', [
                    "ticket_status" => $this->ticketStatus === 'all' ? null : $this->ticketStatus,
                    "date_range" => (int) $this->daterange,
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
                } else {
                    \Log::warning('API Error: ' . $response->status() . ' - ' . $response->body());
                    $this->chartData = ['labels' => [], 'values' => []];
                }
            }
        } catch (\Throwable $th) {
            \Log::error('Exception in loadTicketSummary: ' . $th->getMessage());
            $this->chartData = ['labels' => [], 'values' => []];
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
    <x-header title="Projet" subtitle="Nos projets sur N8N" separator />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div class="grid grid-cols-1 space-y-6">
            @foreach($projects as $project)
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <h2 class="sr-only" id="profile-overview-title">Profile Overview</h2>
                    <div class="bg-white p-6">
                        <div class="sm:flex sm:items-center sm:justify-between">
                            <div class="sm:flex sm:space-x-5">
                                <div class="mt-4 text-center sm:mt-0 sm:pt-1 sm:text-left">
                                    <p class="text-sm font-medium text-gray-600">{{ $project['name'] }}</p>
                                    <p class="text-xl font-bold text-gray-900 sm:text-2xl">{{ $project['code'] }}</p>
                                </div>
                            </div>
                            <div class="mt-5 flex justify-center sm:mt-0">
                                <x-button icon-right="o-arrow-long-right" href="{{ route('project.view', ['id' => $project['id']]) }}" wire:navigate label="en savoir plus" class="btn-primary btn-outline" />
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 divide-y divide-gray-200 border-t border-gray-200 bg-gray-50 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div class="px-6 py-5 text-center text-sm font-medium">
                            <span class="text-gray-900">{{ $project['pending_ticket'] }}</span>
                            <span class="text-gray-600">ticket en attente</span>
                        </div>
                        <div class="px-6 py-5 text-center text-sm font-medium">
                            <span class="text-gray-900">{{ $project['in_progress_ticket'] }}</span>
                            <span class="text-gray-600">ticket en cours</span>
                        </div>
                        <div class="px-6 py-5 text-center text-sm font-medium">
                            <span class="text-gray-900">{{ $project['closed_ticket'] }}</span>
                            <span class="text-gray-600">ticket fermé</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>


        <x-card subtitle="Statistiques par période">
            <div class="mb-5 flex items-end gap-6">
                
                <fieldset class="fieldset flex-1">
                    <legend class="fieldset-legend">Filtrer par statut</legend>
                    <select class="select w-full" wire:model.live="ticketStatus">
                        <option value="all">Tous les statuts</option>
                        <option value="en attente">En attente</option>
                        <option value="en cours">En cours</option>
                        <option value="cloture">Clôturé</option>
                    </select>
                    <span class="label">Optionnel</span>
                </fieldset>

                                
                <fieldset class="fieldset flex-1">
                    <legend class="fieldset-legend">Période (jours)</legend>
                    <select class="select w-full" wire:model.live.debounce.500ms="daterange" >
                        <option value="0">7 Jours</option>
                        <option value="1">15 Jours</option>
                        <option value="2">1 Mois</option>
                        <option value="3">3 Mois</option>
                        <option value="4">6 Mois</option>
                        <option value="5">1 Ans</option>
                    </select>
                    <span class="label">Optionnel</span>
                </fieldset>

            </div>

            <div wire:loading wire:target="ticketStatus,daterange" class="flex items-center justify-center py-4">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                <span class="ml-2 text-sm text-gray-500">Mise à jour en cours...</span>
            </div>

            <div wire:ignore>
                <canvas id="ticketLineChart"></canvas>
            </div>

            <div class="mt-4 text-xs text-gray-500 border-t pt-3">
                <p>
                    Filtres actifs: 
                    <span class="font-medium">{{ $ticketStatus === 'all' ? 'Tous les statuts' : ucfirst($ticketStatus) }}</span>
                    • 
                    @php
                        $labels = [
                            0 => '7 jours',
                            1 => '15 jours',
                            2 => '1 mois',
                            3 => '3 mois',
                            4 => '6 mois',
                            5 => '1 an',
                        ];
                    @endphp

                    <span class="font-medium">
                        {{ $labels[$daterange] ?? '' }}
                    </span>

                </p>
            </div>
        </x-card>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let chartInstance = null;

    const dayLabels = {
        0: "7 jours",
        1: "15 jours",
        2: "1 mois",
        3: "3 mois",
        4: "6 mois",
        5: "1 an"
    };

    function renderTicketChart(labels, values, status = 'all', days = 1) {
        const ctx = document.getElementById('ticketLineChart')?.getContext('2d');
        if (!ctx) return;

        if (chartInstance) {
            chartInstance.destroy();
        }

        let datasetLabel = 'Tickets';
        if (status !== 'all') {
            datasetLabel += ` (${status})`;
        }

        datasetLabel += ` - ${dayLabels[days] ?? ""}`;

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: datasetLabel,
                    data: values,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: { 
                        title: { 
                            display: true, 
                            text: 'Période' 
                        },
                        grid: {
                            display: true,
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    y: { 
                        beginAtZero: true, 
                        title: { 
                            display: true, 
                            text: 'Nombre de tickets' 
                        },
                        grid: {
                            display: true,
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });

        ctx.canvas.style.height = '300px';
    }

    function updateChart(labels, values, status = 'all', days = 1) {
        if (chartInstance) {
            let datasetLabel = 'Tickets';
            if (status !== 'all') {
                datasetLabel += ` (${status})`;
            }

            datasetLabel += ` - ${dayLabels[days] ?? ""}`;

            chartInstance.data.labels = labels;
            chartInstance.data.datasets[0].data = values;
            chartInstance.data.datasets[0].label = datasetLabel;
            chartInstance.update('active');
        } else {
            renderTicketChart(labels, values, status, days);
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        const initialLabels = @json($chartData['labels'] ?? []);
        const initialValues = @json($chartData['values'] ?? []);
        const initialStatus = @json($ticketStatus);
        const initialDays = @json($daterange);
        
        renderTicketChart(initialLabels, initialValues, initialStatus, initialDays);
    });

    document.addEventListener("livewire:navigated", () => {
        const initialLabels = @json($chartData['labels'] ?? []);
        const initialValues = @json($chartData['values'] ?? []);
        const initialStatus = @json($ticketStatus);
        const initialDays = @json($daterange);
        
        renderTicketChart(initialLabels, initialValues, initialStatus, initialDays);
    });

    document.addEventListener("livewire:init", () => {
        Livewire.on("chart-updated", (event) => {
            const [data] = event;
            updateChart(data.labels, data.values, data.status, data.days);
        });
    });
</script>
