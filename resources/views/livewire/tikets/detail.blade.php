<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Illuminate\Validation\Rule;
use Mary\Traits\Toast;

new class extends Component {
    use WithFileUploads;

    use Toast;

    public int $ticketId;
    public array $ticketDetails = [];
    public bool $loading = true;

    public string $activeTab = 'description';
    public bool $myModal1 = false;
    public bool $myModal11 = false;
    public bool $myModal12 = false;

    public ?array $selectedMessage = null;

    #[Validate(['photos.*' => 'image|max:1024'])]
    public $photos = [];

    public $destinateur;

    public bool $showDrawer2 = false;

    public $message_txt;

    public bool $showSendmailTab = false;


    public $message_client;

    public $translatedMessage = '';

    public function mount($ticket)
    {
        $this->ticketId = $ticket;
        $this->fetchTicketDetails();
    }

    public function selectMessage(array $message)
    {
        $this->selectedMessage = $message;
        $this->translatedMessage = '';
    }

    public function fetchTicketDetails()
    {
        $token = session('token');
        if (!$token)
            return redirect()->route('login');

        $response = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])
            ->timeout(200)
            ->get(env('API_REST') . "/ticket/{$this->ticketId}");

        if ($response->successful()) {
            $this->ticketDetails = $response->json();
            $this->destinateur = $response['details'][0]['original_client_mail'];
        }

        $this->loading = false;
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    function formatMessage($message)
    {
        // Extraire les liens HTML existants et les remplacer temporairement
        $links = [];
        $message = preg_replace_callback(
            '~<a\s+href=["\']([^"\']+)["\'][^>]*>([^<]+)</a>~i',
            function ($matches) use (&$links) {
                $placeholder = '___LINK_' . count($links) . '___';
                $links[] = [
                    'url' => $matches[1],
                    'text' => $matches[2]
                ];
                return $placeholder;
            },
            $message
        );

        // Supprimer toutes les autres balises HTML
        $message = strip_tags($message);

        // Restaurer les liens avec le bon format
        foreach ($links as $index => $link) {
            $placeholder = '___LINK_' . $index . '___';
            $href = preg_match('~^https?://~i', $link['url']) ? $link['url'] : "http://{$link['url']}";
            $replacement = '<a href="' . e($href) . '" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">' . e($link['text']) . '</a>';
            $message = str_replace($placeholder, $replacement, $message);
        }

        // Détecter et convertir les URLs brutes restantes
        $pattern = '~(?<!href=["\'])(?<!>)(https?://[^\s<]+|www\.[^\s<]+)(?![^<]*</a>)~i';
        $message = preg_replace_callback($pattern, function ($matches) {
            $url = $matches[0];
            $href = preg_match('~^https?://~i', $url) ? $url : "http://$url";
            return '<a href="' . e($href) . '" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">' . e($url) . '</a>';
        }, $message);

        // Convertir les retours à la ligne en <br>
        $message = nl2br($message);

        return $message;
    }


    public function replySelectedMessage()
    {
        if ($this->selectedMessage) {
            $this->message_txt = strip_tags($this->selectedMessage['message'] ?? '');
        }

        $this->activeTab = 'sendmail';
    }


    public function replyFirstMessage()
    {
        $messages = $this->ticketDetails['conversation']['messages'] ?? [];

        if (!empty($messages)) {
            $firstMessage = $messages[0];
            $this->message_client = strip_tags($firstMessage['message'] ?? '');
        }

        $this->showSendmailTab = true;

        $this->activeTab = 'sendmail';
    }

    public function detectLanguage()
    {
        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        $detectResponse = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post(env('API_REST') . "/detectlanguageiso", [
                    "text" => $this->message_client,
                ]);

        if ($detectResponse->successful()) {
            return $detectResponse->json();
        }

        return null;
    }


    public function translateOpenAI()
    {
        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        if (empty($this->message_txt)) {
            $this->error('Veuillez écrire un message avant de le traduire');
            return;
        }

        $detectResponse = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post(env('API_REST') . "/openai/detectlanguageiso", [
                    "text" => $this->message_txt,
                ]);

        if (!$detectResponse->successful()) {
            $this->error('Impossible de détecter la langue du message');
            return;
        }

        $detectedData = $detectResponse->json();

        if (empty($detectedData['langue'])) {
            $this->error('Impossible de détecter la langue');
            return;
        }

        $currentLang = $detectedData['langue'];

        if (empty($this->message_client)) {
            $this->error('Message client introuvable');
            return;
        }

        $detectClientResponse = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post(env('API_REST') . "/openai/detectlanguageiso", [
                    "text" => $this->message_client,
                ]);

        if (!$detectClientResponse->successful()) {
            $this->error('Impossible de détecter la langue du client');
            return;
        }

        $clientLangData = $detectClientResponse->json();
        $targetLang = $clientLangData['langue'] ?? 'en';

        if ($currentLang === $targetLang) {
            $this->info('Le message est déjà dans la langue du client');
            return;
        }

        $translateResponse = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post(env('API_REST') . "/openai/translateandcorrect", [
                    "text" => $this->message_txt,
                    "target" => $targetLang,
                ]);

        if ($translateResponse->successful()) {
            $translated = $translateResponse->json('translated_text');

            if (!empty($translated)) {
                $this->message_txt = $translated;
                $this->success("Message traduit de {$currentLang} vers {$targetLang}");
            } else {
                $this->error('La traduction a échoué');
            }
        } else {
            $this->error('Erreur lors de la traduction : ' . $translateResponse->body());
        }
    }



    public function updateStatus($newStatus)
    {
        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        $response = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->put(env('API_REST') . "/ticket/{$this->ticketId}", [
                    "status" => $newStatus,
                ]);

        if ($response->successful()) {
            $this->success("Le ticket est maintenant en statut : {$newStatus}");

            $this->fetchTicketDetails();
        } else {
            $this->error("Impossible de mettre à jour le ticket !");
        }
    }



    public function getNextStatus(): array
    {
        $current = $this->ticketDetails['details'][0]['status'] ?? 'en attente';

        return match ($current) {
            'en attente' => ['label' => 'Mettre en cours', 'next' => 'en cours'],
            'en cours' => ['label' => 'Clôturer le ticket', 'next' => 'cloture'],
            'cloture' => ['label' => 'Réouvrir (en attente)', 'next' => 'en attente'],
            default => ['label' => 'Mettre en attente', 'next' => 'en attente'],
        };
    }


    public function translateMessage()
    {
        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        if (empty($this->selectedMessage['message'])) {
            $this->translatedMessage = 'Aucun message à traduire.';
            return;
        }

        $messageText = $this->selectedMessage['message'];

        $response = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post(env('API_REST') . "/openai/translateandcorrect", [
                    "text" => $messageText,
                    "target" => "fr",
                ]);

        if ($response->successful()) {
            $translated = $response->json('translated_text') ?? 'Erreur de traduction';
            $this->translatedMessage = $this->formatMessage($translated);
        } else {
            $this->translatedMessage = 'Erreur lors de l\'appel API';
        }
    }

    public function updatedSelectedMessage($value)
    {
        $this->translatedMessage = '';
    }


    public function reply(): void
    {
        $token = session('token');

        // Validation manuelle pour capturer les erreurs
        $validator = \Validator::make([
            'message_txt' => $this->message_txt,
            'destinateur' => $this->destinateur,
        ], [
            'message_txt' => 'required|string',
            'destinateur' => 'required',
        ], [
            // Messages personnalisés (optionnel)
            'message_txt.required' => 'Le message est obligatoire',
            'destinateur.required' => 'Le destinataire est obligatoire',
        ]);
        // Si la validation échoue, afficher les erreurs dans un toast
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return;
        }


        $attachments = [];
        if (!empty($this->photos)) {
            foreach ($this->photos as $file) {
                if (!$file->isValid()) {
                    $this->error('Un fichier est invalide');
                    continue;
                }

                $filePath = $file->getRealPath();
                $fileContent = file_get_contents($filePath);

                if (empty($fileContent)) {
                    $this->error("Le fichier {$file->getClientOriginalName()} est vide");
                    continue;
                }

                $base64Content = base64_encode($fileContent);

                if (empty($base64Content)) {
                    $this->error("Erreur d'encodage pour {$file->getClientOriginalName()}");
                    continue;
                }

                $attachments[] = [
                    'filename' => $file->getClientOriginalName(),
                    'mimeType' => $file->getMimeType(),
                    'contentBase64' => $base64Content,
                ];
            }
        }

        if (!empty($this->photos) && empty($attachments)) {
            $this->error('Aucun fichier valide n\'a pu être traité');
            return;
        }

        $messages = $this->ticketDetails['conversation']['messages'] ?? [];
        $firstMessage = $messages[0];
        $firstMessageId = $firstMessage['message_id'];
        $ticket_id = $this->ticketId;

        $body = [
            "ticket_id" => $ticket_id,
            "first_message_id" => $firstMessageId,
            "replyText" => $this->message_txt,
            "attachements" => $attachments,
        ];

        \Log::info('Body envoyé:', $body);

        $response = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post(env('API_REST') . "/ticket/replymail", $body);

        if ($response->successful()) {
            $this->myModal12 = false;
            $this->message_txt = '';
            $this->photos = [];
            $this->fetchTicketDetails();
            $this->success('Email envoyé avec succès !');
        } else {
            \Log::error('Erreur API:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            $this->error('Erreur lors de l\'envoi de l\'email : ' . $response->body());
        }
    }

    public function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }


    public function downloadAttachment($messageIndex, $attachmentIndex)
    {
        $messages = $this->ticketDetails['conversation']['messages'] ?? [];

        if (!isset($messages[$messageIndex]['attachments'][$attachmentIndex])) {
            $this->error('Pièce jointe introuvable');
            return;
        }

        $attachment = $messages[$messageIndex]['attachments'][$attachmentIndex];

        if (isset($attachment['data']) && str_starts_with($attachment['data'], 'data:')) {
            preg_match('/data:([^;]+);base64,(.+)/', $attachment['data'], $matches);

            if (count($matches) === 3) {
                $mimeType = $matches[1];
                $base64Data = $matches[2];
                $fileContent = base64_decode($base64Data);
                $filename = $attachment['filename'] ?? 'document';

                return response()->streamDownload(function () use ($fileContent) {
                    echo $fileContent;
                }, $filename, [
                    'Content-Type' => $mimeType,
                ]);
            }
        }

        $this->error('Impossible de télécharger la pièce jointe');
    }


    public ?int $selectedMessageIndex = null;

    public function openMessage($index)
    {
        $messages = $this->ticketDetails['conversation']['messages'] ?? [];
        $this->selectedMessage = $messages[$index] ?? null;
        $this->selectedMessageIndex = $index;
        $this->translatedMessage = '';
    }

    public function removePhoto($index)
    {
        if (isset($this->photos[$index])) {
            $newPhotos = [];
            foreach ($this->photos as $key => $photo) {
                if ($key !== $index) {
                    $newPhotos[] = $photo;
                }
            }

            $this->photos = $newPhotos;

            $this->success('Fichier retiré avec succès');
        }
    }


    public function showOriginalMessage()
    {
        $this->translatedMessage = '';
    }


    public function confirmerActionLu()
    {
        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        $response = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post(env('API_REST') . "/ticket/ignoreclientresponse", [
                    'ticket_id' => $this->ticketId,
                ]);

        if ($response->successful()) {
            $this->success('Tous les messages ont été marqués comme lus');
            $this->myModal11 = false;
            $this->fetchTicketDetails();
        } else {
            $this->error('Erreur lors de la mise à jour : ' . $response->body());
        }
    }
};
?>

