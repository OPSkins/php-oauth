<?php
/**
 * Created by PhpStorm.
 * User: jbrec
 * Date: 8/29/2018
 * Time: 5:40 PM
 */



class OPSkinsOAuthSettings {

    # API stuff
    static $opskinsAPIUrl = 'https://api.opskins.com/'; # https://docs.opskins.com/public/en.html#IOAuth
    static $opskinsAPIKey = 'Your OPSkins API key'; #prod

    # OAuth stuff
    static $opskinsOAuthURL = 'https://oauth.opskins.com/';
    static $opskinsOAuthReturnUri = 'http://localhost/';
    static $siteName = 'Your website name';

    # File stuff

    # I suggest you do not use file storage as it will not scale
    # I figured most people would be using mysql/redis or some db to store this stuff.
    static $stateMappingFile = '/path/to/file/state_map';
    static $clientsFileLocation = '/path/to/file/clients_file';
}

/**
 * Class OPSkinsOAuth
 */
class OPSkinsOAuth {

    public $client_id;
    public $client_list;

    protected static $allowedScopes = [
        'identity',
        'deposit',
        'withdraw',
        'trades',
        'items',
        'manage_items',
        'payments',
        'edit_account',
        'cashout',
        'purchase',
        'convert'
    ];

    public function __construct($client_id = null)
    {
        $this->client_id = $client_id;
    }

    /**
     * @return bool|OPSkinsClient
     * @throws Exception
     */

    public function createOAuthClient() {
        #  https://docs.opskins.com/public/en.html#IOAuth_CreateClient_v1

        $input = [
            'name' => \OPSkinsOAuthSettings::$siteName,
            'redirect_uri' => \OPSkinsOAuthSettings::$opskinsOAuthReturnUri,
            'can_keep_secret' => 1,
        ];

        $url = \OPSkinsOAuthSettings::$opskinsAPIUrl . 'IOAuth/CreateClient/v1/';

        $curl = new OPSkinsCurl($url, INPUT_POST, $input);
        $curl->setAuth( \OPSkinsOAuthSettings::$opskinsAPIKey . ':');

        $data = json_decode($curl->exec(), true);

        \OPSkinsCurl::checkJsonError();

        if (!$data['status']){
            throw new Exception("Bad status from OPSkins API");
        }

        $output = $data['response'];
        $output['client']['secret'] = $output['secret'];

        return OPSkinsClient::storeNewClient($output['client']);


    }

