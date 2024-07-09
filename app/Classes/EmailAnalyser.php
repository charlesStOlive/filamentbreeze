<?php

namespace App\Classes;

/*
* msgraph api documentation can be found at https://developer.msgraph.com/reference
**/

use App\Models\MsgUser;
use Illuminate\Support\Arr;
use App\Classes\Services\SellsyService;
use App\Settings\AnalyseSettings;

class EmailAnalyser
{
    private array $emailData;
    public string $from;
    public array $toRecipients;
    public string $fromNdd;
    public string $subject;
    public string $category;
    public string $body;
    public bool $forbiddenNdd = false;
    public bool $forward = false;
    public bool $has_score = false;
    public bool $hasContact = false;
    public bool $hasClient = false;
    public int $score = 0;
    private MsgUser $user;
    private $emailIn;

    public function __construct(array $email, MsgUser $user)
    {

        $this->user = $user;
        $this->emailIn = $user->msg_email_ins()->make();
        $this->extractEmailDetails($email);
    }

    private function extractEmailDetails($email): void
    {
        // Extraire les infos de bases.
        $this->emailIn->data_mail = $email;
        $sender = Arr::get($email, 'sender.emailAddress.address');
        $from = Arr::get($email, 'from.emailAddress.address');
        $this->emailIn->from = $from ?? $sender;
        $this->emailIn->subject = $subject = Arr::get($email, 'subject');
        if (stripos($subject, 'Re:') === 0 || stripos($subject, 'Fwd:') === 0 || stripos($subject, 'Fw:') === 0) {
            $this->emailIn->is_forwarded = true;
        }
        $tos = $this->getEmailToAddresses($email['toRecipients'] ?? []);
        $cc =  $this->getEmailToAddresses($email['ccRecipients'] ?? []);
        
        $this->emailIn->tos = array_merge($tos, $cc);
        $this->emailIn->Save();
    } 

    private function getEmailToAddresses($recipients) {
        $emails = [];
        //\Log::info('getEmailToAddresses');
        //\Log::info('user->email : '.$this->user->email);

        foreach ($recipients as $recipient) {
            if (isset($recipient['emailAddress']['address'])) {
                $email = $recipient['emailAddress']['address'];
                if($email != $this->user->email) {
                    $emails[] = $email;
                }
                
            }
        }
        return $emails;
    }

    public function analyse(): void
    {
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

    private function checkIfEmailIsToAnalyse()
    {
        if (in_array($this->emailIn->from, $this->getForbiddenNdd()) && !in_array($this->emailIn->from, $this->getCommerciaux())) {
            $this->emailIn->is_canceled = true;
            $this->emailIn->status = 'Abandonnée NDD';
            $this->emailIn->save();
            return;
        } else if (in_array($this->emailIn->from, $this->getCommerciaux())) {
            $this->emailIn->is_from_commercial = true;
            return 'commerciaux';
        } else {
            return true;
        }
    }

    private function getContactAndClient(): void
    {
    }

    function findEmailInBody($body)
    {
        // La regex pour capturer les emails précédés de 'emailde:'
        $regex = '/emailde:\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/';

        // Recherche des correspondances
        if (preg_match($regex, $body, $matches)) {
            // Si une correspondance est trouvée, retourner l'email
            return $matches[1];
        } else {
            // Si aucune correspondance n'est trouvée, retourner null
            return null;
        }
    }

    private function getCommerciaux(): array
    {
        return app(AnalysisSettings::class)->commercials;
    }

    private function getInternalNdds(): array
    {
        return app(AnalysisSettings::class)->internal_ndds;
    }

    private function getForbiddenClientNdd(): array
    {
        return app(AnalysisSettings::class)->ndd_client_rejecteds;
    }

    private function getScorings(): array
    {
        return app(AnalysisSettings::class)->scorings;
    }

    private function getContactScorings(): array
    {
        return app(AnalysisSettings::class)->contact_scorings;
    }

    private function forwardEmailFromCommerciaux(): void
    {
        // Code to forward email from commerciaux
    }

    // Données temp pour test
    public function getContacts(): array
    {
        return [
            [
                'email' => 'charles@notilac.fr',
                'name' => 'Charles',
                'score' => 5,
                'x_staff' => [
                    'name' => 'Charles',
                    'email' => 'jean@owner.com'
                ],
                'x_contact' => [
                    'name' => 'Charles',
                    'email' => 'jean@owner.com'
                ],

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

    public function getClients(): array
    {
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
