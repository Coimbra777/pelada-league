<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiWhatsappHelper
{
    public function send(string $phone, string $message): bool
    {
        $apiUrl = config('services.whatsapp.api_url');

        if (empty($apiUrl)) {
            Log::info('WhatsApp stub: mensagem para {phone}', [
                'phone' => $phone,
                'message' => $message,
            ]);

            return false;
        }

        try {
            $timeout = config('services.whatsapp.timeout', 10);
            $token = config('services.whatsapp.api_token', '');

            $request = Http::timeout($timeout);

            if (!empty($token)) {
                $request = $request->withToken($token);
            }

            $response = $request->post($apiUrl, [
                'phone' => $phone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp: mensagem enviada', [
                    'phone' => substr($phone, 0, -4) . '****',
                ]);

                return true;
            }

            Log::error('WhatsApp: falha no envio', [
                'phone' => substr($phone, 0, -4) . '****',
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('WhatsApp: timeout ou falha de conexao', [
                'phone' => substr($phone, 0, -4) . '****',
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsApp: erro inesperado', [
                'phone' => substr($phone, 0, -4) . '****',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
