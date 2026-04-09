<?php

namespace App\Services\Asaas;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AsaasClient
{
    private PendingRequest $http;

    public function __construct()
    {
        $this->http = Http::baseUrl(config('services.asaas.base_url'))
            ->withHeaders(['access_token' => config('services.asaas.api_key')])
            ->acceptJson();
    }

    public function get(string $endpoint, array $query = []): array
    {
        return $this->http->get($endpoint, $query)->throw()->json();
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->http->post($endpoint, $data)->throw()->json();
    }

    public function put(string $endpoint, array $data = []): array
    {
        return $this->http->put($endpoint, $data)->throw()->json();
    }

    public function delete(string $endpoint): array
    {
        return $this->http->delete($endpoint)->throw()->json();
    }
}
