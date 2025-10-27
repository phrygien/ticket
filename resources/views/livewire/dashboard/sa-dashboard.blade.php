<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public array $donutData = [];
    public bool $loading = false;
    public string $selectedMonth;
    public string $selectedYear;
    
    // Options pour les filtres
    public array $months = [];
    public array $years = [];

    public function mount(): void
    {
        // Initialiser avec le mois et l'année en cours
        $this->selectedMonth = date('m');
        $this->selectedYear = date('Y');
        
        $this->initializeFilters();
        $this->loadDonutData();
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
            $this->years[] = ['id' => (string)$year, 'name' => (string)$year];
        }
    }

    public function loadDonutData(): void
    {
        $this->loading = true;
$token = session('token');
        try {
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
            } else {
                $this->error('Erreur lors du chargement des données');
            }
        } catch (\Exception $e) {
            $this->error('Erreur: ' . $e->getMessage());
        } finally {
            $this->loading = false;
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

    public function getChartData(): array
    {
        return [
            'labels' => array_column($this->donutData, 'label_name'),
            'values' => array_column($this->donutData, 'nb'),
        ];
    }

    public function getTotalRequests(): int
    {
        return array_sum(array_column($this->donutData, 'nb'));
    }

    public function with(): array
    {
        return [
            'chartData' => $this->getChartData(),
            'totalRequests' => $this->getTotalRequests(),
        ];
    }
}; ?>

<div>
<x-header title="Statistiques" subtitle="Suivez les indicateurs clés en temps réel" separator>
    <x-slot:middle>
        <div class="flex items-center justify-end gap-3">
            <x-select 
                label="Mois" 
                icon="o-calendar" 
                :options="$months" 
                wire:model.live="selectedMonth" 
                class="w-40"
            />
            <x-select 
                label="Année" 
                icon="o-calendar-days" 
                :options="$years" 
                wire:model.live="selectedYear" 
                class="w-40"
            />
        </div>
    </x-slot:middle>

    <x-slot:actions>
        <x-button 
            label="Rafraîchir" 
            icon="o-arrow-path" 
            wire:click="loadDonutData" 
            spinner="loadDonutData"
            class="btn-primary w-full md:w-auto"
        />
    </x-slot:actions>
</x-header>


    {{-- Filtres --}}
    {{-- <div class="mb-6">
        <x-card title="Filtres" class="bg-base-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-select 
                    label="Mois" 
                    icon="o-calendar" 
                    :options="$months" 
                    wire:model.live="selectedMonth" 
                />
                <x-select 
                    label="Année" 
                    icon="o-calendar-days" 
                    :options="$years" 
                    wire:model.live="selectedYear" 
                />
                <div class="flex items-end">
                    <x-button 
                        label="Rafraîchir" 
                        icon="o-arrow-path" 
                        wire:click="loadDonutData" 
                        spinner="loadDonutData"
                        class="btn-primary w-full"
                    />
                </div>
            </div>
        </x-card>
    </div> --}}

    <div class="grid grid-cols-1 gap-4">
        {{-- Pie Chart avec légende --}}
        <x-card subtitle="Répartition selon le type de demande">
            @if($loading)
                <div class="flex justify-center items-center h-64">
                    <x-loading class="loading-lg" />
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Chart à gauche --}}
                    <div class="flex flex-col items-center justify-center">
                        <div class="text-center mb-4">
                            <div class="text-3xl font-bold text-primary">{{ $totalRequests }}</div>
                            <div class="text-sm text-gray-500">Demandes totales</div>
                        </div>
                        
                        <div class="w-full max-w-sm">
                            <canvas id="donutChart"></canvas>
                        </div>
                    </div>

                    {{-- Légende à droite --}}
                    <div class="flex flex-col justify-center">
                        <div class="space-y-3">
                            @foreach($donutData as $item)
                                <div class="flex items-center justify-between p-3 hover:bg-base-200 rounded-lg transition-colors">
                                    <div class="flex items-center gap-3 flex-1">
                                        <div class="w-5 h-5 rounded flex-shrink-0" style="background-color: {{ ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'][$loop->index % 8] }}"></div>
                                        <span class="text-sm font-medium">{{ $item['label_name'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 flex-shrink-0">
                                        <span class="font-bold text-lg">{{ $item['nb'] }}</span>
                                        <span class="text-xs text-gray-500 bg-base-300 px-2 py-1 rounded min-w-[3rem] text-center">
                                            {{ $totalRequests > 0 ? round(($item['nb'] / $totalRequests) * 100, 1) : 0 }}%
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </x-card>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <x-card subtitle="Répartition par traitement de ticket">
            {{-- Votre contenu ici --}}
        </x-card>

        <x-card subtitle="Autres statistiques">
            {{-- Votre contenu ici --}}
        </x-card>
    </div>

    <div class="mt-3">
        <x-card subtitle="Evolutions">
            {{-- Votre contenu ici --}}
        </x-card>
    </div>

    @script
    <script>
        let donutChartInstance = null;

        function getColors() {
            return [
                '#3b82f6', // blue
                '#10b981', // green
                '#f59e0b', // amber
                '#ef4444', // red
                '#8b5cf6', // violet
                '#ec4899', // pink
                '#06b6d4', // cyan
                '#f97316', // orange
            ];
        }

        function renderDonutChart(labels, values) {
            const ctx = document.getElementById('donutChart');
            if (!ctx) {
                console.log('Canvas not found');
                return;
            }

            // Détruire l'ancienne instance si elle existe
            if (donutChartInstance) {
                donutChartInstance.destroy();
            }

            donutChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: getColors(),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        function initChart() {
            setTimeout(() => {
                const chartData = @js($chartData);
                console.log('Chart data:', chartData);
                if (chartData.labels && chartData.labels.length > 0) {
                    renderDonutChart(chartData.labels, chartData.values);
                }
            }, 100);
        }

        // Render après mise à jour Livewire
        Livewire.hook('morph.updated', ({ el, component }) => {
            if (component.id === $wire.__instance.id) {
                initChart();
            }
        });

        // Render au chargement initial
        initChart();
    </script>
    @endscript

    @assets
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @endassets
</div>