<?php

namespace Sofi\Base;

/**
 * Сокращение для DIRECTORY_SEPARATOR
 */
define('DS', DIRECTORY_SEPARATOR);

class Sofi
{

    /**
     * Базовые настройким <br>
     * устанавливаются методом addConfig(array)
     * @see addConfig()
     * @var array 
     */
    public static $general = [
        'time-zone' => 'Europe/Moscow',
        'time-limit' => 30,
        'charset' => 'UTF-8',
        'ignore_user_abort' => false,
        'ini' => [
            'display_errors' => true
        ],
        'autoloader' => false,
        'session' => true,
        'application-class' => 'Sofi\Application'
    ];
    static protected $app = null;
    static private $baseAppConfig = [
        'components' => [
            'Response' => 'Sofi\HTTP\message\Response',
            'Request' => [
                'class' => 'Sofi\HTTP\message\Request',
                'creator' => 'createFromGlobals'
            ],
            'Router' => 'Sofi\Router\Router',
            'Layout' => [
                'class' => 'Sofi\mvc\Layout',
                'params' => [],
                'name' => 'main/main'
            ]
        ]
    ];

    /**
     * Возвращает true если запущенно из консоли
     * 
     * @return boolean
     */
    public static function isConsole()
    {
        return PHP_SAPI == 'cli' ||
                (!isset($_SERVER['DOCUMENT_ROOT']) &&
                !isset($_SERVER['REQUEST_URI']));
    }

    /**
     * Добавление параметров конфигурации
     * 
     * @param array $config
     */
    public static function addConfig($config = [])
    {
        self::$general = array_merge(self::$general, $config);
    }
    
    public static function getAppConfig()
    {
        return self::$baseAppConfig;
    }

    /**
     * Иницилизация фреймворка
     * - загрузка конфигураций
     * - установка параметров
     * - установка путей
     * ...
     * 
     * @param array $config
     * @return \Sofi\Base\Sofi
     */
    public static function init($config = [])
    {
        /**
         * Определение пути запуска скрипта
         */
        if (!defined('PUBLIC_PATH')) {
            $debug = debug_backtrace();
            define('PUBLIC_PATH', realpath(dirname($debug[0]['file'])) . DS);
        }

        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(PUBLIC_PATH) . DS);
        }

        self::addConfig($config);

        /*
         * General init
         */
        foreach (self::$general['ini'] as $ini => $val) {
            if (is_int($ini)) {
                ini_set($val, true);
            } else {
                ini_set($ini, $val);
            }
        }

        if (self::$general['session']) {
            session_start();
        }

        ignore_user_abort(self::$general['ignore_user_abort']);

        set_time_limit(self::$general['time-limit']);
        date_default_timezone_set(self::$general['time-zone']);
        mb_internal_encoding(self::$general['charset']);
        mb_regex_encoding(self::$general['charset']);
    }

    /**
     * 
     * @param array $config
     * @return \Sofi\Application
     */
    static function app($config = null)
    {
        if (self::$app == null) {
            if (is_array($config)) {
                $config = array_merge(static::$baseAppConfig, $config);
            } else {
                $config = self::getAppConfig();
            }
            self::$app = self::createObject(self::$general['application-class'], $config);
//            Sofi::d($config);
        }

        return self::$app;
    }

    public static function createObject($type, $params = [])
    {
        if (is_string($type)) {
            $class = new $type();
            if (method_exists($class, 'init')) {
                $class->init($params);
                //call_user_func([$class, 'init'], $params);
            }

            return $class;
        } elseif (is_array($type) && isset($type['class'])) {
            $class = $type['class'];
            unset($type['class']);

            if (isset($type['creator'])) {
                $method = $type['creator'];
                return $class::$method($params);
                //return call_user_func_array([$class,$method], $params); //$class::$method(... $params);
            } else {
                return static::createObject($class, $type);
            }
        } elseif (is_callable($type, true)) {
            return call_user_func_array($type, $params); // $type()(... $params);
        } elseif (is_array($type)) {
            throw new exceptions\InvalidConfig('Object configuration must be an array containing a "class" element.');
        } else {
            throw new exceptions\InvalidConfig('Unsupported configuration type: ' . gettype($type));
        }
    }

    static function exec($action, array $params = [])
    {
        // замыкание
        if ($action instanceof \Closure) {
            return call_user_func_array($action, $params);
        } elseif (is_callable($action)) {
            return $action(...array_values($params));

            // Строка
        } elseif (is_string($action)) {
            if (mb_strpos($action, '{')) {
                foreach ($params as $key => $val) {
                    $action = str_replace('{' . $key . '}', $val, $action);
                }
            }

            $callback = explode('@', $action);
            $class = static::createObject($callback[0]);

            if (method_exists($class, $callback[1])) {
                return call_user_func_array([$class, $callback[1]], $params);
            } else {
                throw new exceptions\InvalidRouteCallback('Bad method callback ' . $action);
            }

            //  Массив
        } elseif (is_array($action)) {
            $class = static::createObject($action);

            return $class->__invoke($params);
        }
    }
    
    public static function getObjectVars($object)
    {
        return get_object_vars($object);
    }

    static function out($var, $shift = '')
    {
        if (is_array($var)) {
            foreach ($var as $key => $item) {
                if (is_array($item)) {
                    echo $shift . ' ('. gettype($item).')' . '<b>' . $key . '</b><br>';
                    self::out($item, $shift . '&nbsp;&nbsp;');
                } else {
                    if (is_object($item)) {
                        echo $shift . ' ('. gettype($item).')' . '<b>' . $key . '</b><br>';
                        self::out($item, $shift . '&nbsp;&nbsp;');
                    } else {
                        echo $shift . ' ('. gettype($item).')' . $key . ' = ' . $item . '<br>';
                    }
                }
            }
        } elseif (is_object($var)) {
            echo $shift . '<b>Object</b><br>';
            self::out(self::getObjectVars($var), $shift . '&nbsp;&nbsp;');
        } else {
            echo $var . '<br>';
        }
    }

    static function d($vars, $terminate = false)
    {
        self::out($vars);

        if ($terminate) {
            exit(9);
        }
    }
    
    static function _($attr)
    {
        return $attr;
    }

    private function __construct()
    {
        ;
    }

    private function __clone()
    {
        ;
    }

}
