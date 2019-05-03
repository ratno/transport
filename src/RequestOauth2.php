<?php

namespace Ratno\Transport;

class RequestOauth2
{
    protected $base_url;
    protected $access_token;

    function __construct($client_id, $client_secret, $base_url)
    {
        $this->base_url = $base_url;
        $this->access_token = $this->authClient($client_id,$client_secret);
    }

    protected function authClient($client_id,$client_secret)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request("POST", $this->base_url . "/client_token.php", [
            'form_params' => [
                "grant_type" => "client_credentials",
                "client_id" => $client_id,
                "client_secret" => $client_secret
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(),true);
        if(array_key_exists("tiketux",$data)
            && array_key_exists("status",$data["tiketux"])
            && $data["tiketux"]["status"] == "OK") {

            return $data["tiketux"]["result"]["access_token"];
        } elseif(array_key_exists("whitelabel",$data)
            && array_key_exists("status",$data["whitelabel"])
            && $data["whitelabel"]["status"] == "OK") {

            return $data["whitelabel"]["result"]["access_token"];
        }

        return "";
    }

    protected function call($method, $url, $request_params = [])
    {
        $client = new \GuzzleHttp\Client();

        if($method == "GET") {
            $result = $client->request($method, $url ."?". http_build_query($request_params),[
                'headers' => ['Authorization' => 'Bearer ' . $this->access_token]
            ]);
        } else {
            $result = $client->request($method, $url, [
                'form_params' => $request_params,
                'headers' => ['Authorization' => 'Bearer ' . $this->access_token]
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