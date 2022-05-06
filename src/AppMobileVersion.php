<?php

namespace Sysout\PhpAppMobileVersion;

use GuzzleHttp\Client;
use Sysout\PhpAppMobileVersion\Exceptions\AppMobileVersionException;

/**
 * Class Mobile Version
 *
 * @author João Victor <joaovictor@sysout.com.br>
 * @since 14/04/2022 
 * @version 1.0.0
 */
class AppMobileVersion {

    private array $options;
    private $client;

    /**
     * Construtor da classe
     *
     * @param array $options
     */
    public function __construct(array $options) {

        if(isset($options["bundleId"])) {

            $this->options = $options;

            if ($this->isUsingCache() && !isset($this->options["cachePeriod"])) {
                $this->options["cachePeriod"] = 600;
            }

            $this->client = new Client([
                'timeout' => 10,
                'verify' => false
            ]);

        } else {
            throw new AppMobileVersionException('Bundle ID obrigatório!');
        }
    }

    /**
     * Retornar versão de um app ios
     *
     * @param string $packageName
     * @param string $country
     * @return $version
     */
    public function getIos($country = 'br')
    {

        try {

            if ($this->isUsingCache()) {

                $cacheDataResult = $this->getFromCache('ios');

                if ($cacheDataResult['status']) {
                    return $cacheDataResult['version'];
                }
            }

            $uri = "http://itunes.apple.com/lookup";

            $query = [
                'bundleId' => $this->bundleId,
                'country' => $country
            ];

            $options = [
                'query' => $query
            ];

            $response = $this->client->get($uri, $options);

            $body = $response->getBody();

            $json = $body->getContents();

            if ($json) {

                $data = json_decode($json);

                $version = $data->results[0]->version;

                if ($version) {

                    // Salvar no cache
                    $this->saveCache('ios', $version);

                    return $version;
                } else {

                    throw new \Exception('Não foi possível retornar o um valor válido');
                }
            } else {

                throw new \Exception('Não foi possível identificar a versão do app dentro do retorno');
            }
        } catch (\Exception $e) {

            return 'Não foi possível se conectar ao servidor / verifique sua conexão ';
        }
    }

    /**
     * Retornar versão de um app android
     *
     * @param string $bundleId
     * @return $result
     */
    public function getAndroid() {

        try {

            if ($this->isUsingCache()) {

                $cacheDataResult = $this->getFromCache('android');

                if ($cacheDataResult['status']) {
                    return $cacheDataResult['version'];
                }
            }

            $uri = "https://play.google.com/store/apps/details";

            $query = [
                'id' => $this->bundleId,
            ];

            $options = [
                'query' => $query
            ];

            $response = $this->client->get($uri, $options);

            $body = $response->getBody();

            $html = $body->getContents();

            preg_match_all('/<span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(.*?)<\/span><\/div><\/span>/s', $html, $output);

            $result = $output[1][3];

            if ($result) {

                return $result;
            } else {

                throw new \Exception('Não foi possível obter um resultado válido');
            }
        } catch (\Exception $e) {

            // Devolve erro de Curl
            // $message = $e->getMessage();

            return 'Não foi possível se conectar ao servidor';
        }
    }

        /**
     * Verifica se o cache está habilitado
     *
     * @return void
     */
    private function isUsingCache() {
        return ($this->options["useCache"] ?? false) && isset($this->options["cacheFilePath"]);
    }

    /**
     * Obter do cache
     *
     * @param $platform
     * @return string
     */
    private function getFromCache(string $platform) {

        if (file_exists($this->options["cacheFilePath"])) {
            
            $id = $this->options["bundleId"];

            //Carrega as informações do cache
            $cacheFileData = $this->loadCacheData();

            if ($cacheFileData && isset($cacheFileData[$id]) && isset($cacheFileData[$id][$platform])) {

                $data = $cacheFileData[$id][$platform];

                $now = date('Y-m-d H:i:s');

                if ($data['expired_at'] > $now) {

                    // O valor contido no cache é válido
                    return [
                        'status' => true,
                        'version' => $data['version']
                    ];
                }
            }
        }

        return [
            'status' => false
        ];
    }

    /**
     * Carrega as informações de cache gravadas
     */
    private function loadCacheData() {

        $cacheFilePath = $this->options["cacheFilePath"];

        if (file_exists($cacheFilePath)) {

            //Obtem o arquivo do caminho especificado
            $cacheFileContents = file_get_contents($cacheFilePath);

            $data = json_decode($cacheFileContents, true);

            if (json_last_error() == JSON_ERROR_NONE) {
                return $data;
            }

            return null;

        }

    }

    /**
     * Grava as informações obtidas no disco
     *
     * @param string $platform
     * @param string $version
     * @return void
     */
    private function saveCache(string $platform, string $version) {
            
        if ($this->isUsingCache()) {

            $id = $this->options["bundleId"];
            
            //Carrega as informações do cache
            $cacheFileData = $this->loadCacheData();

            if (!$cacheFileData) {
                $cacheFileData = [];
            }
    
            $period = $this->options["cachePeriod"];
    
            // Obter data que o cache será expirado
            $expiredAt = now()->addSeconds($period)->format('Y-m-d H:i:s');
    
            $cacheFileData[$id][$platform] = [
                'expired_at' => $expiredAt,
                'version' => $version
            ];
    
            $cacheFileDataEncoded = json_encode($cacheFileData);
    
            // Sobrescrever (ou criar) arquivo
            file_put_contents($this->options["cacheFilePath"], $cacheFileDataEncoded);

        }
    }
}
