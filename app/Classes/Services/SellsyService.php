<?php 


namespace App\Classes\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use App\Models\SellsyToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SellsyService
{
    protected $clientId;
    protected $clientSecret;
    protected $client;

    public function __construct()
    {
        $this->clientId = env('SELLSY_CLIENT_ID');
        $this->clientSecret = env('SELLSY_CLIENT_SECRET');
        $this->client = new Client();
    }

    protected function requestAccessToken()
    {
        try {
            $response = $this->client->post('https://login.sellsy.com/oauth2/access-tokens', [
                'json' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $expiresAt = Carbon::now()->addSeconds($data['expires_in']);

            return [
                'access_token' => $data['access_token'],
                'expires_at' => $expiresAt,
            ];
        } catch (ConnectException $e) {
            Log::error('Connection error: ' . $e->getMessage());
            throw new \Exception('Connection error: Unable to connect to Sellsy API');
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $errorMessage = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error("Request error (Status: $statusCode): $errorMessage");
            throw new \Exception("Request error (Status: $statusCode): $errorMessage");
        } catch (\Exception $e) {
            Log::error('Unexpected error: ' . $e->getMessage());
            throw new \Exception('Unexpected error: ' . $e->getMessage());
        }
    }

    public function getAccessToken()
    {
        $token = SellsyToken::first();

        if (!$token || Carbon::now()->gte($token->expires_at)) {
            $newTokenData = $this->requestAccessToken();
            if ($token) {
                $token->update([
                    'access_token' => $newTokenData['access_token'],
                    'expires_at' => $newTokenData['expires_at'],
                ]);
            } else {
                SellsyToken::create([
                    'access_token' => $newTokenData['access_token'],
                    'expires_at' => $newTokenData['expires_at'],
                ]);
            }

            return $newTokenData['access_token'];
        }

        return $token->access_token;
    }

    public function getContactByEmail($email = 'alexis.clement@suscillon.com')
    {
        $accessToken = $this->getAccessToken();

        \Log::info('test getContactByEmail : '.$email);

        try {
            $response = $this->client->get('https://api.sellsy.com/v2/contacts', [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                ],
                'query' => [
                    'email' => $email,
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (ConnectException $e) {
            Log::error('Connection error: ' . $e->getMessage());
            throw new \Exception('Connection error: Unable to connect to Sellsy API');
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $errorMessage = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error("Request error (Status: $statusCode): $errorMessage");
            throw new \Exception("Request error (Status: $statusCode): $errorMessage");
        } catch (\Exception $e) {
            Log::error('Unexpected error: ' . $e->getMessage());
            throw new \Exception('Unexpected error: ' . $e->getMessage());
        }
    }

    public function searchByEmail(string $email) {
        $accessToken = $this->getAccessToken();
        $query = sprintf('https://api.sellsy.com/v2/search?q=%s&type[]=company&type[]=contact&limit=50', $email);
        $allData = [];
        $uniqueCompanyId = null;
        $stopBecauseOfNonUniuqe = false;

        do {
            try {
                $response = $this->client->get($query, [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                    ],
                ]);

                $data = json_decode($response->getBody(), true);

                \Log::info($data);

                foreach ($data['data'] as $item) {
                    foreach ($item['companies'] as $company) {
                        $companyId = $company['id'] ?? null;
                        if(!$uniqueCompanyId) $uniqueCompanyId = $companyId;
                        if ($uniqueCompanyId != $companyId) {
                            $stopBecauseOfNonUniuqe = true;
                            
                        }
                    }

                    // Filter data to keep only the required fields
                    $filteredItem = [
                        'object' => $item['object'],
                        'companies' => $item['companies'],
                        'email' => $item['email'],
                    ];
                    $allData[] = $filteredItem;
                    if($stopBecauseOfNonUniuqe) {
                        $allData['error'] = [
                                'message' => 'doublon_id',
                                'new_id' => $companyId,
                                'previous_id' => $uniqueCompanyId,
                            ];
                            return $allData;
                    }
                }

                \Log::info($data['pagination']);
                $count = $data['pagination']['count'] ?? null;
                $total = $data['pagination']['count'] ?? null;
                if($count == $total) {
                    break;
                }
                if (isset($data['pagination']['offset'])) {
                    $query = sprintf('https://api.sellsy.com/v2/search?q=%s&type[]=company&type[]=contact&limit=50&offset=%s', $email, $data['pagination']['offset']);
                } else {
                    break;
                }
            } catch (ConnectException $e) {
                Log::error('Connection error: ' . $e->getMessage());
                throw new \Exception('Connection error: Unable to connect to Sellsy API');
            } catch (RequestException $e) {
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
                $errorMessage = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
                Log::error("Request error (Status: $statusCode): $errorMessage");
                throw new \Exception("Request error (Status: $statusCode): $errorMessage");
            } 
        } while (true);

        return $allData;
    }
    
}
