<?php
namespace SitecRESA\WS;
use \Sitec_Rest_Client;
use \Zend_Json;
use SitecRESA\Datatype\DatatypeAbstract;

if (!ini_get('date.timezone') && function_exists('date_default_timezone_set')) {
    date_default_timezone_set('UTC');
}
/**
 * Objet qui fera la passerelle entre l'API et les services web SitecRESA
 *
 * @author Patrice Brun <patrice.brun@sitec.fr>
 *
 * @example exemples.php différents exemples
 */
class ApiClient {
    // the version of the discovery mechanism this class is meant to work with

    const VERSION_EXISTE = '1.0/2.0/2.1/2.2';
    const PREFIX_PATH = "/ws/";
    const FORMAT = "json";

    private $version = '2.3';
    private $client = null;
    private $sApiKey = null;
    private $sSecretKey = null;
    private $panier;

    /**
     * objet qui fera la passerelle entre l'API et les services web SitecRESA.
     * Il suffit de le construire et de le passer à méthode d'un objet ou d'une Classe héritant de {@see SitecRESA\Datatype\DatatypeAbstract}
     * @param array $config configuration particulière à l'instance.
     * Les configuration permanentes doivent être définies dans le fichier /config.php
     */
    public function __construct($apiConfig) {
        if(!isset($apiConfig['url'])
                || !isset($apiConfig['apiKey'])
                || !isset($apiConfig['secretKey'])){
            throw new Exception\Api("You have to give an array with path, url, apiKey and secretKey to create a client");
        }
        $this->sApiKey = $apiConfig['apiKey'];
        $this->sSecretKey = $apiConfig['secretKey'];
        $apiClient = new \Guzzle\Http\Client($apiConfig['url'], array(
            'request.params' => array(
                'cache.override_ttl' => 3600,
            )
        ));

        $canCache = new \Guzzle\Plugin\Cache\CallbackCanCacheStrategy(
            function ($request) {
                $dateDebut = $request->getQuery()->get('dateDebut');
                $fin = $request->getQuery()->get('dateFin');
                if ((!isset($dateDebut) && !isset($fin) && preg_match("%organisme\/get%",$request->getUrl()))
                    || (!isset($dateDebut) && !isset($fin) && preg_match("%produit\/get%",$request->getUrl()))
                    || (!isset($dateDebut) && !isset($fin) && preg_match("%conditionretenue\/get%",$request->getUrl()))
                    || (!isset($dateDebut) && !isset($fin) && preg_match("%equipementscategorieproduit\/get%",$request->getUrl()))
                )
                {
                    return true;
                }

                return false;
            }
        );

        $cachePlugin = new \Guzzle\Plugin\Cache\CachePlugin(array(
            'storage' => new \Guzzle\Plugin\Cache\DefaultCacheStorage(
                new \Guzzle\Cache\DoctrineCacheAdapter(
                    new \Doctrine\Common\Cache\FilesystemCache($apiConfig['cache'])
                )
            ),
            'can_cache' => $canCache
        ));

        $apiClient->addSubscriber($cachePlugin);

        $this->client = $apiClient;
    }

    public function setPanier($panier) {
        $this->panier = $panier;
    }

