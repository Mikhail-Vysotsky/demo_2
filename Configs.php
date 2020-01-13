<?php

/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 10.01.18
 * Time: 09:15
 *
 * Use:
 *  - Add new element in config/main/main.php to all users
 *  - Add property where
 *  - Override local config in config/usr/main.php
 * Example:
 *  Configs::get()->doca_root
 *
 * @property string doca_root docaplus root directory
 */
class Configs
{
    /**
     * @var array
     */
    private $_params = array();
    /**
     * @var array
     */
    private static $_instances = array();

    /**
     * @param string $config_name
     *
     * @return Configs
     */
    public static function get($config_name = 'main')
    {
        static $_instances = array();
        if (!isset($_instances[$config_name])) {

            self::$_instances[$config_name] = new Configs();

            $main_conf_path = dirname(__FILE__) . '/../config/main/' . $config_name . '.php';
            if (!file_exists($main_conf_path)) {
                die("File does not exist $main_conf_path");
            }

            $params = include $main_conf_path;

            //$usr_conf_path = APP_PATH . '/usr/configs/' . $config_name . '.php';
            $usr_conf_path = dirname(__FILE__) . '/../config/usr/' . $config_name . '.php';
            if (file_exists($usr_conf_path)) {
                $usr_params = include $usr_conf_path;

                foreach ($usr_params as $key => $val) {
                    $params[$key] = $val;
                }
            }

            self::$_instances[$config_name]->_params = $params;
        }

        return self::$_instances[$config_name];
    }

    /**
     * @param $name
     *
     * @return null
     */
    function __get($name)
    {
        if (array_key_exists($name, $this->_params)) {
            return $this->_params[$name];
        }
        return null;
    }
}
