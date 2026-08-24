<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BitrixService
{
    protected string $baseUrl;

    protected string $username;

    protected string $password;

    protected int $timeout = 30;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            config('bitrix.base_url'),
            '/'
        );

        $this->username = config(
            'bitrix.username'
        );

        $this->password = config(
            'bitrix.password'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Departments
    |--------------------------------------------------------------------------
    */

    public function fetchDepartments(): array
    {
        return $this->get(
            '/V1/bitrix24/Getdepartments'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    */

    public function fetchAgents(): array
    {
        return $this->get(
            '/V1/bitrix24/GetAgents'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET Request
    |--------------------------------------------------------------------------
    */

    protected function get(string $endpoint): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        try {
            $response = Http::timeout($this->timeout)
                ->accept('*/*')
                ->withBasicAuth(
                    $this->username,
                    $this->password
                )
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Bitrix API request failed.', [
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            throw new \RuntimeException(
                'Bitrix API request failed with HTTP status ' .
                $response->status()
            );
        } catch (Throwable $e) {
            Log::error('Bitrix API exception.', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | API Response Normalization
    |--------------------------------------------------------------------------
    */

    protected function extractApiData(
        mixed $response
    ): array {
        if (!is_array($response)) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | Case 1
        |--------------------------------------------------------------------------
        |
        | [
        |     "data" => [...]
        | ]
        |
        */

        if (
            isset($response['data']) &&
            is_array($response['data'])
        ) {
            return $response['data'];
        }

        /*
        |--------------------------------------------------------------------------
        | Case 2
        |--------------------------------------------------------------------------
        |
        | [
        |     "result" => [...]
        | ]
        |
        */

        if (
            isset($response['result']) &&
            is_array($response['result'])
        ) {
            return $response['result'];
        }

        /*
        |--------------------------------------------------------------------------
        | Case 3
        |--------------------------------------------------------------------------
        |
        | [
        |     "items" => [...]
        | ]
        |
        */

        if (
            isset($response['items']) &&
            is_array($response['items'])
        ) {
            return $response['items'];
        }

        /*
        |--------------------------------------------------------------------------
        | Case 4
        |--------------------------------------------------------------------------
        |
        | API directly returns:
        |
        | [
        |     [...]
        | ]
        |
        */

        if (
            array_is_list($response)
        ) {
            return $response;
        }

        /*
        |--------------------------------------------------------------------------
        | Case 5
        |--------------------------------------------------------------------------
        |
        | If the API wraps the actual result one level deeper,
        | find the first array value containing the list.
        |
        */

        foreach ($response as $value) {
            if (
                is_array($value) &&
                array_is_list($value)
            ) {
                return $value;
            }
        }

        return [];
    }
}