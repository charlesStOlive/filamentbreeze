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

    private function getQuery($emailOrNdd, $type) {
        if($type == 'contact') {
            return sprintf('https://api.sellsy.com/v2/search?q=%s&type[]=contact&limit=50', $emailOrNdd); 
        }
        if($type == 'company') {
            return sprintf('https://api.sellsy.com/v2/search?q=%s&type[]=company&limit=50', $emailOrNdd); 
            
        } 
    }

    private function getDomainFromEmail(string $email): ?string {
        $parts = explode('@', $email);
        return $parts[1] ?? null;
    }

    public function executeQuery($query) {
        $allData = [];
        $uniqueCompanyId = null;
        $stopBecauseOfNonUniuqe = false;
        //
        do {
            try {
                $response = $this->client->get($query, [
                    'headers' => [
                        'Authorization' => "Bearer {$this->getAccessToken()}",
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
                    // $filteredItem = [
                    //     'object' => $item['object'],
                    //     'companies' => $item['companies'],
                    //     'email' => $item['email'],
                    // ];
                    $filteredItem = $item;
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
                    $query = sprintf('%s&offset=%s', $query, $data['pagination']['offset']);
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

    public function getResult($data) {
        if(isset($data['error']['message'])) {
            return [
                'type' => 'error', 
                'message' => $data['error']['message'],
            ];
        }
        if(count($data) == 0) {
            return [
                'type' => 'empty', 
                'message' => null,
            ];
        }
        if(count($data) == 1) {
            $result = $data[0];
            $type = $result['object']['type'];
            if( $type == 'contact') {
                $companies = null;
                if(count($result['companies']) == 1 ) {
                    $companies = $result['companies'][0]['id'];
                } else if (count($result['companies']) > 1 ) {
                    $companies = 'multiple';
                }
                return [
                    'type' => $type,
                    'contact_id' => $result['object']['id'] ?? null,
                    'staff_id' => $result['owner']['id'] ?? null,
                    'client_id' => $companies
                ];
            }
            // if( $type == 'client') {
            //     return [
            //         'type' => $type,
            //         'client_id' => $result['object']['id'] ?? null,
            //     ];
            // }
            
        }
        if(count($data) > 1) {
            $result = $data[0];
            $type = $result['object']['type'];
            if( $type == 'contact') {
                if(count($result['companies']) == 1 ) {
                    $companies = $result['companies'][0]['id'];
                } 
                return [
                    'type' => 'client',
                    'client_id' => $companies
                ];
            }
        }
    }

    public function searchByEmail(string $email) {
        //Recherche d'abord sur les contacts. 
        $query = $this->getQuery($email, 'contact');
        $searchResult = $this->executeQuery($query);
        $parsedResult = $this->getResult($searchResult);
        $typeparsedResult = $parsedResult['type'];
        \log::info('$typeparsedResult : '.$typeparsedResult);
        if($typeparsedResult == 'contact') {
            $searchResult['x_parsedResult'] = $parsedResult;
            if($contactId = $parsedResult['contact_id'] ?? false) {
                $searchResult['x_contact'] = $this->searchByContactId($contactId);
            }
            if($clientId = $parsedResult['client_id'] ?? false) {
                $searchResult['x_client'] = $this->searchByClientId($clientId);
            }
             if($staffId = $parsedResult['staff_id'] ?? false) {
                $searchResult['x_staff'] = $this->searchByStaffId($staffId);
            }
            return $searchResult;
        } else if($typeparsedResult == 'empty') {
            $ndd = $this->getDomainFromEmail($email);
            // $queryCompany = $this->getQuery($ndd, 'company');
            // $searchResult = $this->executeQuery($queryCompany);
            $query = $this->getQuery($ndd, 'contact');
            $searchResult = $this->executeQuery($query);
            $parsedResult = $this->getResult($searchResult);
            \Log::info('parsedResult after first empty');
            \Log::info($parsedResult);
            $typeparsedResult = $parsedResult['type'];
            if($typeparsedResult == 'client') {
                if($clientId = $parsedResult['client_id'] ?? false) {
                    $searchResult['x_client'] = $this->searchByClientId($clientId);
                }
            }
            return $searchResult;
        }
        
    }

    

    public function searchByContactId($id) {
        $accessToken = $this->getAccessToken();
        try {
            $response = $this->client->get("https://api.sellsy.com/v2/contacts/{$id}", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/json',
            ],
        ]);
        $data = json_decode($response->getBody(), true);
        return $data;
    } catch (ConnectException $e) {
            Log::error('Connection error: ' . $e->getMessage());
            throw new \Exception('Connection error: Unable to connect to Sellsy API');
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $errorMessage = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error("Request error (Status: $statusCode): $errorMessage");
            throw new \Exception("Request error (Status: $statusCode): $errorMessage");
        } 
    }

    public function searchByClientId($id) {
        $accessToken = $this->getAccessToken();
        try {
            $response = $this->client->get("https://api.sellsy.com/v2/companies/{$id}", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/json',
            ],
        ]);
        $data = json_decode($response->getBody(), true);
        return $data;
    } catch (ConnectException $e) {
            Log::error('Connection error: ' . $e->getMessage());
            throw new \Exception('Connection error: Unable to connect to Sellsy API');
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $errorMessage = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error("Request error (Status: $statusCode): $errorMessage");
            throw new \Exception("Request error (Status: $statusCode): $errorMessage");
        } 
    }

    public function searchByStaffId($id) {
        $accessToken = $this->getAccessToken();
        try {
            $response = $this->client->get("https://api.sellsy.com/v2/staffs/{$id}", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/json',
            ],
        ]);
        $data = json_decode($response->getBody(), true);
        return $data;
    } catch (ConnectException $e) {
            Log::error('Connection error: ' . $e->getMessage());
            throw new \Exception('Connection error: Unable to connect to Sellsy API');
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $errorMessage = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error("Request error (Status: $statusCode): $errorMessage");
            throw new \Exception("Request error (Status: $statusCode): $errorMessage");
        } 
    }
    
}
