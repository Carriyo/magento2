<?php


/**
 * Description of Logger
 *
 */

namespace Carriyo\Shipment\Logger;


class Logger extends \Monolog\Logger
{

    /**
     * @var Handler
     */
    protected $handler;


    public function __construct(
        Handler $handler
    )
    {
        $this->handler = $handler;
        parent::__construct("carriyo", ["handlers" => $this->handler]);
    }

    public function error($message, array $context = array())
    {
        //add helper method to send message to external system
        return $this->addRecord(static::CRITICAL, $message, $context);

    }
}
