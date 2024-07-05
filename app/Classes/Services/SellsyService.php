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
        $this->client = new Client([
            'base_uri' => 'https://api.sellsy.com/v2/',
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
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

    protected function handleRequest($method, $url, $options = [])
    {
        // Ajoute l'en-tête d'autorisation pour chaque requête
        $options['headers']['Authorization'] = "Bearer {$this->getAccessToken()}";

        try {
            $response = $this->client->request($method, $url, $options);
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

    public function getAccessToken()
    {
        $token = SellsyToken::first();

        if (!$token || Carbon::now()->gte($token->expires_at)) {
            $newTokenData = $this->requestAccessToken();
            $expiresAt = Carbon::now()->addSeconds($newTokenData['expires_in']);
            if ($token) {
                $token->update([
                    'access_token' => $newTokenData['access_token'],
                    'expires_at' => $expiresAt,
                ]);
            } else {
                SellsyToken::create([
                    'access_token' => $newTokenData['access_token'],
                    'expires_at' => $expiresAt,
                ]);
            }

            return $newTokenData['access_token'];
        }

        return $token->access_token;
    }

    public function getContactByEmail($email = 'alexis.clement@suscillon.com')
    {
        $options = [
            'query' => [
                'email' => $email,
            ]
        ];

        return $this->handleRequest('GET', 'contacts', $options);
    }

    private function getDomainFromEmail(string $email): ?string {
        $parts = explode('@', $email);
        return $parts[1] ?? null;
    }

    public function executeQuery($query) {
        $allData = [];
        $uniqueCompanyId = null;
        $stopBecauseOfNonUnique = false;

        do {
            $data = $this->handleRequest('GET', $query);

            foreach ($data['data'] as $item) {
                foreach ($item['companies'] as $company) {
                    $companyId = $company['id'] ?? null;
                    if (!$uniqueCompanyId) $uniqueCompanyId = $companyId;
                    if ($uniqueCompanyId != $companyId) {
                        $stopBecauseOfNonUnique = true;
                    }
                }
                $filteredItem = $item;
                $allData[] = $filteredItem;
                if ($stopBecauseOfNonUnique) {
                    $allData['error'] = [
                        'message' => 'multiple_client',
                        'new_id' => $companyId,
                        'previous_id' => $uniqueCompanyId,
                    ];
                    return $allData;
                }
            }

            $count = $data['pagination']['count'] ?? null;
            $total = $data['pagination']['count'] ?? null;
            if ($count == $total) {
                break;
            }
            if (isset($data['pagination']['offset'])) {
                $query = sprintf('%s&offset=%s', $query, $data['pagination']['offset']);
            } else {
                break;
            }
        } while (true);

        return $allData;
    }


    public function workonContactResult($data) {
        if (isset($data['error']['message'])) {
            return [
                'type' => 'error', 
                'message' => $data['error']['message'],
            ];
        }
        if (count($data) == 0) {
            return [
                'type' => 'error', 
                'message' => 'no_contact',
            ];
        }
        if (count($data) == 1) {
            $result = $data[0];
            $companies = null;
            if (count($result['companies']) == 1) {
                $companies = $result['companies'][0]['id'];
            } else if (count($result['companies']) > 1) {
                $companies = 'multiple';
            }
            return [
                'type' => 'contact',
                'contact_id' => $result['object']['id'] ?? null,
                'client_id' => $companies
            ];
        }
        if (count($data) > 1) {
            //on peu prendre le premier resultat parceque si plusieurs enreprise il a été préalablement filtré et mis en erreur. 
            $result = $data[0];
            if (count($result['companies']) == 1) {
                $companies = $result['companies'][0]['id'];
            } 
            return [
                'type' => 'client',
                'client_id' => $companies
            ];
        }
    }

    public function workOnClientResult($data) {
        \Log::info('count data ---'.count($data));
        if (count($data) == 0) {
            return [
                'type' => 'error', 
                'message' => 'empty',
            ];
        } else {
            return $this->extractCustomFields($data);
        }

    }

    function extractCustomFields($data) {
    $customFields = $data['_embed']['custom_fields'];
    $result = [];
    foreach ($customFields as $field) {
        // Vérifiez si la valeur est un tableau et prenez la première valeur si c'est le cas
        if (is_array($field['value'])) {
            $result[$field['code']] = $field['value'][0];
        } else {
            $result[$field['code']] = $field['value'];
        }
    }
    unset($data['_embed']);
    return array_merge($data, $result);
}

    public function searchByEmail(string $email) {
        $query = sprintf('search?q=%s&type[]=contact&limit=50', $email);
        $searchResult = $this->executeQuery($query);
        $parsedResult = $this->workonContactResult($searchResult);
        $typeparsedResult = $parsedResult['type'];
        if ($typeparsedResult == 'contact') {
            $searchResult['x_parsedResult'] = $parsedResult;
            if ($contactId = $parsedResult['contact_id'] ?? false) {
                $searchResult['x_contact'] = $this->getContactById($contactId);
            }
            if ($clientId = $parsedResult['client_id'] ?? false) {
                $searchResult['temp_client'] = $clientResult = $this->getClientById($clientId);
                $searchResult['x_client'] = $this->workOnClientResult($clientResult);
            }
            if ($staffId = $searchResult['x_client']['progi-commerc2'] ?? false) {
                $searchResult['x_staff'] = $this->searchByStaffId($staffId);
            }
            return $searchResult;
        } else if ($typeparsedResult == 'empty') {
            $ndd = $this->getDomainFromEmail($email);
            $query = sprintf('search?q=%s&type[]=contact&limit=50', $ndd);
            $searchResult = $this->executeQuery($query);
            $parsedResult = $this->workonContactResult($searchResult);
            $typeparsedResult = $parsedResult['type'];
            if ($typeparsedResult == 'client') {
                if ($clientId = $parsedResult['client_id'] ?? false) {
                    $searchResult['x_client'] = $this->getClientById($clientId);
                }
            }
            return $searchResult;
        }
    }

    public function getContactById($id) {
        $queryParams = [
            'field' => ['first_name', 'last_name', 'email', 'position',]
        ];
        $queryParams = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        return $this->handleRequest('GET', "contacts/{$id}?{$queryParams}");
    }

    public function getClientById($id) {
        $queryParams = [
            'embed' => ['cf.197833', 'cf.282914'],
            'field' => ['name', '_embed']
        ];
        $queryParams = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        return $this->handleRequest('GET', "companies/{$id}?{$queryParams}");
    }

    public function searchByStaffId($id) {
        $queryParams = [
            'field' => ['email', 'firstname', 'lastname'],
        ];
        $queryParams = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        return $this->handleRequest('GET', "staffs/{$id}?{$queryParams}");
    }

    public function getCustomFields() {
        return $this->handleRequest('GET', "custom-fields?limit=50");
    }
}
