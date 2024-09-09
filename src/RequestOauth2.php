<?php
namespace Ratno\Transport;
use Illuminate\Support\Facades\DB;

class RequestOauth2
{
    protected $base_url;
    protected $access_token;
    protected $client_id;
    protected $client_secret;
    protected $retry_auth = 0;

    function __construct($client_id, $client_secret, $base_url)
    {
        $this->base_url = $base_url;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->access_token = $this->authClient($client_id,$client_secret);
    }

    protected function authClient($client_id,$client_secret, $re_try = false)
    {   

        // If the access_token has existed from session
        $session_token = $this->getSessionToken();
        if($session_token && !$re_try){
            return $session_token;
        }
        
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

            $this->setTokenSession($data["tiketux"]["result"]["expires_in"], $data["tiketux"]["result"]["access_token"]);

            return $data["tiketux"]["result"]["access_token"];
        } elseif(array_key_exists("whitelabel",$data)
            && array_key_exists("status",$data["whitelabel"])
            && $data["whitelabel"]["status"] == "OK") {
            
            $this->setTokenSession($data["whitelabel"]["result"]["expires_in"], $data["whitelabel"]["result"]["access_token"]);

            return $data["whitelabel"]["result"]["access_token"];
        }

        return "";
    }

    protected function getSessionToken(){

        if(!function_exists('session')){
            return null;
        }
        
        $session = session('access_token_asmat'); 
        $currentTimestamp = time();
        
        // Check session exist
        if(!$session){
            return null;
        }
        
        // Check session has expire
        if ($session['expire'] <=  $currentTimestamp) {
            return null;
        }
        
        return $session['token']; 
    }

    protected function setTokenSession($time, $token){

        if(!function_exists('session')){
            return null;
        }

        $timestamp = time() + $time;

        session(['access_token_asmat' => [
            'token' => $token,
            'expire' => $timestamp
        ]]);

    }

    protected function call($method, $url, $request_params = [], $timeout = 0, $option = null)
    {
        $client = new \GuzzleHttp\Client();
        $error = null;

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
        } else {
            $user_agent = '-';
        }

        try{
            if($method == "GET") {
                $result = $client->request($method, $url ."?". http_build_query($request_params),[
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'random-user-id' => $this->userId(),
                        'asmat-user-agent' => $user_agent,
                    ],
                    'timeout' => $timeout,
                    'connect_timeout' => $timeout
                ]);

            } else {
                if(!empty($option['json'])) {
                    $data = 'json';
                } else {
                    $data = 'form_params';
                }

                $result = $client->request($method, $url, [
                    $data => $request_params,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'random-user-id' => $this->userId()
                    ],
                    'timeout' => $timeout,
                    'connect_timeout' => $timeout
                ]);
            }
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // This is will catch all connection timeouts
            $error = [
                'code' => '408',
                'message' => 'WL Request Timeout, Timeout after: '.$timeout.' sec',
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // This will catch all 400 level errors.
            $error = [
                'code' => $e->getResponse()->getStatusCode(),
                'message' => 'WL Failed Request '.$e->getResponse()->getStatusCode(),
            ];
            
            // If response 401, try 1 more time authClient() using recursive function call()
            if($this->retry_auth == 0 && $e->getResponse()->getStatusCode() == '401'){
                $this->retry_auth++;
                $this->access_token = $this->authClient($this->client_id,$this->client_secret, true);
                return $this->call($method, $url, $request_params);
            }
        }

        if($error){
            try{
                $res = DB::connection('mysql_log')
                ->table('log_error')
                ->insert([
                    'title' => 'WL Failed Request API ',
                    'tag' => $url,
                    'client' => $_SERVER['HTTP_HOST'].' : '.$this->client_id,
                    'message' => $error['message'],
                    'created_at' => NOW(),
                    'updated_at' => NOW(),
                ]);	
            }catch(\Exeception $e){}

            switch ($error['code']) 
            {
                case '408':
                    abort(408, '408 Request Timeout');
                    break;
                
                default:
                    abort($error['code'], $error['message']);
                    break;
            }
            
            return "";   
        }

        return $result->getBody()->getContents();
    }
    
    protected function userId(){

        if(!function_exists('session')){
            return null;
        }

        $session_user_id =  session('session_user_id');

        if(empty($session_user_id)){
            $random_id = bin2hex(random_bytes(8));
            $session_user_id = date('Ymdhis').''.$random_id;
            session(['session_user_id' => $session_user_id]);
        }

        return $session_user_id;
    }

    public function get($endpoint_url,$request_params = [])
    {
        return $this->call("GET", $this->base_url . $endpoint_url, $request_params);
    }

    public function post($endpoint_url,$request_params = [], $option = null)
    {
        return $this->call("POST", $this->base_url . $endpoint_url, $request_params, '0', $option);
    }

    public function getWithTimeout($endpoint_url,$request_params = [], $timeout = 60)
    {
        return $this->call("GET", $this->base_url . $endpoint_url, $request_params, $timeout);
    }

    public function postWithTimeout($endpoint_url,$request_params = [], $timeout = 60)
    {
        return $this->call("POST", $this->base_url . $endpoint_url, $request_params, $timeout);
    }
}