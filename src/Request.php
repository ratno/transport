<?php

namespace Ratno\Transport;

class Request
{
    protected $client_id;
    protected $base_url;
    protected $key;
    protected $secret;

    function __construct($client_id, $client_secret, $base_url, $access_token = "", $access_token_secret = "")
    {
        $this->client_id = $client_id;
        $this->base_url = $base_url;

        if($access_token && $access_token_secret) {
            $this->key = $access_token;
            $this->secret = $access_token_secret;
        } else {
            $this->key = $client_id;
            $this->secret = $client_secret;
        }

    }

    protected function call($method, $url, $request_params = [])
    {
        $requestSignature = new RequestSignature();

        $params = [];

        $params['auth_nonce']	  	= $requestSignature->createNonce(true);
        $params['auth_timestamp'] 	= time();
        $params['auth_client_id'] 	= $this->client_id;
        if($this->key <> $this->client_id) {
            $params['auth_access_token'] = $this->key;
        }

        if(count($request_params)) {
            foreach($request_params as $key_param => $value_param) {
                $params[$key_param] = $value_param;
            }
        }

        $baseSignature = $requestSignature->createSignatureBase($method, $url, $params);
        $signature     = $requestSignature->createSignature($baseSignature, $this->key, $this->secret);

        $params_string = $requestSignature->normalizeParams($params) . '&auth_signature=' . $signature;

        $client = new \GuzzleHttp\Client();

        if($method == "GET") {
            $result = $client->request($method, $url ."?". $params_string);
        } else {
            $params['auth_signature'] = $signature;

            $result = $client->request($method, $url, [
                'form_params' => $params
            ]);
        }

        return $result->getBody()->getContents();
    }

    public function get($endpoint_url,$request_params = [])
    {
        return $this->call("GET", $this->base_url . $endpoint_url, $request_params);
    }

    public function post($endpoint_url,$request_params = [])
    {
        return $this->call("POST", $this->base_url . $endpoint_url, $request_params);
    }
}