<?php
namespace semknoxSearch\bin;
class wosoLogger
{
    /**
     * Detailed debug information
     */
    public const DEBUG = 100;
    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    public const INFO = 200;
    /**
     * Uncommon events
     */
    public const NOTICE = 250;
    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    public const WARNING = 300;
    /**
     * Runtime errors
     */
    public const ERROR = 400;
    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public const CRITICAL = 500;
    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    public const ALERT = 550;
    /**
     * Urgent alert.
     */
    public const EMERGENCY = 600;
    private $loggerObj = null;
    public function __construct () {
        if (is_null($this->loggerObj)) {
            if (Shopware()->Container()->has('corelogger')) {
                $this->loggerObj = Shopware()->Container()->get('corelogger');
            }
            if (Shopware()->Container()->has('pluginlogger')) {
                $this->loggerObj = Shopware()->Container()->get('pluginlogger');
            }
        }
        if (is_null($this->loggerObj)) {
            return;
        }        
    }
    public function debug($message, array $context = []): void {
        if (is_null($this->loggerObj)) {
            return;
        }
        $this->loggerObj->debug($message, $context);
    }
    public function info($message, array $context = []): void {
        if (is_null($this->loggerObj)) {
            return;
        }
        $this->loggerObj->info($message, $context);
    }
    public function notice($message, array $context = []): void {
        if (is_null($this->loggerObj)) {
            return;
        }
        $this->loggerObj->notice($message, $context);
    }
    public function warning($message, array $context = []): void {
        if (is_null($this->loggerObj)) {
            return;
        }
        $this->loggerObj->warning($message, $context);
    }
    public function error($message, array $context = []): void {
        if (is_null($this->loggerObj)) {
            return;
        }
        $this->loggerObj->error($message, $context);
    }
    public function critical($message, array $context = []): void {
        if (is_null($this->loggerObj)) {
            return;
        }
        $this->loggerObj->critical($message, $context);
    }
    public function alert($message, array $context = []): void {
        if (is_null($this->loggerObj)) {
            return;
        }
        $this->loggerObj->alert($message, $context);
    }
    public function emergency($message, array $context = []): void {
        if (is_null($this->loggerObj)) {
            return;
        }
        $this->loggerObj->emergency($message, $context);
    }
}
