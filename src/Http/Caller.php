<?php
namespace Http;

use \Monolog;

/**
 * Class Caller
 *
 * @package Http
 */
class Caller
{
    /**
     * @var Monolog\Logger
     */
    private $logger = null;

    /**
     * Connection timeout in seconds.
     *
     * @var int
     */
    private $connectionTimeout = 10;

    /**
     * @param Monolog\Logger $logger
     */
    public function __construct(Monolog\Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $url
     * @param array  $parameters
     * @param array  $additionalHeaders
     * @param bool   $disableCallResultDebugLog
     * @param int    $logLevelForStatusCode404  Monolog severity values; use Monolog\Logger::WARNING
     *
     * @return array
     */
    public function get(
        $url,
        array $parameters = array(),
        array $additionalHeaders = array(),
        $disableCallResultDebugLog = false,
        $logLevelForStatusCode404 = Monolog\Logger::WARNING
    ) {
        if (0 < \count($parameters)) {
            $url .= '?' . \http_build_query($parameters);
        }

        $this->logger->addDebug('calling GET: ' . $url);

        $curlHandler = $this->createCurlHandler($url, $additionalHeaders);

        return $this->executeCurl(
            $curlHandler,
            $url,
            $disableCallResultDebugLog,
            $logLevelForStatusCode404
        );
    }

    /**
     * @param string       $url
     * @param array|string $parameters                assoc array with parameter or http_build_query string
     * @param array        $additionalHeaders
     * @param bool         $disableCallResultDebugLog
     * @param int          $logLevelForStatusCode404  Monolog severity values; use Monolog\Logger::WARNING
     *
     * @return array
     */
    public function post(
        $url,
        $parameters,
        array $additionalHeaders = array(),
        $disableCallResultDebugLog = false,
        $logLevelForStatusCode404 = Monolog\Logger::WARNING
    ) {
        $this->logger->addDebug(
            'calling POST: ' . $url . ' with parameters ' . var_export($parameters, true)
        );

        $curlHandler = $this->createCurlHandler($url, $additionalHeaders);

        \curl_setopt_array(
            $curlHandler,
            array(
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => $parameters,
            )
        );

        return $this->executeCurl(
            $curlHandler,
            $url,
            $disableCallResultDebugLog,
            $logLevelForStatusCode404
        );
    }


    /**
     * @param string $url
     * @param array  $parameters
     * @param array  $additionalHeaders
     * @param bool   $disableCallResultDebugLog
     * @param int    $logLevelForStatusCode404 Monolog severity values; use Monolog\Logger::WARNING
     *
     * @return array
     */
    public function put(
        $url,
        array $parameters,
        array $additionalHeaders = array(),
        $disableCallResultDebugLog = false,
        $logLevelForStatusCode404 = Monolog\Logger::WARNING
    ) {
        $this->logger->addDebug(
            'calling PUT: ' . $url . ' with parameters ' . var_export($parameters, true)
        );

        $curlHandler = $this->createCurlHandler($url, $additionalHeaders);

        \curl_setopt_array(
            $curlHandler,
            array(
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS    => \http_build_query($parameters),
            )
        );

        return $this->executeCurl(
            $curlHandler,
            $url,
            $disableCallResultDebugLog,
            $logLevelForStatusCode404
        );
    }

    /**
     * @param string $url
     * @param array  $additionalHeaders
     * @param bool   $disableCallResultDebugLog
     * @param int    $logLevelForStatusCode404 Monolog severity values; use Monolog\Logger::WARNING
     *
     * @return array
     */
    public function delete(
        $url,
        array $additionalHeaders = array(),
        $disableCallResultDebugLog = false,
        $logLevelForStatusCode404 = Monolog\Logger::WARNING
    ) {
        $this->logger->addDebug(
            'calling DELETE: ' . $url
        );

        $curlHandler = $this->createCurlHandler($url, $additionalHeaders);

        \curl_setopt_array(
            $curlHandler,
            array(
                CURLOPT_CUSTOMREQUEST  => 'DELETE',
            )
        );

        return $this->executeCurl(
            $curlHandler,
            $url,
            $disableCallResultDebugLog,
            $logLevelForStatusCode404
        );
    }

    /**
     * @param string $url
     * @param array  $additionalHeaders
     *
     * @return resource
     */
    private function createCurlHandler($url, array $additionalHeaders)
    {
        $curlHandler = \curl_init();

        \curl_setopt_array(
            $curlHandler,
            array(
                CURLOPT_URL            => $url,
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => $this->connectionTimeout,
                CURLOPT_FOLLOWLOCATION => true,
            )
        );

        if (0 < \count($additionalHeaders)) {
            \curl_setopt($curlHandler, CURLOPT_HTTPHEADER, $additionalHeaders);
        }

        return $curlHandler;
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private function removeTokenParameter($url)
    {
        $urlData         = \parse_url($url);
        $queryStringData = array();

        if (false === \array_key_exists('query', $urlData)) {
            return $url;
        }

        $oldQueryString  = $urlData['query'];

        \parse_str($urlData['query'], $queryStringData);

        foreach ($queryStringData as $key => $value) {
            if ('token' === $key || 'access_token' === $key) {
                unset($queryStringData[$key]);
            }
        }

        $newQueryString = \http_build_query($queryStringData);

        return \str_replace($oldQueryString, $newQueryString, $url);
    }

    /**
     * @param resource $curlHandler
     * @param string   $url
     * @param bool     $disableCallResultDebugLog
     * @param int      $logLevelForStatusCode404
     *
     * @return array
     */
    private function executeCurl(
        $curlHandler,
        $url,
        $disableCallResultDebugLog,
        $logLevelForStatusCode404
    ) {
        $url        = $this->removeTokenParameter($url);
        $beforeCall = \microtime(true);

        $result       = \curl_exec($curlHandler);
        $responseCode = (int)\curl_getinfo($curlHandler, CURLINFO_HTTP_CODE);
        $curlErrno    = \curl_errno($curlHandler);
        $curlError    = \curl_error($curlHandler);

        $afterCall    = \microtime(true);
        $callDuration = $afterCall - $beforeCall;

        $this->logger->addDebug('callDuration: ' . \var_export($callDuration, true) . ' s for url: ' . $url);

        if (0 !== $curlErrno) {
            $this->logger->addError('curl error (' . $curlErrno . '): ' . $curlError . ' url: ' . $url);
        }

        if (500 <= $responseCode) {
            $this->logger->addError(
                'curl call error: ' . $responseCode . ' url: ' . $url
            );
        } elseif (300 <= $responseCode) {
            if (404 === $responseCode) {
                $this->logger->addRecord($logLevelForStatusCode404, 'curl call: ' . $responseCode . ' url: ' . $url);
            } else {
                $this->logger->addWarning('curl call warning: ' . $responseCode . ' url: ' . $url);
            }
        }

        if (false === $disableCallResultDebugLog) {
            $this->logger->addDebug('result: (' . $responseCode . ') ' . \var_export($result, true));
        }

        $result = array(
            'body'         => $result,
            'responseCode' => $responseCode,
        );

        \curl_close($curlHandler);

        return $result;
    }
}