    /**
     *
     * @throws apiException
     * @param string $name
     * @param array  $arguments
     * @return DatatypeAbstract objet représentant les données du web service.
     */
    public function __call($name, $arguments) {
        $iIdRessource = "index";
        if (count($arguments) != 2) {
            throw new \SitecRESA\Exception\Api("client method calls expect 2 parameter");
        }
        if (!is_array($arguments[1])) {
            throw new \SitecRESA\Exception\Api("client arguments parameter should be an array");
        }

        $aParams = $arguments[1];
        $sVerbe = $arguments[0];
        if (array_key_exists('idRessource', $aParams)){
            $iIdRessource = $aParams['idRessource'];
            unset($aParams['idRessource']);
        }
        if (!array_key_exists('format', $aParams)){
            $aParams['format'] = self::FORMAT;
        }

        $aParams['requestHash'] = hash_hmac("sha1", $this->sApiKey . '-' . time(), $this->sSecretKey);
        $aParams['apiKey']      = $this->sApiKey;
        $aParams['timestamp']   = time();
        $aParams['version']     = $this->version;
        $name = self::PREFIX_PATH.$name;
        if($this->panier && $this->panier instanceof \SitecRESA\Datatype\Panier){
            $aParams["identifiantPanier"] = $this->panier->id;
        }

//        $client = \Zend_Rest_Client::getHttpClient();
        if  (count($aParams) > 0) {
            if (!isset($aParams["dateDebut"])) {
                $aParams["dateDebut"] = null;
            }

            if (!isset($aParams["dateFin"])) {
                $aParams["dateFin"] = null;
            }
        }

        switch ($sVerbe) {
            case "post":
                $response = $this->client->restPost($name, $aParams);
                break;

            case "put":
                $response = $this->client->restPut($name  . "/" . $iIdRessource, $aParams);
                break;

            case "get":
                $request = $this->client->createRequest('GET', $name.'/get/'.$iIdRessource.'/format/json');
                $request->setHeaders($aParams);
                $response = $this->client->send($request);
                break;

            case "delete":
                $response = $this->client->restDelete($name . "/" . $iIdRessource);
                break;
            default :
                throw new \SitecRESA\Exception\Api("$sVerbe n'est pas une requête HTTP gérée par ce Web Service");
        }

        if ($response->getStatusCode() == 401) {
            throw new \SitecRESA\Exception\Auth($response->getBody() . " status : " . $response->getStatusCode(), $response->getStatusCode());
        }

        if ($response->getStatusCode() == 412) {
            return $this->doResponse($response->getBody());//throw new \SitecRESA\Exception\IO($response->getBody(),$response->getStatus() );
        }

        if ($response->getStatusCode() == 201) {//créé et l'accès est disponible
            return $response->getHeader("Location");
        }
        if ($response->getStatusCode() == 204) {//pas de contenu.
            return;
        }

        if ($response->getStatusCode() != 200) {
            throw new \SitecRESA\Exception\Api($response->getBody(), $response->getStatusCode());
        }

        return $this->doResponse($response->getBody());

    }

     /**
     *
     * @throws Zend_Json_Exception
     * @param  string  $sResponse
     * @return Sitec_Rest_Response
     */
    public function doResponse($sResponse) {
        $apiClient = new \SitecRESA\WS\Client(array("apiKey" => $this->sApiKey, "secretKey" => $this->sSecretKey, "url" => $this->client->getBaseUrl()));
        try{
            $result = Zend_Json::decode($sResponse);
            if($this->isAssociativeArray($result)){
                return DatatypeAbstract::createObjectFromArray($apiClient, $result);
            }  else {
                $aoResults = array();
                foreach ($result as $resultPiece){
                    $aoResults[] = DatatypeAbstract::createObjectFromArray($apiClient, $resultPiece);
                }
                return $aoResults;
            }
        }catch(\Zend_Json_Exception $e){
            throw new \SitecRESA\Exception\IO("La réponse n'est pas au format attendu : $sResponse");
        }
    }

    /**
    * Returns true only if the array is associative.
    * @param array $array
    * @return bool True if the array is associative.
    */
    private function isAssociativeArray($array) {
        if (!is_array($array)) {
          return false;
        }
        $keys = array_keys($array);
        foreach($keys as $key) {
          if (is_string($key)) {
            return true;
          }
        }
        return false;
    }

    /**
     * @param $verbe
     * @param $url
     * @param array $aQuery
     * @return \Guzzle\Http\Message\RequestInterface
     */
    public function createRequest($verbe,$url,$aQuery = array()){
        $aParams = array();
        $aParams['requestHash'] = hash_hmac("sha1", $this->sApiKey . '-' . time(), $this->sSecretKey);
        $aParams['apiKey']      = $this->sApiKey;
        $aParams['timestamp']   = time();
        $aParams['version']     = $this->version;
        $request = $this->client->createRequest($verbe,$url);
        $request->setHeaders($aParams);
        if(sizeof($aQuery)){
            $query = $request->getQuery();
            foreach($aQuery as $key=>$value){
                $query->set($key, $value);
            }
        }
        return $request;
    }

    /**
     * @param $request
     */
    public function send($request){
        return $this->client->send($request);
    }
}