    /**
     * @param OPSkinsClient $client
     * @return bool
     * @throws Exception
     */
    public function deleteOAuthClient(\OPSkinsClient &$client){

        $url = \OPSkinsOAuthSettings::$opskinsAPIUrl . 'IOAuth/DeleteClient/v1/';

        $curl = new OPSkinsCurl($url, INPUT_POST , $client->client_id);
        $curl->setAuth( \OPSkinsOAuthSettings::$opskinsAPIKey . ':');
        $data = json_decode($curl->exec(), true);

        if (!$data['status']){
            throw new Exception("Bad status from OPSkins API");
        }

        return true;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getOwnedClientList(){

        $url = \OPSkinsOAuthSettings::$opskinsAPIUrl . 'IOAuth/GetOwnedClientList/v1/';

        $curl = new OPSkinsCurl($url);
        $curl->setAuth( \OPSkinsOAuthSettings::$opskinsAPIKey . ':');
        $data = json_decode($curl->exec(), true);

        if (!$data['status']){
            throw new Exception("Bad status from OPSkins API");
        }

        return $data['response'];
    }

    /**
     * @param OPSkinsClient $client
     * @return bool|OPSkinsClient
     * @throws Exception
     */
    public function resetClientSecret(\OPSkinsClient &$client){

        $url = \OPSkinsOAuthSettings::$opskinsAPIUrl . 'IOAuth/ResetClientSecret/v1/';

        $curl = new OPSkinsCurl($url, INPUT_POST , $client->client_id);
        $curl->setAuth( \OPSkinsOAuthSettings::$opskinsAPIKey . ':');
        $data = json_decode($curl->exec(), true);

        if (!$data['status']){
            throw new Exception("Bad status from OPSkins API");
        }

        $output = $data['response'];
        $output['client']['secret'] = $output['secret'];

        return OPSkinsClient::storeNewClient($output['client']);
    }

    /**
     * @param OPSkinsClient $client
     * @param string $name
     * @param string $redirect_uri
     * @return bool|OPSkinsClient
     * @throws Exception
     */
    public function updateClient(\OPSkinsClient &$client, $name = '', $redirect_uri = ''){

        if(empty($redirect_uri) && empty($name)){
            throw new Exception("Either redirect_uri or name must be set.");
        }

        $input = [];
        $input['client_id'] = $client->client_id;
        if(!empty($name)){
            $input['redirect_uri'] = $redirect_uri;
        }
        if(!empty($name)){
            $input['name'] = $name;
        }

        $url = \OPSkinsOAuthSettings::$opskinsAPIUrl . 'IOAuth/UpdateClient/v1/';

        $curl = new OPSkinsCurl($url, INPUT_POST , $input);
        $curl->setAuth( \OPSkinsOAuthSettings::$opskinsAPIKey . ':');
        $data = json_decode($curl->exec(), true);

        if (!$data['status']){
            throw new Exception("Bad status from OPSkins API");
        }

        $output = $data['response'];
        $output['client']['secret'] = $client->secret;

        return OPSkinsClient::storeNewClient($output['client']);

    }

    /**
     * @param OPSkinsClient $client
     * @param array $scopes
     * @return string
     * @throws Exception
     */
    public function getAuthUrl(\OPSkinsClient &$client, $scopes = ['identity']){

        foreach ($scopes as $scope){
            if(!in_array($scope, self::$allowedScopes)){
                throw new Exception("Invalid Scope selected");
            }
        }

        $state = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(10/strlen($x)) )),1,10);

        // save state
        if(is_file(\OPSkinsOAuthSettings::$stateMappingFile)) {
            $state_map = file_get_contents(\OPSkinsOAuthSettings::$stateMappingFile);
        }
        if (empty($state_map)){
            file_put_contents(\OPSkinsOAuthSettings::$stateMappingFile, json_encode([$state => $client->client_id]));
        }else{
            $state_map = json_decode($state_map, true);
            $state_map[$state] = $client->client_id;
            file_put_contents(\OPSkinsOAuthSettings::$stateMappingFile, json_encode($state_map));
        }


        $input = [
            'client_id' => $client->client_id,
            'response_type' => 'code',
            'state' => $state,
            'duration' => 'permanent',
            'scope' => implode(' ', $scopes)
        ];

        return 'https://oauth.opskins.com/v1/authorize?' . http_build_query($input);
    }

    /**
     * @param $state
     * @param $code
     * @return OPSkinsClient
     * @throws Exception
     */
    public function verifyReturn($state, $code){
        if(empty($state)){
            throw new Exception("State is empty");
        }

        $state_map = file_get_contents(\OPSkinsOAuthSettings::$stateMappingFile);

        if(empty($state_map)){
            throw new Exception("state map is empty");
        }
        $state_map = json_decode($state_map, true);

        if(empty($state_map[$state]) || !empty($_GET['error'])){
            throw new Exception("unable to locate mapping");
        }

        $client = new OPSkinsClient( $state_map[$state] );
        $client->loadClient();
        $client->authCode = $code;
        $client->storeClient();

        //remove state mapping
        unset($state_map[$state]);
        file_put_contents(\OPSkinsOAuthSettings::$stateMappingFile, json_encode($state_map));

        return $client;

    }

    /**
     * @param OPSkinsClient $client
     * @return OPSkinsClient
     * @throws Exception
     */
    public function getBearerToken(\OPSkinsClient &$client){
        $url = 'https://oauth.opskins.com/v1/access_token';

        if(empty($client->authCode)){
            throw new Exception("No auth code for client");
        }

        $input = [
            'grant_type' => 'authorization_code',
            'code' => $client->authCode,
        ];

        $curl = new OPSkinsCurl($url, INPUT_POST, $input);
        $curl->setAuth( $client->client_id . ':' . $client->secret);
        $data = json_decode($curl->exec());

        $client->bearerToken = $data;
        $client->storeClient();

        return $client;
    }

    /**
     * @param OPSkinsClient $client
     * @return mixed
     */
    public function testAuthed(\OPSkinsClient $client){

        $url = \OPSkinsOAuthSettings::$opskinsAPIUrl . 'ITest/TestAuthed/v1/';

        $curl = new OPSkinsCurl($url);
        $curl->setBearer($client->bearerToken->access_token);
        $data = json_decode($curl->exec(), true);

        return $data;

    }

}

