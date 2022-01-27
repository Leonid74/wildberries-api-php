<?php

/**
 * Wildberries REST API Client Interface
 *
 * @author Leonid74 leonid@sheikman.ru
 */

namespace Leonid74\Wildberries;

interface WbApiInterface
{
    /**
     * Get the incomes
     *
     * @param $dateFrom
     */
    public function incomes( string $dateFrom = null );

    /**
     * Get the stocks
     *
     * @param $dateFrom
     */
    public function stocks( string $dateFrom = null );

    /**
     * Get the orders
     *
     * @param $dateFrom
     * @param $flag
     */
    public function orders( string $dateFrom = null, int $flag = 0 );

    /**
     * Get the sales
     *
     * @param $dateFrom
     * @param $flag
     */
    public function sales( string $dateFrom = null, int $flag = 0 );

    /**
     * Get the sales report details by period
     *
     * @param $dateFrom
     * @param $dateTo
     * @param $limit
     * @param $rrdid
     */
    public function reportDetailByPeriod( string $dateFrom = null, string $dateTo = null, int $limit = 100, int $rrdid = 0 );

    /**
     * Get the report on excise goods
     *
     * @param $dateFrom
     */
    public function exciseGoods( string $dateFrom = null );
}
