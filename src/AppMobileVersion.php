<?php

namespace Sysout\PhpAppMobileVersion;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
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
    private $ITUNES_URL = "http://itunes.apple.com/lookup";
    private $GOOGLE_PLAY_URL = "https://play.google.com/store/apps/details";

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
     * @return string versão
     */
    public function getIos($country = 'br') {

        try {

            if ($this->isUsingCache()) {

                $cacheDataResult = $this->getFromCache('ios');

                if ($cacheDataResult['status']) {
                    return $cacheDataResult['version'];
                }
            }

            $uri = $this->ITUNES_URL;

            $query = [
                'bundleId' => $this->options["bundleId"],
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

                    throw new AppMobileVersionException('Não foi possível retornar o um valor válido');
                }
            } else {

                throw new AppMobileVersionException('Não foi possível identificar a versão do app dentro do retorno');
            }

        } catch (BadResponseException $be) {

            throw new AppMobileVersionException('Falha ao realizar a requisição no Itunes');

        } catch (ConnectException $ce) {

            throw new AppMobileVersionException('Falha ao conectar na Itunes');
    
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * Retornar versão de um app android
     *
     * @param string $bundleId
     * @return string versão
     */
    public function getAndroid() {

        try {

            if ($this->isUsingCache()) {

                $cacheDataResult = $this->getFromCache('android');

                if ($cacheDataResult['status']) {
                    return $cacheDataResult['version'];
                }
            }

            $uri = $this->GOOGLE_PLAY_URL;

            $query = [
                'id' => $this->options["bundleId"],
            ];

            $options = [
                'query' => $query
            ];

            $response = $this->client->get($uri, $options);

            $body = $response->getBody();

            $html = $body->getContents();

            preg_match('/\[\[\[\"\d+\.\d+\.\d+/', $html, $output);

            $result = substr(current($output), 4) ?? null;

            if ($result) {

                // Salvar no cache
                $this->saveCache('android', $result);

                return $result;

            } else {

                throw new AppMobileVersionException('Não foi possível obter um resultado válido');
            }

        } catch (BadResponseException $be) {

            throw new AppMobileVersionException('Falha ao realizar a requisição na Play Store');

        } catch (ConnectException $ce) {

            throw new AppMobileVersionException('Falha ao conectar na Play Store');
    
        } catch (\Exception $e) {

            throw $e;
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
