<?php

namespace App\Classes\Services;

/*
* msgraph api documenation can be found at https://developer.msgraph.com/reference
**/

use App\Models\MsgToken;
use Exception;
use GuzzleHttp\Client;
use App\Models\MsgUser;
use App\Classes\EmailAnalyser;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class MsgConnect
{
    protected static string $baseUrl = 'https://graph.microsoft.com/v1.0/';

    public function isConnected(): bool
    {
        $token = $this->getTokenData();

        if ($token === null) {
            return false;
        }

        if ($token->expires < time()) {
            return false;
        }

        return true;
    }

    public function connect(bool $redirect = true): mixed
    {
        $params = [
            'scope' => 'https://graph.microsoft.com/.default',
            'client_id' => config('msgraph.clientId'),
            'client_secret' => config('msgraph.clientSecret'),
            'grant_type' => 'client_credentials',
        ];
        $token;
        try {
            $client = new Client;
            $response = $client->post(config('msgraph.tenantUrlAccessToken'), ['form_params' => $params]);
            $token =  json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode(($e->getResponse()->getBody()->getContents()));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        if (isset($token->access_token)) {
            $this->storeToken($token->access_token, '', $token->expires_in);
        }

        if ($redirect) {
            return redirect(config('msgraph.msgraphLandingUri'));
        }
        return $token->access_token ?? null;
    }

    public function getUsers() {
        $users = $this->guzzle('get', 'users');
        \Log::info($users);
        return $users;
    }

    public function subscribeToEmailNotifications(string $userId, string $secretClientValue): array
    {
        $expirationDate = now()->addHours(24);

        try {
            $subscription = [
                'changeType' => 'created', // ou 'updated,deleted' selon les besoins
                'notificationUrl' =>  url('/api/email-notifications'), // Votre endpoint qui traitera les notifications
                'resource' => 'users/' . $userId . '/mailFolders(\'Inbox\')/messages', // Chemin de la ressource à surveiller
                'expirationDateTime' => $expirationDate->toISOString(), // Date d'expiration de l'abonnement
                'clientState' => $secretClientValue,
            ];

            $response = $this->guzzle('post', 'subscriptions', $subscription);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            \Log::error('Failed to subscribe to email notifications: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to subscribe to email notifications'];
        }
    }

    public function unsubscribeFromEmailNotifications(string $subscriptionId): array
    {
        try {
            $response = $this->guzzle('delete', 'subscriptions/' . $subscriptionId);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            \Log::error('Failed to unsubscribe from email notifications: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to unsubscribe from email notifications'];
        }
    }

    public function renewEmailNotificationSubscription(string $subscriptionId): array
    {
        $expirationDate = now()->addHours(24);
        try {
            $subscription = [
                'expirationDateTime' => $expirationDate->toISOString(),
            ];
            $response = $this->guzzle('patch', 'subscriptions/' . $subscriptionId, $subscription);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            \Log::error('Failed to renew email notification subscription: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to renew email notification subscription'];
        }
    }

    public function processEmailNotification($notificationData)
    {
        $data = $notificationData['value'][0];
        $clientState = $data['clientState'];
        $tenantId = $data['tenantId'];
        $messageId = $data['resourceData']['id'];

        try {
            $user = $this->verifySubscriptionAndgetUser($clientState, $tenantId);
        } catch (\Exception $e) {
            \Log::error("Error in subscription verification: " . $e->getMessage());
            throw $e; // Propagate the exception
        }

        $accessToken = $this->getAccessToken();
        return $this->modifyEmailHeaderAndCategory($user, $messageId, $accessToken);
    }

    protected function verifySubscriptionAndgetUser($clientState, $tenantId)
    {
        if ($tenantId != config('msgraph.tenantId')) {
            \Log::info('Différence entre msgraph.tenantId et tenantId: '.config('msgraph.tenantId'));
            throw new \Exception("Tenant ID does not match the configured value.");
        }
        // Suppose that MsgUser is your Eloquent model and it has `mds_id` and `abn_secret` fields
        $user = MsgUser::where('abn_secret', $clientState)->first();
        if (!$user) {
            throw new \Exception("No user found matching the provided client state.");
        }
        return $user;
    }

    public function modifyEmailHeaderAndCategory($user, $messageId)
    {
        $accessToken = $this->getAccessToken(); // Ensure you have a valid access token
        try {
            // Get the current email to modify
            $email = $this->guzzle('get', "users/{$user->ms_id}/messages/{$messageId}");
            $emailAnalyser = new emailAnalyser($email, $user);
            
            
            

            // $updatedSubject = "[test] " . $email['subject'];
            // $category = 'good';


            // // Update the email
            // $updateData = [
            //     'subject' => $updatedSubject,
            //     'categories' => [$category] // Add the category
            // ];
            // $response = $this->guzzle('patch', "users/{$user->ms_id}/messages/{$messageId}", $updateData);

            return $response;
        } catch (Exception $e) {
            \Log::error("Failed to modify email header: " . $e->getMessage());
            return null;
        }
    }

    public function getAccessToken(bool $returnNullNoAccessToken = false, bool $redirect = false): mixed
    {
        // Admin token will be stored without user_id
        $token = MsgToken::where('user_id', null)->first();

        // Check if tokens exist otherwise run the oauth request
        if (!isset($token->access_token)) {
            // Don't request new token, simply return null when no token found with this option
            if ($returnNullNoAccessToken) {
                return null;
            }

            return $this->connect($redirect);
        }

        $now = now()->addMinutes(5);

        if ($token->expires < $now) {
            return $this->connect($redirect);
        } else {

            // Token is still valid, just return it
            return $token->access_token;
        }
    }

    public function getTokenData(): MsgToken|null
    {
        return MsgToken::where('user_id', null)->first();
    }

    protected function storeToken(string $access_token, string $refresh_token, string $expires): MsgToken
    {
        //Create or update a new record for admin token
        return MsgToken::updateOrCreate(['user_id' => null], [
            'email' => 'application_token', // Placeholder name
            'access_token' => $access_token,
            'expires' => (time() + $expires),
            'refresh_token' => $refresh_token,
        ]);
    }

    // /**
    //  * @throws Exception
    //  */
    // public function __call(string $function, array $args): mixed
    // {
    //     $options = ['get', 'post', 'patch', 'put', 'delete'];
    //     $path = (isset($args[0])) ? $args[0] : null;
    //     $data = (isset($args[1])) ? $args[1] : [];

    //     if (in_array($function, $options)) {
    //         return self::guzzle($function, $path, $data);
    //     } else {
    //         //request verb is not in the $options array
    //         throw new Exception($function . ' is not a valid HTTP Verb');
    //     }
    // }

    protected function isJson($data): bool
    {
        return is_string($data) && is_array(json_decode($data, true)) && (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * @throws Exception
     */
    protected function guzzle(string $type, string $request, array $data = []): array
    {
        try {
            $client = new Client();
            $response = $client->$type(self::$baseUrl . $request, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                    'Prefer' => config('msgraph.preferTimezone'),
                ],
                'body' => json_encode($data),
            ]);

            $responseObject = json_decode($response->getBody()->getContents(), true);
            return $responseObject ?? [];
        } catch (ClientException $e) {
            \Log::error("HTTP request failed: " . $e->getMessage());
            return json_decode($e->getResponse()->getBody()->getContents(), true) ?? ['error' => 'Failed to process request'];
        } catch (Exception $e) {
            \Log::error("Unexpected error: " . $e->getMessage());
            throw new Exception('Internal server error. Please try again later.');
        }
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function getPagination(array $data, string $top = '0', string $skip = '0'): array
    {
        $total = $data['@odata.count'] ?? 0;

        if (isset($data['@odata.nextLink'])) {
            $parts = parse_url($data['@odata.nextLink']);
            parse_str($parts['query'], $query);

            $top = $query['$top'] ?? 0;
            $skip = $query['$skip'] ?? 0;
        }

        return [
            'total' => $total,
            'top' => $top,
            'skip' => $skip,
        ];
    }
}