/**
 * Class OPSkinsCurl
 */
class OPSkinsCurl {

    public $ch;

    public function __construct($url, $method = INPUT_GET, $fields = [])
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:50.0) Gecko/20100101 Firefox/50.0');

        if( $method == INPUT_POST) {
            if (is_array($fields) && !empty($fields)) {
                curl_setopt($this->ch, CURLOPT_POST, true);
            } else {
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
            }
        }

        if(!empty($fields)) {

            if (is_array($fields)) {
                $curlFields = http_build_query($fields);
            } else {
                $curlFields = $fields;
            }

            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $curlFields);
        }

        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);

    }

    /**
     * @param $authString
     */
    public function setAuth($authString){
        curl_setopt($this->ch, CURLOPT_USERPWD, $authString);
    }

    /**
     * @param $bearerToken
     */
    public function setBearer($bearerToken){
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $bearerToken"]);
    }

    /**
     * @return bool|string
     * @throws Exception
     */
    public function exec(){
        $result = curl_exec($this->ch);

        // Parse out the headers
        $headersize = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $headers = substr($result, 0, $headersize - 4);
        $responseBody = substr($result, $headersize);


        if(curl_getinfo($this->ch, CURLINFO_HTTP_CODE) != 200){
            throw new Exception("HTTP error " . curl_getinfo($this->ch, CURLINFO_HTTP_CODE) . " $responseBody");

        }

        return $responseBody;
    }

    public static function checkJsonError(){
        if (json_last_error()) {
            throw new Exception(function_exists('json_last_error_msg') ? json_last_error_msg() : 'JSON error ' . json_last_error(), json_last_error());
        }
    }


}


/**
 * Class OPSkinsClient
 */
class OPSkinsClient {

    public $secret;
    public $client_id;
    public $name;
    public $redirect_uri;
    public $time_created;
    public $has_secret;
    public $authCode;
    public $bearerToken;

    private static $requiredFields = [
        'secret',
        'client_id',
        'name',
        'redirect_uri',
        'time_created',
        'has_secret'
    ];

    public function __construct($client_id)
    {
        $this->client_id = $client_id;
    }


    /**
     * @param $client_arr
     * @return bool|OPSkinsClient
     */
    public static function storeNewClient($client_arr){
        $client = new self($client_arr['client_id']);

        foreach ($client_arr as $key => $val){
            if( !in_array($key, self::$requiredFields) ){
                continue;
            }
            $client->{$key} = $val;

        }

        if($client->storeClient()){
            return $client;
        }

        return false;
    }


    /**
     * @return bool|mixed
     */
    public function getClientList(){

        if(!is_file(\OPSkinsOAuthSettings::$clientsFileLocation)){
            return false;
        }

        $contents = file_get_contents(\OPSkinsOAuthSettings::$clientsFileLocation);

        if (empty($contents)){
            return false;
        }

        $client_list = json_decode($contents);

        \OPSkinsCurl::checkJsonError();

        if(empty($client_list)){
            return false;
        }

        return $client_list;
    }


    /**
     * @return $this
     * @throws Exception
     */
    public function loadClient(){
        $client_list = $this->getClientList();

        if(empty( $client_list->{$this->client_id} )){
            throw new Exception("Client id is missing");
        }
        /** @var $client OPSkinsOAuth*/
        $client = $client_list->{$this->client_id};

        // This sucks but works for now
        // todo fix this
        $this->secret = $client->secret;
        $this->client_id = $client->client_id;
        $this->name = $client->name;
        $this->redirect_uri = $client->redirect_uri;
        $this->time_created = $client->time_created;
        $this->has_secret = $client->has_secret;
        if(!empty($client->authCode)){
            $this->authCode = $client->authCode;
        }
        if(!empty($client->bearerToken)){
            $this->bearerToken = $client->bearerToken;
        }

        return $this;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function storeClient(){

        $client_list = $this->getClientList();

        $client_list->{$this->client_id} = $this;

        if( !$this->verifyNonEmpty() ){
            throw new Exception("missing data");
        }

        $bytes = file_put_contents(\OPSkinsOAuthSettings::$clientsFileLocation, json_encode($client_list));

        return $bytes !== false ? true : false;
    }

    /**
     * @return bool
     */
    protected function verifyNonEmpty(){
        foreach (self::$requiredFields as $field){
            if ( empty($this->{$field}) ){
                return false;
            }
        }
        return true;
    }

}