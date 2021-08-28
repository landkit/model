<?php

namespace LandKit\Model;

use PDO;
use PDOException;

class Connect
{
    /**
     * @var PDO|null
     */
    private static $instance = null;

    /**
     * @var PDOException|null
     */
    private static $fail = null;

    /**
     * Block Connect instance generation.
     */
    private function __construct()
    {
    }

    /**
     *  Block Connect instance cloning.
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
        if (empty(self::$instance)) {
            try {
                if (!defined('CONF_DATABASE') || !isset(CONF_DATABASE[$database])) {
                    throw new PDOException('Database configuration not found.');
                }

                self::$instance = new PDO(
                    CONF_DATABASE[$database]['driver']
                    . ':host=' . CONF_DATABASE[$database]['host']
                    . ';port=' . CONF_DATABASE[$database]['port']
                    . ';dbname=' . CONF_DATABASE[$database]['dbname'],
                    CONF_DATABASE[$database]['username'],
                    CONF_DATABASE[$database]['password'],
                    CONF_DATABASE[$database]['options'] ?? CONF_DATABASE['global']['options']
                );
            } catch (PDOException $e) {
                self::$fail = $e;
            }
        }

        return self::$instance;
    }

    /**
     * @return PDOException|null
     */
    public static function fail()
    {
        return self::$fail;
    }
}