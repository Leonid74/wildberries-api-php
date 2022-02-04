<?php

/**
 * Wildberries REST API Client
 *
 * @see Wildberries REST API statistics Documentation
 *      (https://images.wbstatic.net/portal/education/Kak_rabotat'_s_servisom_statistiki.pdf)
 * @see Wildberries REST API Documentation
 *      (https://suppliers-api.wildberries.ru/swagger/index.html)
 *
 * @author Leonid74 leonid@sheikman.ru
 *
 * @uses http-client parts of code (https://github.com/andrey-tech/http-client-php)
 */

namespace Leonid74\Wildberries;

use Exception;
use stdClass;
use Josantonius\HTTPStatusCode\HTTPStatusCode;

class WbApiClient implements WbApiInterface
{
    /**
     * Constants of the output level of debugging information
     * Константы уровня вывода отладочной информации
     *
     * @var int
     */
    public const DEBUG_NONE = 0;    // 0 - не выводить
    public const DEBUG_URL = 1;     // 1 - URL запросов/ответов
    public const DEBUG_HEADERS = 2; // 2 - заголовки запросов/ответов
    public const DEBUG_CONTENT = 3; // 3 - содержимое запросов/ответов

    /**
     * The default output level of debugging information
     * Уровень вывода отладочной информации по-умолчанию
     *
     * @var int
     */
    public $debugLevel = self::DEBUG_NONE;

    /**
     * Maximum number of HTTP requests per second (0 - trottling disabled)
     * Максимальное число HTTP запросов в секунду (0 - троттлинг отключен)
     *
     * @var float
     */
    public $throttle = 3;

    /**
     * HTTP status codes corresponding to the successful execution of the request
     * Коды статуса НТТР, соответствующие успешному выполнению запроса
     *
     * @var array
     */
    public $successStatusCodes = [ 200 ];

    /**
     * Connection timeout for cUrl, seconds
     * Таймаут соединения для cUrl, секунд
     *
     * @var int
     */
    public $curlConnectTimeout = 30;

    /**
     * Data exchange timeout for cUrl, seconds
     * Таймаут обмена данными для cUrl, секунд
     *
     * @var int
     */
    public $curlTimeout = 300;

    /**
     * Time of the last request, microseconds
     * Время последнего запроса, микросекунды
     *
     * @var float
     */
    private $lastRequestTime = 0;

    /**
     * Counter of the requests for debugging messages
     * Счетчик запросов для отладочных сообщений
     *
     * @var int
     */
    private $requestCounter = 0;

    /**
     * cURL Resource
     * Ресурс cURL
     *
     * @var \CurlHandle
     */
    private $curl;

    /**
     * The API address of the service
     * Адрес API сервиса
     *
     * @var string
     */
    private $apiUrl = 'https://suppliers-stats.wildberries.ru/api/v1/supplier';

    /**
     * Partner Token
     * Токен партнера
     *
     * @var string
     */
    private $token;

    /**
     * Date and time in the format of the RFC 3339 standard
     * Дата и время в формате стандарта RFC3339
     *
     * @var string
     */
    private $dateFrom;

    /**
     * Wildberries API constructor
     * Wildberries API конструктор
     *
     * @param $token
     * @param $dateFrom
     *
     * @throws Exception
     */
    public function __construct( ?string $token = '', string $dateFrom = null )
    {
        if ( empty( $token ) ) {
            throw new Exception( 'The Token is not specified' );
        }

        $this->token = $token;
        $this->dateFrom = $dateFrom;
    }

    /**
     * Get the token
     * Получить токен
     *
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Get the date set using setDateFrom()
     * Получить установленную с помощью setDateFrom() дату
     *
     * @return string
     */
    public function getDateFrom(): ?string
    {
        return $this->dateFrom;
    }

    /**
     * Set dateFrom
     * Установить дату
     *
     * @return string
     */
    public function setDateFrom( string $dateFrom = null ): WbApiClient
    {
        $this->dateFrom = $dateFrom;

        return $this;
    }

