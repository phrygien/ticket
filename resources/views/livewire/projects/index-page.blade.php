<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {

    public $projects = [];
    public $chartData = [];
    public string $ticketStatus = 'all';
    public $daterange = 0;

    // Donut chart properties
    public array $donutData = [];
    public bool $loadingDonut = false;
    public string $selectedMonth;
    public string $selectedYear;
    public array $months = [];
    public array $years = [];
    public $ticketPartitionData = [];
    public bool $loadingPartition = false;

    public function mount(): void
    {
        $this->selectedMonth = date('m');
        $this->selectedYear = date('Y');

        $this->initializeFilters();
        $this->allProject();
        $this->loadTicketSummary();
        $this->loadDonutData();
        $this->loadTicketPartition();
    }

    public function initializeFilters(): void
    {
        // Mois
        $this->months = [
            ['id' => 'all', 'name' => 'Tous les mois'],
            ['id' => '01', 'name' => 'Janvier'],
            ['id' => '02', 'name' => 'Février'],
            ['id' => '03', 'name' => 'Mars'],
            ['id' => '04', 'name' => 'Avril'],
            ['id' => '05', 'name' => 'Mai'],
            ['id' => '06', 'name' => 'Juin'],
            ['id' => '07', 'name' => 'Juillet'],
            ['id' => '08', 'name' => 'Août'],
            ['id' => '09', 'name' => 'Septembre'],
            ['id' => '10', 'name' => 'Octobre'],
            ['id' => '11', 'name' => 'Novembre'],
            ['id' => '12', 'name' => 'Décembre'],
        ];

        // Années
        $currentYear = date('Y');
        $this->years = [
            ['id' => 'all', 'name' => 'Toutes les années']
        ];
        for ($i = 0; $i < 5; $i++) {
            $year = $currentYear - $i;
            $this->years[] = ['id' => (string) $year, 'name' => (string) $year];
        }
    }

    public function loadDonutData(): void
    {
        $this->loadingDonut = true;

        try {
            $token = session('token') ?: $this->loginAndGetToken();

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post('https://dev-ia.astucom.com/n8n_cosmia/dash/getdonutSummary', [
                            'month' => $this->selectedMonth,
                            'year' => $this->selectedYear,
                        ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $this->donutData = $data['details'] ?? [];

                    $this->dispatch('donut-chart-updated', [
                        'data' => $this->donutData,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error loading donut data: ' . $e->getMessage());
            $this->donutData = [];
        } finally {
            $this->loadingDonut = false;
        }
    }

    public function updatedSelectedMonth(): void
    {
        $this->loadDonutData();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadDonutData();
    }

    public function getTotalRequests(): int
    {
        return array_sum(array_column($this->donutData, 'nb'));
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
        $this->loadDonutData();
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
                ])->post(env('API_REST') . '/dash/getticketsummary', [
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

    public function loadTicketPartition(): void
    {
        $this->loadingPartition = true;

        try {
            $token = session('token') ?: $this->loginAndGetToken();

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post('https://dev-ia.astucom.com/n8n_cosmia/dash/getTicketPartitionSummary', [
                            'month' => 'all',
                            'year' => 'all',
                        ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $rawData = $data['details'] ?? [];

                    $this->ticketPartitionData = $rawData;

                    $this->dispatch('ticket-partition-updated', [
                        'data' => $rawData
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error loading ticket partition: ' . $e->getMessage());
            $this->ticketPartitionData = [];
        } finally {
            $this->loadingPartition = false;
        }
    }

    public function getPartitionTotal(): int
    {
        $total = 0;
        foreach ($this->ticketPartitionData as $row) {
            foreach ($row as $key => $value) {
                if ($key !== 'date' && is_numeric($value)) {
                    $total += (int) $value;
                }
            }
        }
        return $total;
    }

    public function with(): array
    {
        return [
            'page_title' => 'Tous les projets',
            'totalRequests' => $this->getTotalRequests(),
            'partitionTotal' => $this->getPartitionTotal(),
        ];
    }

};
?>

<div class="mx-auto max-w-9xl">
    <x-header title="Projet" subtitle="Nos projets sur N8N" separator />


    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
                                <x-button icon-right="o-arrow-long-right"
                                    href="{{ route('project.view', ['id' => $project['id']]) }}" wire:navigate
                                    label="en savoir plus" class="btn-primary btn-outline" />
                            </div>
                        </div>
                    </div>
                    <div
                        class="grid grid-cols-1 divide-y divide-gray-200 border-t border-gray-200 bg-gray-50 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
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

    <div class="mt-3 grid grid-cols-1">
        <x-card subtitle="Statistiques par période" separator>
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
                    <select class="select w-full" wire:model.live.debounce.500ms="daterange">
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
                <div id="ticketLineChart" style="height: 300px;"></div>
            </div>

            <div class="mt-4 text-xs text-gray-500 border-t pt-3">
                <p>
                    Filtres actifs:
                    <span
                        class="font-medium">{{ $ticketStatus === 'all' ? 'Tous les statuts' : ucfirst($ticketStatus) }}</span>
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

    <div class="mt-3 grid grid-cols-1">
        <x-card subtitle="Classification par catégorie" separator>
            <div wire:loading wire:target="loadTicketPartition" class="flex justify-center items-center h-96">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                <span class="ml-3 text-gray-500">Chargement...</span>
            </div>

            <div wire:ignore>
                <div id="ticketPartitionChart" style="height: 400px;"></div>
            </div>

            @if(!empty($ticketPartitionData))
                <div class="mt-4 text-xs text-gray-500 border-t pt-3">
                    <p class="font-medium">
                        Total: {{ $partitionTotal }} tickets classifiés
                    </p>
                </div>
            @endif
        </x-card>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- <div class="grid grid-cols-1 space-y-6">
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
                                <x-button icon-right="o-arrow-long-right"
                                    href="{{ route('project.view', ['id' => $project['id']]) }}" wire:navigate
                                    label="en savoir plus" class="btn-primary btn-outline" />
                            </div>
                        </div>
                    </div>
                    <div
                        class="grid grid-cols-1 divide-y divide-gray-200 border-t border-gray-200 bg-gray-50 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
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
        </div> --}}


        {{-- <x-card subtitle="Statistiques par période" separator>
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
                    <select class="select w-full" wire:model.live.debounce.500ms="daterange">
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
                <div id="ticketLineChart" style="height: 300px;"></div>
            </div>

            <div class="mt-4 text-xs text-gray-500 border-t pt-3">
                <p>
                    Filtres actifs:
                    <span
                        class="font-medium">{{ $ticketStatus === 'all' ? 'Tous les statuts' : ucfirst($ticketStatus) }}</span>
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

        <x-card subtitle="Classification par catégorie" separator>
            <div wire:loading wire:target="loadTicketPartition" class="flex justify-center items-center h-96">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                <span class="ml-3 text-gray-500">Chargement...</span>
            </div>

            <div wire:ignore>
                <div id="ticketPartitionChart" style="height: 400px;"></div>
            </div>

            @if(!empty($ticketPartitionData))
                <div class="mt-4 text-xs text-gray-500 border-t pt-3">
                    <p class="font-medium">
                        Total: {{ $partitionTotal }} tickets classifiés
                    </p>
                </div>
            @endif
        </x-card> --}}

        <div class="mt-3 grid-cols-1 md:grid-cols-2 gap-6">
            <x-card subtitle="Répartition selon le type de demande">
                {{-- Filtres --}}
                <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-base-200 rounded-lg">
                    <x-select label="Mois" icon="o-calendar" :options="$months" wire:model.live="selectedMonth" />
                    <x-select label="Année" icon="o-calendar-days" :options="$years" wire:model.live="selectedYear" />
                    <div class="flex items-end">
                        <x-button icon="o-arrow-path" wire:click="loadDonutData" spinner="loadDonutData"
                            class="btn-soft btn-error w-full" />
                    </div>
                </div>
            
                @if($loadingDonut)
                    <div class="flex justify-center items-center h-64">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                        <span class="ml-3 text-gray-500">Chargement...</span>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-6">
                        <div class="text-center mb-6">
                            <div class="text-4xl font-bold text-error">{{ $totalRequests }}</div>
                            <div class="text-sm text-gray-500">Demandes totales</div>
                        </div>

                        <div wire:ignore>
                            <div id="donutChart" style="height: 400px; width: 100%;"></div>
                        </div>
                    </div>
                @endif
            </x-card>
        </div>

    </div>
</div>

<script src="https://code.highcharts.com/highcharts.js"></script>

<script>
    let lineChart = null;
    let donutChart = null;
    let barChart = null;

    const dayLabels = {
        0: "7 jours",
        1: "15 jours",
        2: "1 mois",
        3: "3 mois",
        4: "6 mois",
        5: "1 an"
    };

    // Noms lisibles pour les catégories
    const categoryLabels = {
        'suivi_commande': 'Suivi commande',
        'colis_non_recu': 'Colis non reçu',
        'paiement': 'Paiement',
        'facture_non_recu': 'Facture non reçue',
        'produit_defectueux': 'Produit défectueux',
        'retour_retractation': 'Retour/Rétractation',
        'demande_specifique': 'Demande spécifique',
        'colis_vide': 'Colis vide'
    };

    // Couleurs pour chaque catégorie
    const categoryColors = {
        'suivi_commande': '#3b82f6',
        'colis_non_recu': '#ef4444',
        'paiement': '#10b981',
        'facture_non_recu': '#f59e0b',
        'produit_defectueux': '#8b5cf6',
        'retour_retractation': '#ec4899',
        'demande_specifique': '#06b6d4',
        'colis_vide': '#f97316'
    };

    const highchartsColors = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', 
        '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'
    ];

    // ===== LINE CHART =====
    function renderTicketChart(labels, values, status = 'all', days = 0) {
        let seriesName = 'Tickets';
        if (status !== 'all') {
            seriesName += ` (${status})`;
        }
        seriesName += ` - ${dayLabels[days] ?? ""}`;

        const container = document.getElementById('ticketLineChart');
        if (!container) return;

        if (lineChart) {
            lineChart.destroy();
        }

        lineChart = Highcharts.chart('ticketLineChart', {
            chart: {
                type: 'area'
            },
            title: {
                text: null
            },
            xAxis: {
                categories: labels,
                title: {
                    text: 'Période'
                }
            },
            yAxis: {
                title: {
                    text: 'Nombre de tickets'
                },
                allowDecimals: false
            },
            legend: {
                enabled: true
            },
            plotOptions: {
                area: {
                    fillColor: {
                        linearGradient: {
                            x1: 0,
                            y1: 0,
                            x2: 0,
                            y2: 1
                        },
                        stops: [
                            [0, 'rgba(59, 130, 246, 0.3)'],
                            [1, 'rgba(59, 130, 246, 0.05)']
                        ]
                    },
                    marker: {
                        radius: 4
                    },
                    lineWidth: 2,
                    states: {
                        hover: {
                            lineWidth: 3
                        }
                    },
                    threshold: null
                }
            },
            series: [{
                name: seriesName,
                data: values,
                color: '#3b82f6'
            }],
            credits: {
                enabled: false
            },
            tooltip: {
                shared: true,
                valueSuffix: ' tickets'
            }
        });
    }

    function updateChart(labels, values, status = 'all', days = 0) {
        if (lineChart) {
            let seriesName = 'Tickets';
            if (status !== 'all') {
                seriesName += ` (${status})`;
            }
            seriesName += ` - ${dayLabels[days] ?? ""}`;

            lineChart.update({
                xAxis: {
                    categories: labels
                },
                series: [{
                    name: seriesName,
                    data: values
                }]
            });
        } else {
            renderTicketChart(labels, values, status, days);
        }
    }

    // ===== LINE CHART (Classification) =====
    function renderStackedBarChart(rawData) {
        const container = document.getElementById('ticketPartitionChart');
        if (!container) {
            console.log('Container ticketPartitionChart not found');
            return;
        }

        if (barChart) {
            barChart.destroy();
        }

        if (!rawData || rawData.length === 0) {
            console.log('No data available for classification chart');
            return;
        }

        // Extract dates for x-axis labels
        const dates = rawData.map(item => {
            const d = new Date(item.date);
            return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
        });

        // Extract categories (excluding 'date')
        const categories = Object.keys(rawData[0]).filter(key => key !== 'date');

        // Create series for each category
        const series = categories.map(category => ({
            name: categoryLabels[category] || category,
            data: rawData.map(item => parseInt(item[category]) || 0),
            color: categoryColors[category] || '#999999'
        }));

        barChart = Highcharts.chart('ticketPartitionChart', {
            chart: {
                type: 'line'
            },
            title: {
                text: null
            },
            yAxis: {
                title: {
                    text: 'Nombre de tickets'
                },
                allowDecimals: false
            },
            xAxis: {
                categories: dates,
                accessibility: {
                    rangeDescription: 'Période de temps'
                }
            },
            legend: {
                layout: 'vertical',
                align: 'right',
                verticalAlign: 'middle'
            },
            plotOptions: {
                series: {
                    label: {
                        connectorAllowed: false
                    },
                    marker: {
                        enabled: true,
                        radius: 3
                    }
                }
            },
            series: series,
            credits: {
                enabled: false
            },
            tooltip: {
                shared: true,
                crosshairs: true,
                formatter: function() {
                    let s = '<b>' + this.x + '</b><br/>';
                    let total = 0;
                    this.points.forEach(point => {
                        s += '<span style="color:' + point.color + '">\u25CF</span> ' + 
                             point.series.name + ': ' + point.y + '<br/>';
                        total += point.y;
                    });
                    s += '<b>Total: ' + total + '</b>';
                    return s;
                }
            },
            responsive: {
                rules: [{
                    condition: {
                        maxWidth: 500
                    },
                    chartOptions: {
                        legend: {
                            layout: 'horizontal',
                            align: 'center',
                            verticalAlign: 'bottom'
                        }
                    }
                }]
            }
        });
    }

    // ===== DONUT CHART =====
    function renderDonutChart(data) {
        const container = document.getElementById('donutChart');
        if (!container) {
            console.log('Container donutChart not found');
            return;
        }

        if (donutChart) {
            donutChart.destroy();
        }

        const chartData = data.map((item, index) => ({
            name: item.label_name,
            y: parseInt(item.nb),
            color: highchartsColors[index % highchartsColors.length]
        }));

        donutChart = Highcharts.chart('donutChart', {
            chart: {
                type: 'pie'
            },
            title: {
                text: null
            },
            plotOptions: {
                pie: {
                    innerSize: '50%',
                    dataLabels: {
                        enabled: true,
                        format: '<b>{point.name}</b>: {point.percentage:.1f}%'
                    },
                    showInLegend: true
                }
            },
            // legend: {
            //     align: 'right',
            //     verticalAlign: 'middle',
            //     layout: 'vertical'
            // },
            series: [{
                name: 'Demandes',
                data: chartData
            }],
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: '<b>{point.y}</b> ({point.percentage:.1f}%)'
            }
        });
    }

    function initDonutChart() {
        setTimeout(() => {
            const donutData = @json($donutData ?? []);
            if (donutData && donutData.length > 0) {
                renderDonutChart(donutData);
            }
        }, 100);
    }

    function initStackedBarChart() {
        setTimeout(() => {
            const partitionData = @json($ticketPartitionData ?? []);
            if (partitionData && partitionData.length > 0) {
                renderStackedBarChart(partitionData);
            }
        }, 100);
    }

    // ===== INITIALIZATION =====
    document.addEventListener("DOMContentLoaded", () => {
        const initialLabels = @json($chartData['labels'] ?? []);
        const initialValues = @json($chartData['values'] ?? []);
        const initialStatus = @json($ticketStatus);
        const initialDays = @json($daterange);
        renderTicketChart(initialLabels, initialValues, initialStatus, initialDays);

        initDonutChart();
        initStackedBarChart();
    });

    document.addEventListener("livewire:navigated", () => {
        const initialLabels = @json($chartData['labels'] ?? []);
        const initialValues = @json($chartData['values'] ?? []);
        const initialStatus = @json($ticketStatus);
        const initialDays = @json($daterange);
        renderTicketChart(initialLabels, initialValues, initialStatus, initialDays);

        initDonutChart();
        initStackedBarChart();
    });

    document.addEventListener("livewire:init", () => {
        Livewire.on("chart-updated", (event) => {
            const [data] = event;
            updateChart(data.labels, data.values, data.status, data.days);
        });

        Livewire.on("donut-chart-updated", (event) => {
            const [data] = event;
            renderDonutChart(data.data);
        });

        Livewire.on("ticket-partition-updated", (event) => {
            const [data] = event;
            renderStackedBarChart(data.data);
        });
    });
</script>