<div class="w-full mx-auto">

    <div class="mx-auto w-full">

<!-- Navigation des onglets avec loading -->
<div class="border-b border-gray-200 mb-4">
    <nav class="-mb-px flex space-x-8">
        <button wire:click="setTab('description')"
            class="px-3 py-2 text-sm font-medium whitespace-nowrap border-b-2 relative
            {{ $activeTab === 'description' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-200' }}">
            <svg wire:loading.remove wire:target="setTab('description')" class="w-4 h-4 inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m0 0l-6-6m6 6H3" />
            </svg>
            <svg wire:loading wire:target="setTab('description')" class="animate-spin w-4 h-4 inline-block mr-1" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Ticket - {{ $ticketDetails['details'][0]['num_ticket'] }}
        </button>

        <button wire:click="setTab('conversation')"
            class="px-3 py-2 text-sm font-medium whitespace-nowrap border-b-2 relative
            {{ $activeTab === 'conversation' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-200' }}">
            <svg wire:loading.remove wire:target="setTab('conversation')" class="w-4 h-4 inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <svg wire:loading wire:target="setTab('conversation')" class="animate-spin w-4 h-4 inline-block mr-1" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Conversation ({{ count($ticketDetails['conversation']['messages'] ?? []) }})
        </button>

        <button wire:click="setTab('commentaire')"
            class="px-3 py-2 text-sm font-medium whitespace-nowrap border-b-2 relative
            {{ $activeTab === 'commentaire' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-200' }}">
            <svg wire:loading.remove wire:target="setTab('commentaire')" class="w-4 h-4 inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4-.86L3 20l1.86-4a9.863 9.863 0 01-.86-4c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <svg wire:loading wire:target="setTab('commentaire')" class="animate-spin w-4 h-4 inline-block mr-1" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Commentaires
        </button>

        @if($showSendmailTab)
            <button wire:click="setTab('sendmail')"
                class="px-3 py-2 text-sm font-medium whitespace-nowrap border-b-2 relative
                {{ $activeTab === 'sendmail' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-200' }}">
                <svg wire:loading.remove wire:target="setTab('sendmail')" class="w-4 h-4 inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <svg wire:loading wire:target="setTab('sendmail')" class="animate-spin w-4 h-4 inline-block mr-1" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Send mail
            </button>
        @endif
    </nav>
</div>

<!-- Overlay de chargement pour le contenu -->
<div class="relative">
    <!-- Indicateur de chargement global -->
    {{-- <div wire:loading wire:target="setTab" class="absolute inset-0 bg-white/80 backdrop-blur-sm z-50 flex items-center justify-center rounded-lg">
        <div class="text-center">
            <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-sm font-medium text-gray-700">Chargement en cours...</p>
        </div>
    </div> --}}

    <!-- Contenu des onglets -->
    <div>
        @if($activeTab === 'description')
            <!-- Contenu description -->
        @elseif($activeTab === 'conversation')
            <!-- Contenu conversation -->
        @elseif($activeTab === 'commentaire')
            <!-- Contenu commentaire -->
        @elseif($activeTab === 'sendmail')
            <!-- Contenu sendmail -->
        @endif
    </div>
</div>

    </div>

    <div>

        @if($activeTab === 'description')

            <x-button class="mb-3 btn-primary" label="{{ $this->getNextStatus()['label'] }}"
                wire:click="updateStatus('{{ $this->getNextStatus()['next'] }}')" />


                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="px-4 py-6 sm:px-6">
                                <h3 class="text-base/7 font-semibold text-gray-900">{{ __('Infos Ticket')}}</h3>
                                <p class="mt-1 max-w-2xl text-sm/6 text-gray-500">{{ __('Tous les information sur le ticket') }}.
                                </p>
                            </div>
                            <div class="border-t border-gray-100">
                                <dl class="divide-y divide-gray-100">
                                    <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-900">Status</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{ $ticketDetails['details'][0]['status'] }}
                                        </dd>
                                    </div>
                                    <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-900">Numero ticket</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{ $ticketDetails['details'][0]['num_ticket'] }}
                                        </dd>
                                    </div>
                                    <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-900">Numero de commande</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{ $ticketDetails['details'][0]['num_commande'] }}
                                        </dd>
                                    </div>
                                    <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-900">Application for</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{ $ticketDetails['details'][0]['subject_ticket'] }}
                                        </dd>
                                    </div>
                                    <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-900">Email address</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{ $ticketDetails['details'][0]['original_client_mail'] }}
                                        </dd>
                                    </div>
                                    <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-900">Email address</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{ $ticketDetails['details'][0]['reception_mail'] }}
                                        </dd>
                                    </div>
                                    <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-900">Nom et prenoms du clients</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{ $ticketDetails['details'][0]['nom_client'] }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div class="bg-gray-50 sm:rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-base font-semibold text-gray-900">Resume</h3>

                                <div class="mt-2 max-w-xl text-sm text-gray-700 max-h-[500px] overflow-y-auto pr-2">
                                    @php
    $todos = $ticketDetails['details'][0]['to_do'] ?? '';
    $items = preg_split('/\r\n|\r|\n/', trim($todos));
                                    @endphp

                                    <ul class="list-none pl-5 space-y-1">
                                        @foreach($items as $item)
                                            @if(!empty(trim($item)))
                                                <li>{{ trim($item) }}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

        @elseif($activeTab === 'conversation')

                        <div class="flex h-[calc(100vh-200px)] bg-gray-50">
                            <div class="w-1/2 bg-white border-r border-gray-200 flex flex-col">
                                <div class="p-4 border-b border-gray-200 bg-white">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-lg font-semibold text-gray-900">Conversation</h2>

                                    <div class="flex space-x-2 ml-auto">
                                        @if($ticketDetails['details'][0]['need_attention'] == 1)
                                        <x-button 
                                            label="Marquer comme lu" 
                                            @click="$wire.myModal11 = true" 
                                            class="btn-warning btn-dash" 
                                        />
                                        @endif

                                        @if(count($ticketDetails['conversation']['messages'] ?? []) > 0)
                                            <button wire:click="replyFirstMessage" type="button"
                                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                </svg>
                                                Répondre
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                </div>

                                <div class="flex-1 overflow-y-auto">
                                    @php
            $messages = $ticketDetails['conversation']['messages'] ?? [];
            $detail = $ticketDetails['details'][0] ?? [];
            $clientEmail = strtolower($detail['original_client_mail'] ?? '');
            $supportEmail = strtolower($detail['reception_mail'] ?? '');
                                    @endphp

                                    @forelse($messages as $idx => $msg)
                                                                    @php
                                            $fromRaw = $msg['from'] ?? '';
                                            if (preg_match('/<([^>]+)>/', $fromRaw, $m)) {
                                                $fromEmail = strtolower($m[1]);
                                            } elseif (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $fromRaw, $m2)) {
                                                $fromEmail = strtolower($m2[0]);
                                            } else {
                                                $fromEmail = strtolower(trim($fromRaw));
                                            }

                                            $label = null;
                                            $badgeClasses = '';
                                            if ($clientEmail && strpos($fromEmail, $clientEmail) !== false) {
                                                $label = 'Client';
                                                $badgeClasses = 'bg-blue-100 text-blue-600';
                                            } elseif ($supportEmail && strpos($fromEmail, $supportEmail) !== false) {
                                                $label = 'Support';
                                                $badgeClasses = 'bg-green-100 text-green-600';
                                            }

                                            $isSelected = $selectedMessageIndex === $idx;
                                                                    @endphp

                                                                    <div wire:click="openMessage({{ $idx }})"
                                                                        class="px-4 py-3 border-b border-gray-200 cursor-pointer hover:bg-gray-50 transition-colors {{ $isSelected ? 'bg-blue-50 border-l-4 border-l-blue-600' : '' }}">

                                                                        <div class="flex items-start justify-between mb-1">
                                                                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                                                                <!-- Avatar initial -->
                                                                                <div
                                                                                    class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm">
                                                                                    {{ strtoupper(substr($fromRaw, 0, 1)) }}
                                                                                </div>

                                                                                <div class="flex-1 min-w-0">
                                                                                    <div class="flex items-center gap-2">
                                                                                        <span class="text-sm font-semibold text-gray-900 truncate">
                                                                                            {{ preg_match('/^([^<]+)/', $fromRaw, $nameMatch) ? trim($nameMatch[1]) : $fromRaw }}
                                                                                        </span>
                                                                                        @if($label)
                                                                                            <span
                                                                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClasses }}">
                                                                                                {{ $label }}
                                                                                            </span>
                                                                                        @endif
                                                                                    </div>
                                                                                    <!-- <p class="text-xs text-gray-500">
                                                                                       <p class="text-xs text-gray-500">
                                                                                            {{ isset($msg['date']) ? \Carbon\Carbon::parse(preg_replace('/\s*\([^)]+\)\s*$/', '', $msg['date']))->format('d/m/Y H:i') : '' }}
                                                                                            </p>
                                                                                    </p> -->
                                                                                    <p class="text-xs text-gray-500">
                                                                                        <time class="js-local-time"
                                                                                            datetime="{{ isset($msg['date']) ? \Carbon\Carbon::parse(preg_replace('/\s*\([^)]+\)\s*$/', '', $msg['date']))->utc()->toISOString() : '' }}">
                                                                                            <!-- Fallback visible sans JS : montre UTC pour transparence -->
                                                                                            {{ isset($msg['date']) ? \Carbon\Carbon::parse(preg_replace('/\s*\([^)]+\)\s*$/', '', $msg['date']))->utc()->format('d/m/Y H:i') . ' UTC' : '' }}
                                                                                        </time>
                                                                                    </p>
                                                                                </div>
                                                                            </div>

                                                                            <!-- Indicateur pièces jointes -->
                                                                            @if(!empty($msg['attachments']))
                                                                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor"
                                                                                    viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                                                </svg>
                                                                            @endif
                                                                        </div>

                                                                        <!-- Sujet -->
                                                                        <h3 class="text-sm font-medium text-gray-900 mb-1 truncate">
                                                                            {{ $msg['subject'] ?? '(Sans objet)' }}
                                                                        </h3>

                                                                        <!-- Aperçu du message -->
                                                                        <p class="text-xs text-gray-600 line-clamp-2">
                                                                            {{ strip_tags($msg['message'] ?? '') }}
                                                                        </p>
                                                                    </div>
                                    @empty
                                        <div class="flex flex-col items-center justify-center h-full p-8 text-center">
                                            <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <p class="text-gray-500">Aucun message trouvé</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Panneau de lecture (droite) -->
                    <div class="flex-1 flex flex-col bg-white">
                        @if($selectedMessage)
                            <!-- En-tête du message -->
                            <div class="px-6 py-4 border-b border-gray-200">
                                <div class="flex items-start justify-between mb-3">
                                    <h1 class="text-xl font-semibold text-gray-900 flex-1">
                                        {{ $selectedMessage['subject'] ?? '(Sans objet)' }}
                                    </h1>
                                </div>

                                <!-- Info expéditeur -->
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold">
                                        {{ strtoupper(substr($selectedMessage['from'] ?? '', 0, 1)) }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-gray-900">
                                                {{ preg_match('/^([^<]+)/', $selectedMessage['from'] ?? '', $nameMatch) ? trim($nameMatch[1]) : ($selectedMessage['from'] ?? '-') }}
                                            </span>
                                            @if(!empty($selectedMessage['attachments']))
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                    </svg>
                                                    {{ count($selectedMessage['attachments']) }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 text-sm text-gray-500">
                                            @php
                $toAddress = $selectedMessage['to'] ?? '';
                $toName = preg_match('/^([^<]+)/', $toAddress, $toMatch) ? trim($toMatch[1]) : $toAddress;
                                            @endphp
                                            <span>À: {{ $toName ?: '-' }}</span>
                                            <span>•</span>
                                            <span><span>{{ isset($selectedMessage['date']) ? \Carbon\Carbon::parse(preg_replace('/\s*\([^)]+\)\s*$/', '', $selectedMessage['date']))->format('d/m/Y H:i') : '-' }}</span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contenu du message -->
                            <div class="flex-1 overflow-y-auto px-6 py-4">
                                <!-- Message (original ou traduit) -->
                                <div class="prose max-w-none text-gray-700">
                                    @if($translatedMessage)
                                        <!-- Badge indiquant que c'est la traduction -->
                                        <div class="flex items-center gap-2 mb-4 px-3 py-2 bg-yellow-50 border border-yellow-200 rounded-lg w-fit">
                                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                                            </svg>
                                            <span class="text-sm font-medium text-yellow-800">Version traduite</span>
                                        </div>
                                        {!! $translatedMessage !!}
                                    @else
                                        {!! $this->formatMessage($selectedMessage['message'] ?? '') !!}
                                    @endif
                                </div>

                                <!-- Pièces jointes -->
                    <!-- Pièces jointes -->
                    @if(!empty($selectedMessage['attachments']))
                        @php
                    // Séparer les images des autres fichiers
                    $images = [];
                    $otherFiles = [];

                    foreach ($selectedMessage['attachments'] as $attachment) {
                        $extension = strtolower(pathinfo($attachment['filename'] ?? '', PATHINFO_EXTENSION));
                        $mimeType = $attachment['mimeType'] ?? '';

                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) || str_contains($mimeType, 'image')) {
                            $images[] = $attachment['url'] ?? '';
                        } else {
                            $otherFiles[] = $attachment;
                        }
                    }

                    // Filtrer les URLs vides
                    $images = array_filter($images);
                        @endphp

                        <div class="mt-6 border-t border-gray-200 pt-6">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                                <svg class="h-5 w-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                Pièces jointes ({{ count($selectedMessage['attachments']) }})
                            </h4>

                            <!-- Galerie d'images -->
                            @if(!empty($images))
                                <div class="mb-4">
                                    <h5 class="text-xs font-semibold text-gray-700 mb-2 flex items-center">
                                        <svg class="h-4 w-4 mr-1 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Images ({{ count($images) }})
                                    </h5>
                                    <x-image-gallery :images="$images" class="h-40 rounded-lg" />
                                </div>
                            @endif

                            <!-- Autres fichiers -->
                            @if(!empty($otherFiles))
                                <div>
                                    <h5 class="text-xs font-semibold text-gray-700 mb-2 flex items-center">
                                        <svg class="h-4 w-4 mr-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Fichiers ({{ count($otherFiles) }})
                                    </h5>
                                    <div class="grid grid-cols-1 gap-2">
                                        @foreach($otherFiles as $attachmentIndex => $attachment)
                                            @php
                            $filename = $attachment['filename'] ?? 'Fichier sans nom';
                            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            $mimeType = $attachment['mimeType'] ?? '';
                            $attachmentUrl = $attachment['url'] ?? null;

                            [$iconColor, $bgColor] = match (true) {
                                $extension === 'pdf' || str_contains($mimeType, 'pdf') =>
                                ['text-red-600', 'bg-red-50'],
                                in_array($extension, ['doc', 'docx']) || str_contains($mimeType, 'word') =>
                                ['text-blue-600', 'bg-blue-50'],
                                in_array($extension, ['xls', 'xlsx']) || str_contains($mimeType, 'spreadsheet') =>
                                ['text-green-600', 'bg-green-50'],
                                in_array($extension, ['zip', 'rar', '7z', 'tar']) =>
                                ['text-yellow-600', 'bg-yellow-50'],
                                default => ['text-gray-600', 'bg-gray-50']
                            };
                                            @endphp

                                            <div
                                                class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                                <div class="flex items-center flex-1 min-w-0">
                                                    <div class="flex-shrink-0 {{ $bgColor }} rounded-lg p-2.5 {{ $iconColor }}">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>

                                                    <div class="ml-3 flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $filename }}</p>
                                                        <p class="text-xs text-gray-500 mt-0.5">
                                                            @if(!empty($attachment['size']))
                                                                {{ $this->formatFileSize($attachment['size']) }}
                                                            @endif
                                                            @if($extension)
                                                                <span class="mx-1.5">•</span>
                                                                <span class="uppercase">{{ $extension }}</span>
                                                            @endif
                                                        </p>
                                                    </div>
                                                </div>

                                                @if($attachmentUrl)
                                                    <a href="{{ $attachmentUrl }}"
                                                        target="_blank"
                                                        download="{{ $filename }}"
                                                        class="ml-4 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-md transition-colors">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        <span>Télécharger</span>
                                                    </a>
                                                @else
                                                    <span class="ml-4 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-400 bg-gray-50 rounded-md cursor-not-allowed">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        <span>Non disponible</span>
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                            </div>

                            <!-- Footer avec actions -->
                            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                                <div class="flex items-center gap-3">
                                    @if($translatedMessage)
                                        <!-- Bouton pour revenir à l'original -->
                                        <button type="button" wire:click="showOriginalMessage"
                                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                            </svg>
                                            <span>Revenir à l'original</span>
                                        </button>
                                    @else
                                        <!-- Bouton pour traduire -->
                                        <button type="button" wire:click="translateMessage" wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                                            <svg wire:loading.remove wire:target="translateMessage" class="w-4 h-4" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                                            </svg>
                                            <svg wire:loading wire:target="translateMessage" class="animate-spin w-4 h-4" fill="none"
                                                viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                                </circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                            <span>Traduire en français</span>
                                        </button>
                                    @endif

                                    @if(count($ticketDetails['conversation']['messages'] ?? []) > 0)
                                        <button wire:click="replyFirstMessage" type="button"
                                           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                            </svg>
                                            Répondre
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @else
                            <!-- État vide -->
                            <div class="flex-1 flex items-center justify-center p-8">
                                <div class="text-center">
                                    <svg class="w-24 h-24 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <h3 class="text-lg font-medium text-gray-900 mb-1">Sélectionnez un message</h3>
                                    <p class="text-sm text-gray-500">Choisissez un message dans la liste pour afficher son contenu</p>
                                </div>
                            </div>
                        @endif
                    </div>
                        </div>

        @endif
@if($activeTab === 'sendmail')
    <div class="mx-auto w-full max-w-6xl">
        <x-header title="Répondre à l'email" separator>
            <x-slot:subtitle>
                Composez votre réponse et ajoutez des pièces jointes si nécessaire
            </x-slot:subtitle>
            <x-slot:actions>
                <x-button 
                    label="Retour" 
                    @click="$wire.setTab('description')" 
                    icon="o-arrow-left" 
                    class="btn-ghost" 
                />
            </x-slot:actions>
        </x-header>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Colonne principale - Composition du message -->
            <div class="lg:col-span-2">
                <x-card title="Composition du message" class="shadow-lg">
                    <x-form wire:submit.prevent="">
                        <!-- Informations du destinataire -->
                        <div class="mb-6 rounded-lg bg-base-200 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex-1">
                                    <x-input 
                                        label="Destinataire" 
                                        wire:model="destinateur" 
                                        placeholder="exemple@domaine.com" 
                                        icon="o-envelope"
                                        hint="Le destinataire de votre réponse" 
                                        readonly 
                                        class="font-semibold"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Affichage du sujet en lecture seule -->
                        @if(!empty($ticketDetails['conversation']['messages'][0]['subject']))
                            <div class="mb-4">
                                <x-input 
                                    label="Objet" 
                                    value="RE: {{ $ticketDetails['conversation']['messages'][0]['subject'] }}"
                                    icon="o-chat-bubble-left-right"
                                    readonly
                                    class="bg-base-200"
                                />
                            </div>
                        @endif

                        <!-- Message du client (référence) -->
                        @if(!empty($message_client))
                            <div class="mb-4">
                                <div class="rounded-lg border-l-4 border-info bg-info/10 p-4">
                                    <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-info-content">
                                        <x-icon name="o-chat-bubble-left-ellipsis" class="h-4 w-4" />
                                        Message original du client
                                    </div>
                                    <div class="max-h-32 overflow-y-auto text-sm text-base-content/70">
                                        {{ Str::limit($message_client, 300) }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Éditeur de message -->
                        <div class="mb-4">
                            <x-markdown 
                                wire:model="message_txt" 
                                label="Votre réponse" 
                                hint="Rédigez votre réponse au client"
                                rows="12"
                            />
                        </div>

                        <!-- Actions -->
                        <x-slot:actions>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-button 
                                    label="Annuler" 
                                    @click="$wire.setTab('description')"
                                    class="btn-ghost" 
                                    icon="o-x-mark"
                                />
                                <x-button 
                                    label="Traduire" 
                                    wire:click="translateOpenAI"
                                    class="btn-outline btn-accent" 
                                    icon="o-language"
                                    spinner="translateOpenAI"
                                    tooltip="Traduire et corriger le message avec l'IA"
                                />
                                <x-button 
                                    label="Envoyer la réponse" 
                                    class="btn-primary" 
                                    icon="o-paper-airplane"
                                    @click="$wire.myModal12 = true" 
                                    tooltip="Envoyer la réponse au client"
                                />
                            </div>
                        </x-slot:actions>
                    </x-form>
                </x-card>
            </div>

            <!-- Colonne latérale - Pièces jointes -->
            <div class="lg:col-span-1">
                <!-- Pièces jointes -->
                <x-card title="Pièces jointes" subtitle="Formats acceptés: images (max 1 Mo)" class="shadow-lg">
                    <x-file 
                        wire:model="photos" 
                        label="Ajouter des fichiers" 
                        multiple 
                        accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        hint="Images uniquement - 1 Mo max par fichier"
                    />
                    
                    @if(!empty($photos))
                        <div class="mt-4">
                            <p class="mb-2 text-sm font-semibold">
                                <x-icon name="o-paper-clip" class="inline h-4 w-4" />
                                {{ count($photos) }} fichier(s) sélectionné(s)
                            </p>
                            <ul class="space-y-2">
                                @foreach($photos as $index => $photo)
                                    <li class="flex items-center justify-between rounded-lg bg-base-200 p-2 text-sm">
                                        <span class="flex items-center gap-2 truncate">
                                            <x-icon name="o-photo" class="h-4 w-4 flex-shrink-0 text-primary" />
                                            <span class="truncate">{{ $photo->getClientOriginalName() }}</span>
                                        </span>
                                        <x-button 
                                            icon="o-x-mark" 
                                           wire:click="removePhoto({{ $index }})"
                                            class="btn-ghost btn-xs ml-2 flex-shrink-0"
                                            tooltip="Retirer"
                                        />
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </x-card>

                <!-- Informations du ticket -->
                <x-card title="Informations du ticket" class="mt-6 shadow-lg">
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Ticket ID:</span>
                            <span class="badge badge-primary">{{ $ticketId }}</span>
                        </div>
                        
                        @if(!empty($ticketDetails['details'][0]['status']))
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Statut:</span>
                                <span class="badge badge-outline">
                                    {{ ucfirst($ticketDetails['details'][0]['status']) }}
                                </span>
                            </div>
                        @endif

                        @if(!empty($ticketDetails['conversation']['messages']))
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Messages:</span>
                                <span class="font-semibold">
                                    {{ count($ticketDetails['conversation']['messages']) }}
                                </span>
                            </div>
                        @endif
                    </div>
                </x-card>

                <!-- Aide rapide -->
                <x-card title="Aide rapide" class="mt-6 bg-info/10">
                    <div class="space-y-2 text-sm">
                        <p class="flex items-start gap-2">
                            <x-icon name="o-information-circle" class="mt-0.5 h-4 w-4 flex-shrink-0 text-info" />
                            <span>Le bouton <strong>Traduire</strong> détecte automatiquement la langue du client</span>
                        </p>
                        <p class="flex items-start gap-2">
                            <x-icon name="o-sparkles" class="mt-0.5 h-4 w-4 flex-shrink-0 text-warning" />
                            <span>Votre message sera traduit et corrigé avec l'IA</span>
                        </p>
                    </div>
                </x-card>
            </div>
        </div>
    </div>
@endif
@if($activeTab === 'commentaire')
    <div class="mx-auto max-w-5xl">
        <x-header title="Historique des commentaires" separator>
            <x-slot:subtitle>
                Suivez toutes les mises à jour et commentaires sur ce ticket
            </x-slot:subtitle>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <x-icon name="o-chat-bubble-left-right" class="h-5 w-5 text-primary" />
                    <span class="badge badge-primary badge-lg">
                        {{ count($ticketDetails['comment'] ?? []) }} commentaire(s)
                    </span>
                </div>
            </x-slot:actions>
        </x-header>

        @if(empty($ticketDetails['comment']))
            <!-- État vide -->
            <x-card class="shadow-lg">
                <div class="py-12 text-center">
                    <x-icon name="o-chat-bubble-bottom-center-text" class="mx-auto h-16 w-16 text-base-300" />
                    <h3 class="mt-4 text-lg font-semibold text-base-content">Aucun commentaire</h3>
                    <p class="mt-2 text-sm text-base-content/60">
                        Ce ticket n'a pas encore de commentaires ou d'historique de mise à jour.
                    </p>
                </div>
            </x-card>
        @else
            <!-- Timeline des commentaires -->
            <div class="relative">
                <!-- Ligne verticale de la timeline -->
                <div class="absolute left-[29px] top-0 h-full w-0.5 bg-gradient-to-b from-primary via-primary/50 to-transparent"></div>

                <ul role="list" class="space-y-6">
                    @foreach ($ticketDetails['comment'] as $index => $comment)
                        <li class="relative">
                            <!-- Carte du commentaire -->
                            <x-card class="ml-16 shadow-md transition-all hover:shadow-lg">
                                <!-- En-tête du commentaire -->
                                <div class="mb-4 flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <!-- Badge de position sur la timeline -->
                                        <div class="absolute -left-[42px] z-10 flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-content shadow-md ring-4 ring-base-100">
                                            {{ $index + 1 }}
                                        </div>

                                        <!-- Informations utilisateur -->
                                        <div>
                                            <h3 class="text-base font-bold text-base-content">
                                                {{ $comment['user']['name'] ?? 'Utilisateur' }}
                                            </h3>
                                            <p class="text-xs text-base-content/60">
                                                @if(!empty($comment['user']['email']))
                                                    {{ $comment['user']['email'] }}
                                                @else
                                                    Membre de l'équipe
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Date et heure -->
                                    <div class="flex flex-col items-end gap-1">
                                        <time 
                                            datetime="{{ $comment['created_at'] }}" 
                                            class="flex items-center gap-1 text-xs font-medium text-primary"
                                            title="{{ \Carbon\Carbon::parse($comment['created_at'])->format('d/m/Y H:i:s') }}"
                                        >
                                            <x-icon name="o-clock" class="h-4 w-4" />
                                            {{ \Carbon\Carbon::parse($comment['created_at'])->diffForHumans() }}
                                        </time>
                                        <span class="text-xs text-base-content/50">
                                            {{ \Carbon\Carbon::parse($comment['created_at'])->format('d/m/Y à H:i') }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Contenu du commentaire -->
                                <div class="prose prose-sm max-w-none">
                                    <div class="rounded-lg bg-base-200 p-4">
                                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-base-content">
                                            {{ $comment['comment'] }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Type de commentaire / badge (optionnel) -->
                                @if(!empty($comment['type']))
                                    <div class="mt-3 flex items-center gap-2">
                                        <span class="badge badge-sm badge-outline">
                                            <x-icon name="o-tag" class="mr-1 h-3 w-3" />
                                            {{ ucfirst($comment['type']) }}
                                        </span>
                                    </div>
                                @endif
                            </x-card>

                            <!-- Indicateur de connexion à la timeline -->
                            <div class="absolute left-[29px] top-8 h-0.5 w-7 bg-primary"></div>
                        </li>
                    @endforeach
                </ul>

                <!-- Point de fin de timeline -->
                <div class="relative ml-16 mt-8">
                    <div class="absolute -left-[42px] flex h-6 w-6 items-center justify-center rounded-full bg-success shadow-md">
                        <x-icon name="o-check" class="h-4 w-4 text-success-content" />
                    </div>
                    <div class="rounded-lg border-2 border-dashed border-base-300 bg-base-200/50 p-4 text-center">
                        <p class="text-sm font-medium text-base-content/60">
                            <x-icon name="o-check-circle" class="mr-1 inline h-4 w-4 text-success" />
                            Vous êtes à jour avec l'historique du ticket
                        </p>
                    </div>
                </div>
            </div>

            <!-- Statistiques en bas (optionnel) -->
            <x-card class="mt-8 bg-gradient-to-r from-primary/5 to-accent/5">
                <div class="grid grid-cols-1 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold text-primary">
                            {{ count($ticketDetails['comment']) }}
                        </div>
                        <div class="text-xs text-base-content/60">Total commentaires</div>
                    </div>
                </div>
            </x-card>
        @endif
    </div>
@endif

    </div>



<x-modal wire:model="myModal11" title="Confirmation de l’action" class="backdrop-blur">
    <div class="py-4">
        <p class="text-gray-700 text-lg">
            Es-tu sûr de vouloir effectuer cette action ?<br>
            Cette opération est irréversible.
        </p>
    </div>

    <x-slot:actions>
        <x-button label="Annuler" flat @click="$wire.myModal11 = false" />
        <x-button label="Confirmer" primary wire:click="confirmerActionLu" class="btn-warning" spinner="confirmerActionLu" />
    </x-slot:actions>
</x-modal>


<x-modal wire:model="myModal12" title="Confirmer l'envoi" class="backdrop-blur">
    <div class="py-4">
        <div class="mb-4 flex items-start gap-3 rounded-lg bg-warning/10 p-4">
            <svg class="h-6 w-6 flex-shrink-0 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <h4 class="font-semibold text-gray-900">Êtes-vous sûr de vouloir envoyer cette réponse ?</h4>
                <p class="mt-1 text-sm text-gray-600">
                    L'email sera envoyé à <strong>{{ $destinateur }}</strong>
                    @if(!empty($photos))
                        avec <strong>{{ count($photos) }} pièce(s) jointe(s)</strong>
                    @endif
                </p>
            </div>
        </div>
    </div>

    <x-slot:actions>
        <x-button label="Annuler" flat @click="$wire.myModal12 = false" />
        <x-button 
            label="Envoyer maintenant" 
            primary 
            wire:click="reply" 
            class="btn-primary" 
            icon="o-paper-airplane"
            spinner="reply"
        />
    </x-slot:actions>
</x-modal>


</div>

<script>
document.querySelectorAll('.js-local-time').forEach(el => {
    const iso = el.getAttribute('datetime');
    if (!iso) return;

    const date = new Date(iso);  // ← Le navigateur convertit AUTO en heure locale !

    console.log(iso)
    console.log(date)

    // Format jour/mois/année 24h (style proche du français)
    const options = {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    };

    el.textContent = date.toLocaleString('fr-FR', options);  // ou navigator.language pour full adapt
    // Alternative full auto : date.toLocaleString() sans options
});
</script>