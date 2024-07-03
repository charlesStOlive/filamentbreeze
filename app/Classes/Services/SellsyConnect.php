<?php 

namespace App\Classes\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SellsyConnect
{
    protected $client;
    protected $apiUrl;
    protected $apiKey;
    protected $apiSecret;
    protected $userToken;
    protected $userSecret;

    public function __construct()
    {
        $this->apiUrl = env('SELLSY_API_URL');
        $this->apiKey = env('SELLSY_API_KEY');
        $this->apiSecret = env('SELLSY_API_SECRET');
        $this->userToken = env('SELLSY_USER_TOKEN');
        $this->userSecret = env('SELLSY_USER_SECRET');

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'timeout'  => 5.0,
        ]);
    }

    public function test($var) {
        \Log::info('test is ok '.$var);
        return true;
    }

    public function getContactByEmail($email)
    {
        $endpoint = 'client.getList';
        $params = [
            'search' => [
                'email' => $email,
            ],
        ];

        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
            'json' => [
                'method' => $endpoint,
                'params' => $params,
            ],
        ];

        try {
            $response = $this->client->post('', $options);
            $body = $response->getBody();
            $data = json_decode($body, true);

            return $data;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $errorMessage = $response->getBody()->getContents();
                throw new \Exception("Error {$statusCode}: {$errorMessage}");
            } else {
                throw new \Exception($e->getMessage());
            }
        }
    }
}
