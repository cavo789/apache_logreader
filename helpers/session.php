<?php

class logReaderSession
{
    protected static $instance = null;

    public function __construct($bDestroy = false)
    {
        // server should keep session data for AT LEAST 1 hour
        ini_set('session.gc_maxlifetime', 3600);

        // each client should remember their session id for EXACTLY 1 hour
        session_set_cookie_params(3600);

        if (!isset($_SESSION)) {
            session_start();
        }

        if ($bDestroy) {
            session_destroy();
        }

        return true;
    } // function __construct()

    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new logReaderSession();
        }

        return self::$instance;
    }

    public static function set($name, $value)
    {
        $_SESSION[$name] = $value;
    }

    public static function get($name, $defaultvalue)
    {
        return isset($_SESSION[$name]) ? $_SESSION[$name] : $defaultvalue;
    }
}
