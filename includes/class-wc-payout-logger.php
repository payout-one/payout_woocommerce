<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Payout_Logger {

    public static $logger;
    const WC_LOG_SOURCE = 'payout';

    public static function log($message) {
        if (!class_exists('WC_Logger')) {
            return;
        }

        if (empty(self::$logger)) {
            self::$logger = wc_get_logger();
        }

        self::$logger->debug($message, ['source' => self::WC_LOG_SOURCE]);
    }
}
