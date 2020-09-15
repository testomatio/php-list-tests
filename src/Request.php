<?php

namespace Testomatio;

class Request
{
    private $url;
    private $apiKey;

    public function __construct()
    {
        $this->url = getenv('TESTOMATIO_URL');
        if (!$this->url) {
            $this->url = 'https://app.testomat.io';
        }
        $this->apiKey = getenv('TESTOMATIO');
    }

    public function sendTests($testsData)
    {
        $data = [
            'tests' => $testsData,
            'framework' => 'Codeception',
            'language' => 'php'
        ];

        $url = $this->url . '/api/load?api_key=' . $this->apiKey;
        $response = \Httpful\Request::post($url)
            ->body($data)
            ->sendsJson()
            ->send();


        if ($response->hasErrors()) {
            throw new \Exception("Can't send request to Testomat.io " . $response->code . "\n" . $response->body);
        }


    }
}