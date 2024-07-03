<?php

namespace App\Classes;

/*
* msgraph api documentation can be found at https://developer.msgraph.com/reference
**/

use App\Models\MsgUser;
use Illuminate\Support\Arr;

class EmailAnalyser
{
    private array $emailData; 
    public string $from;
    public array $toRecipients;
    public string $fromNdd;
    public string $subject;
    public string $category;
    public bool $forbiddenNdd = false;
    public bool $forward = false;
    public bool $has_score = false;
    public bool $hasContact = false;
    public bool $hasClient = false;
    public int $score = 0;
    private MsgUser $user;

    public function __construct(array $email, MsgUser $user) {
        $this->emailData = $email;
        $this->user = $user;
        $this->extractEmailDetails();
    }

    private function extractEmailDetails(): void {
        // Extraire les infos de bases.
        $sender = Arr::get($this->emailData, 'sender.emailAddress.address');
        $from = Arr::get($this->emailData, 'from.emailAddress.address');
        $this->from = $from ?? $sender;
        $this->fromNdd = $this->getDomainFromEmail($this->from);
        $this->body = Arr::get($this->emailData, 'body');
        $this->toRecipients = Arr::pluck($this->emailData['toRecipients'], 'emailAddress.address');
    }

    public function analyse(): void {
        $emailToAnalyse = $this->checkIfEmailIsToAnalyse();
        if (!$emailToAnalyse) {
            return;
        }
        if ($emailToAnalyse == 'commerciaux') {
            $this->forwardEmailFromCommerciaux();
            return;
        }
        $this->getContactAndClient(); 
    }

    private function checkIfEmailIsToAnalyse() {
        if (in_array($this->fromNdd, $this->getForbiddenNdd()) && !in_array($this->from, $this->getCommerciaux())) {
            $this->proceedUpdate('ndd_blocked');
            return false;
        } else if (in_array($this->from, $this->getCommerciaux())) {
            return 'commerciaux';
        } else {
            return true;
        }
    }

    private function getContactAndClient(): void {
        $contacts = $this->getContacts();
        foreach ($contacts as $contact) {
            if ($contact['email'] == $this->from) {
                $this->hasContact = true;
                $client = $this->getClientById($contact['entreprise_id']);
                if ($client) {
                    $this->hasClient = true;
                    $this->score += $contact['score'] + $client['score'];
                }
                break;
            }
        }
    }

    private function getClientById(int $id): ?array {
        foreach ($this->getClients() as $client) {
            if ($client['id'] == $id) {
                return $client;
            }
        }
        return null;
    }

    private function getCommerciaux(): array {
        return [
            // En attente de données
        ];
    }

    private function getForbiddenNdd(): array {
        return [
            // En attente de données
        ];
    }

    private function getForbiddenClientNdd(): array {
        return [
            // En attente de données
        ];
    }

    private function proceedUpdate(string $status): void {
        // Code to update status
    }

    private function forwardEmailFromCommerciaux(): void {
        // Code to forward email from commerciaux
    }

    // Helpers
    private function getDomainFromEmail(string $email): ?string {
        $parts = explode('@', $email);
        return $parts[1] ?? null;
    }
    public function isReply(): bool {
        // Vérifier si le sujet contient "Re:"
        return stripos($this->subject, 'Re:') === 0;
    }

    public function isForward(): bool {
        // Vérifier si le sujet contient "Fwd:" ou "Fw:"
        return stripos($this->subject, 'Fwd:') === 0 || stripos($this->subject, 'Fw:') === 0;
    }

    // Données temp pour test
    public function getContacts(): array {
        return [
            [
                'email' => 'charles@notilac.fr',
                'name' => 'Charles',
                'score' => 5,
                'interlocuteur' => 'jean@owner.com',
                'entreprise_id' => 45
            ],
            [
                'email' => 'michel@notilac.fr',
                'name' => 'Michel',
                'score' => 4,
                'entreprise_id' => 45
            ],
            [
                'email' => 'jean@yahoo.fr',
                'name' => 'Jean',
                'score' => 4,
                'entreprise_id' => 10
            ],
        ];
    }

    public function getClients(): array {
        return [
            [
                'id' => 45,
                'ndd' => 'notilac.fr',
                'name' => 'Notilac',
                'interlocuteur' => 'michel@owner.com',
                'score' => 25,
            ],
            [
                'id' => 10,
                'name' => 'Michel SAS',
                'interlocuteur' => 'corinne@owner.com',
                'score' => 10,
            ]
        ];
    }
}
