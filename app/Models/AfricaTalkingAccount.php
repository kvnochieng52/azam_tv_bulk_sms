<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AfricaTalkingAccount extends Model
{
    /**
     * Fetch balance for a given country code (ke or ug).
     */
    public static function getBalance(string $countryCode = 'ke'): string
    {
        $code     = strtolower($countryCode);
        $username = config("services.africastalking_{$code}.username");
        $apiKey   = config("services.africastalking_{$code}.api_key");

        if (empty($username) || empty($apiKey)) {
            throw new \Exception("Africa's Talking credentials not configured for country: " . strtoupper($code));
        }

        $url = 'https://api.africastalking.com/version1/user?username=' . urlencode($username);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'apikey: ' . $apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_SSL_VERIFYPEER => env('APP_ENV') === 'production',
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
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