    /**
     * Form and send request to API service
     * Формируем и отправляем запрос к API сервису
     *
     * @param string $path
     * @param string $method
     * @param array  $data
     *
     * @return stdClass
     */
    protected function sendRequest( string $path, string $method = 'GET', array $data = [] )
    {
        if ( empty( $this->token ) ) {
            return $this->handleError( 'The Token is not specified' );
        }
        if ( !isset( $data['dateFrom'] ) ) {
            return $this->handleError( 'The dateFrom parameter is not specified' );
        }

        $data['dateFrom'] = date( DATE_RFC3339, strtotime( $data['dateFrom'] ) );
        $data['dateTo'] = date( DATE_RFC3339, strtotime( isset( $data['dateTo'] ) ? $data['dateTo'] : 'now' ) );
        $data['key'] = $this->token;
        $url = $this->apiUrl . '/' . $path;
        $method = strtoupper( $method );
        $headers = [ 'Content-Type: application/json' ];

        $this->curl = curl_init();

        switch ( $method ) {
            case 'POST':
                curl_setopt( $this->curl, CURLOPT_POST, true );
                curl_setopt( $this->curl, CURLOPT_POSTFIELDS, http_build_query( $data ) );
                break;
            case 'PUT':
                curl_setopt( $this->curl, CURLOPT_CUSTOMREQUEST, 'PUT' );
                curl_setopt( $this->curl, CURLOPT_POSTFIELDS, http_build_query( $data ) );
                break;
            case 'DELETE':
                curl_setopt( $this->curl, CURLOPT_CUSTOMREQUEST, 'DELETE' );
                curl_setopt( $this->curl, CURLOPT_POSTFIELDS, http_build_query( $data ) );
                break;
            default:
                if ( !empty( $data ) ) {
                    $url .= '?' . http_build_query( $data );
                }
        }

        curl_setopt( $this->curl, CURLOPT_URL, $url );
        curl_setopt( $this->curl, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $this->curl, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $this->curl, CURLOPT_HEADER, true );
        curl_setopt( $this->curl, CURLOPT_CONNECTTIMEOUT, $this->curlConnectTimeout );
        curl_setopt( $this->curl, CURLOPT_TIMEOUT, $this->curlTimeout );

        $this->requestCounter++;

        // Print the url and headers of the request
        // Выводим url и заголовки запроса
        $this->debug( "[{$this->requestCounter}] ===> REQUEST {$method} {$url}", self::DEBUG_URL );
        if ( !empty( $headers ) ) {
            curl_setopt( $this->curl, CURLOPT_HTTPHEADER, $headers );
            $this->debug( "[{$this->requestCounter}] ===> REQUEST HEADERS:\n" . var_export( $headers, true ), self::DEBUG_HEADERS );
        }

        $response = $this->throttleCurl();
        $deltaTime = sprintf( '%0.4f', microtime( true ) - $this->lastRequestTime );

        $curlErrors = curl_error( $this->curl );
        $curlInfo = curl_getinfo( $this->curl );
        $ipAddress = $curlInfo['primary_ip'];
        $header_size = $curlInfo['header_size'];
        $headerCode = $curlInfo['http_code'];
        $responseHeaders = trim( substr( $response, 0, $header_size ) );
        $responseBodyRaw = substr( $response, $header_size );
        $responseBody = json_decode( $responseBodyRaw );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $responseBody = $responseBodyRaw;
        }
        unset( $response, $responseBodyRaw );

        curl_close( $this->curl );

        // Print the headers of the request again
        // Выводим еще заголовки запроса
        if ( isset( $curlInfo['request_header'] ) ) {
            $this->debug( "[{$this->requestCounter}] ===> REQUEST HEADERS:\n" . $curlInfo['request_header'], self::DEBUG_HEADERS );
        }

        // Print the request parameters
        // Выводим параметры запроса
        $this->debug( "[{$this->requestCounter}] ===> REQUEST PARAMS:\n" . var_export( $data, true ), self::DEBUG_CONTENT );

        $retval = new stdClass();
        $retval->data = $responseBody;
        $retval->http_code = $headerCode;
        $retval->headers = $responseHeaders;
        $retval->ip = $ipAddress;
        $retval->curlErrors = $curlErrors;
        $retval->method = $method . ':' . $url;
        $retval->timestamp = date( DATE_RFC3339 );

        // Print the url, headers and the result of the response
        // Выводим url, заголовки и результат ответа
        $this->debug( "[{$this->requestCounter}] <=== RESPONSE TIME IN {$deltaTime}s (CODE: {$headerCode})", self::DEBUG_URL );
        $this->debug( "[{$this->requestCounter}] <=== RESPONSE HEADERS:\n{$responseHeaders}", self::DEBUG_HEADERS );
        $this->debug( "[{$this->requestCounter}] <=== RESPONSE RESULT:\n" . var_export( ['info' => $curlInfo, 'result' => $this->handleResult( $retval )], true ), self::DEBUG_CONTENT );

