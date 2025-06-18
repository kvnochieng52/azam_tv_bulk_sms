<?php
// app/Models/AfricaTalkingAccount.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AfricaTalkingAccount extends Model
{
    public static function getBalance()
    {
        $username = config('services.africastalking.username');
        $apiKey = config('services.africastalking.api_key');

        $url = 'https://api.africastalking.com/version1/user?username=' . urlencode($username);

        $headers = [
            'Accept: application/json',
            'apikey: ' . $apiKey
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_SSL_VERIFYPEER => env('APP_ENV') === 'production'
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: $error");
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            throw new \Exception($result['errorMessage'] ?? 'API request failed');
        }

        if (!isset($result['UserData']['balance'])) {
            throw new \Exception('Balance information not found in response');
        }

        return $result['UserData']['balance'];
    }
}
