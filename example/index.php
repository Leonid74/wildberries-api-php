<?php

/**
 * Wildberries REST API Client Usage Example
 *
 * @see Wildberries REST API statistics Documentation
 *      (https://images.wbstatic.net/portal/education/Kak_rabotat'_s_servisom_statistiki.pdf)
 * @see Wildberries REST API Documentation
 *      (https://suppliers-api.wildberries.ru/swagger/index.html)
 *
 * @author Leonid74 leonid@sheikman.ru
 */

use Leonid74\Wildberries\WbApiClient;

require_once 'vendor/autoload.php';

$token = '<you token x64>';
$dateFrom = '01-01-2022';
$dateTo = '19-01-2022';

try {
    $WbApiClient = new WbApiClient( $token );
    $WbApiClient->debugLevel = WbApiClient::DEBUG_URL;
    $WbApiClient->throttle = 2;
} catch ( Exception $e ) {
    die( "Критическая ошибка при создании ApiClient: ({$e->getCode()}) " . $e->getMessage() );
}

$sales = $WbApiClient->sales( $dateFrom );
if ( isset( $sales->is_error ) ) {
    echo "\nError: " . implode( '; ', $sales->errors );
} else {
    var_dump( $sales );
}

$reportDetailByPeriod = $WbApiClient->reportDetailByPeriod( $dateFrom, $dateTo );
if ( isset( $reportDetailByPeriod->is_error ) ) {
    echo "\nError: " . implode( '; ', $reportDetailByPeriod->errors );
} else {
    var_dump( $reportDetailByPeriod );
}

// You can set a common date (dateFrom) via the setDateFrom() function and then access other functions without passing the date
// Можно задать общую дату (dateFrom) через функцию setDateFrom() и затем обращаться к другим функциям, не передавая дату
$WbApiClient->setDateFrom( $dateFrom );
$sales = $WbApiClient->sales();
$incomes = $WbApiClient->incomes();
