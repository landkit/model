<?php

namespace LandKit\Model;

use PDO;
use PDOException;

class Connect
{
    /**
     * @var array|null
     */
    private static $instance = null;

    /**
     * @var PDOException|null
     */
    private static $fail = null;

    /**
     * Connect constructor.
     */
    private function __construct()
    {
    }

    /**
     * Connect clone.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * @param string $database
     * @return PDO|null
     */
    public static function instance(string $database = 'default')
    {
        try {
            if (!defined('CONF_DATABASE') || !isset(CONF_DATABASE[$database])) {
                throw new PDOException("Database configuration '{$database}' not found.");
            }

            $dbConf = CONF_DATABASE[$database];
            $dbKey = "{$dbConf['driver']}-{$dbConf['dbname']}@{$dbConf['host']}";

            if (empty(self::$instance[$dbKey])) {
                self::$instance[$dbKey] = new PDO(
                    "{$dbConf['driver']}:host={$dbConf['host']};dbname={$dbConf['dbname']};port={$dbConf['port']}",
                    $dbConf['username'],
                    $dbConf['password'],
                    $dbConf['options']
                );
            }

            return self::$instance[$dbKey];
        } catch (PDOException $e) {
            self::$fail = $e;
        }

        return null;
    }

    /**
     * @return PDOException|null
     */
    public static function fail()
    {
        return self::$fail;
    }
}
