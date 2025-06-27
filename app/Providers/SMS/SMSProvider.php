<?php

namespace App\Providers\SMS;

use App\Http\Dto\Auth\AuthDTO;
use Curl\Curl;

class SMSProvider
{
    public function __construct() {}

    /**
     * Send SMS to user by phone through sms gate
     */
    public function sendSMS(AuthDTO $authDTO): array
    {
$data = [
    "phone_number" => preg_replace('/\D+/', '', $authDTO->phone),
    "channels" => [
        "sms"
    ],
    "channel_options"=> [
        "sms"=> [
            "text"=> "Ваш код: {$authDTO->code}",
            "alpha_name"=> "AptekaInLek",
            "ttl"=> 300
        ]
    ]
];

            $url = 'https://api.communicator.mts.by/2555/json2/simple';
            $curl = new Curl();
            $curl->setBasicAuthentication('AptekaInLek_Kqt7', 'mQuoLS');
            $curl->setHeader('Content-Type', 'application/json');
            $curl->post($url, json_encode($data));

            $response = $curl->response;

        return ['sent' => true, 'error' => ''];
    }
}