        return $retval;
    }

    /**
     * Provides trottling of HTTP requests
     * Обеспечивает троттлинг HTTP запросов
     *
     * @return string|false
     */
    private function throttleCurl()
    {
        do {
            if ( empty( $this->throttle ) ) {
                break;
            }

            // Calculate the required delay time before sending the request, microseconds
            // Вычисляем необходимое время задержки перед отправкой запроса, микросекунды
            $usleep = (int)( 1E6 * ( $this->lastRequestTime + 1 / $this->throttle - microtime( true ) ) );
            if ( $usleep <= 0 ) {
                break;
            }

            $sleep = sprintf( '%0.4f', $usleep / 1E6 );
            $this->debug( "[{$this->requestCounter}] +++++ THROTTLE REQUEST (" . $this->throttle . "/sec) {$sleep}'s +++++", self::DEBUG_URL );
            usleep( $usleep );
        } while ( false );

        do {
            $this->lastRequestTime = microtime( true );
            $response = curl_exec( $this->curl );

            $oneMoreTry = curl_getinfo( $this->curl, CURLINFO_RESPONSE_CODE ) == 429;
            if ( $oneMoreTry ) {
                $this->debug( "[{$this->requestCounter}] +++++ TOO MANY REQUESTS, WAITING 0.5sec +++++", self::DEBUG_URL );
                usleep( 500000 );
            }
        } while ( $oneMoreTry );

        return $response;
    }

    /**
     * Outputs debugging messages to STDOUT at a given level of debugging information output
     * Выводит в STDOUT отладочные сообщения на заданном уровне вывода отладочной информации
     *
     * @param string
     * @param int
     *
     * @return void
     */
    protected function debug( string $message, int $callerLogLevel = 999 ): void
    {
        if ( $this->debugLevel >= $callerLogLevel ) {
            echo $message . PHP_EOL;
        }
    }

    /**
     * Process results
     * Обрабатываем результат
     *
     * @param mixed
     *
     * @return stdClass
     */
    protected function handleResult( $data )
    {
        if ( empty( $data->data ) ) {
            $data->data = new stdClass();
        }
        if ( !in_array( $data->http_code, $this->successStatusCodes ) || isset( $data->data->errors ) ) {
            $data->data->is_error = true;
            if ( !isset( $data->data->errors ) ) {
                $data->data->errors[] = HTTPStatusCode::get( $data->http_code );
            }
            if ( !empty( $data->curlErrors ) ) {
                $data->data->errors[] = $data->curlErrors;
            }
            $data->data->http_code = $data->http_code;
            $data->data->headers = $data->headers;
            $data->data->ip = $data->ip;
            $data->data->method = $data->method;
            $data->data->timestamp = $data->timestamp;
        }

        return $data->data;
    }

    /**
     * Process errors
     * Обрабатываем ошибки
     *
     * @param string|null
     *
     * @return stdClass
     */
    protected function handleError( ?string $customMessage = null )
    {
        $message = new stdClass();
        $message->is_error = true;
        if ( null !== $customMessage ) {
            $message->message = $customMessage;
        }

        return $message;
    }

    /**
     * API interface implementation
     */

    /**
     * Get the incomes
     * Получить поставки
     *
     * @param string
     *
     * @return stdClass
     */
    public function incomes( string $dateFrom = null )
    {
        $data = ['dateFrom' => $dateFrom ?? $this->dateFrom ?? null];
        $requestResult = $this->sendRequest( 'incomes', 'GET', $data );

        return $this->handleResult( $requestResult );
    }

    /**
     * Get the stocks
     * Получить складскую информацию
     *
     * @param string
     *
     * @return stdClass
     */
    public function stocks( string $dateFrom = null )
    {
        $data = ['dateFrom' => $dateFrom ?? $this->dateFrom ?? null];
        $requestResult = $this->sendRequest( 'stocks', 'GET', $data );

        return $this->handleResult( $requestResult );
    }

    /**
     * Get the orders
     * Получить заказы
     *
     * @param string
     * @param int
     *
     * @return stdClass
     */
    public function orders( string $dateFrom = null, int $flag = 0 )
    {
        if ( $flag < 0 || $flag > 1 ) {
            return $this->handleError( 'The flag value must be 0 or 1' );
        }

        $data = ['dateFrom' => $dateFrom ?? $this->dateFrom ?? null, 'flag' => $flag];
        $requestResult = $this->sendRequest( 'orders', 'GET', $data );

        return $this->handleResult( $requestResult );
    }

    /**
     * Get the sales
     * Получить продажи
     *
     * @param string
     * @param int
     *
     * @return stdClass
     */
    public function sales( string $dateFrom = null, int $flag = 0 )
    {
        if ( $flag < 0 || $flag > 1 ) {
            return $this->handleError( 'The flag value must be 0 or 1' );
        }

        $data = ['dateFrom' => $dateFrom ?? $this->dateFrom ?? null, 'flag' => $flag];
        $requestResult = $this->sendRequest( 'sales', 'GET', $data );

        return $this->handleResult( $requestResult );
    }

    /**
     * Get the sales report details by period
     * Получить отчет о продажах по реализации
     *
     * @param string
     * @param string
     * @param int
     * @param int
     *
     * @return stdClass
     */
    public function reportDetailByPeriod( string $dateFrom = null, string $dateTo = null, int $limit = 100, int $rrdid = 0 )
    {
        $data = ['dateFrom' => $dateFrom ?? $this->dateFrom ?? null, 'dateTo' => $dateTo, 'limit' => $limit, 'rrdid' => $rrdid];
        $requestResult = $this->sendRequest( 'reportDetailByPeriod', 'GET', $data );

        return $this->handleResult( $requestResult );
    }

    /**
     * Get the report on excise goods
     * Получить отчет по КиЗам
     *
     * @param string
     *
     * @return stdClass
     */
    public function exciseGoods( string $dateFrom = null )
    {
        $data = ['dateFrom' => $dateFrom ?? $this->dateFrom ?? null];
        $requestResult = $this->sendRequest( 'exciseGoods', 'GET', $data );

        return $this->handleResult( $requestResult );
    }
}
