<?php

namespace Devfaysal\Icombd;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class Icombd
{
    protected $baseUrl = 'http://api.icombd.com/api/v1';
    protected $username = null;
    protected $password = null;
    protected $sms_sendar = null;
    protected $voice_sendar = null;

    public function __construct()
    {
        if (config('icombd.username') == null) {
            throw new \Exception('Icombd username is not set');
        }
        if (config('icombd.password') == null) {
            throw new \Exception('Icombd password is not set');
        }
        $this->username = config('icombd.username');
        $this->password = config('icombd.password');
        $this->sms_sendar = config('icombd.sms_sendar');
        $this->voice_sendar = config('icombd.voice_sendar');
    }

    public function send($numbers, $message)
    {
        if (is_array($numbers)) {
            $numbers = implode(',', $numbers);
        }

        $url = $this->baseUrl . '/campaigns/sendsms/plain';

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'sender' => $this->sms_sendar,
            'text' => $message,
            'to' => $numbers,
        ];

        $response = Http::post($url, $data);
        return $response->status();
    }

    public function sendVoice($numbers, $audioFileName)
    {
        if (is_array($numbers)) {
            $numbers = implode(',', $numbers);
        }

        $url = $this->baseUrl . '/campaigns/sendsms/plain';

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'sender' => $this->sms_sendar,
            'audio' => $audioFileName,
            'to' => $numbers,
        ];

        $response = Http::post($url, $data);
        return $response->status();
    }

    public function accountBalance()
    {
        $data = [
            'username' => $this->username,
            'password' => $this->password,
        ];
        $url = 'http://api.icombd.com/balance';
        $response = Http::get($url, $data);
        return $response->json();
    }
}
