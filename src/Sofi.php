<?php

namespace Sofi\Base;

/**
 * Сокращение для DIRECTORY_SEPARATOR
 */
define('DS', DIRECTORY_SEPARATOR);

class Sofi
{
    
    const SOFI_MODE_DEV  = 4;
    const SOFI_MODE_TEST = 2;
    const SOFI_MODE_PROD = 0;

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
        'paths' => [
            'app\\' => 'app',
            'modules\\' => 'modules',
        ],
        'autoloader' => false,
        'session' => true,
        'application-class' => 'Sofi\Application'
    ];
    static public $Loader = null;
    static private $baseAppConfig = [
        'components' => [
            'Router' => 'Sofi\Router\Router',
            'Layout' => [
                'class' => 'Sofi\mvc\Layout',
                'name' => 'main/main'
            ]
        ]
    ];
    static protected $app = null;
    
    static protected $dev = null;

    /**
     * Возвращает true если запущенно из консоли
     * 
     * @return boolean
     */
    public static function isConsole(): bool
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

    public static function getConfigFile($name, $path)
    {
        if (file_exists($path . $name . '.conf.php')) {
            return require_once $path . $name . '.conf.php';
        }
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
    public static function init($config = [], $loader = null)
    {
        /**
         * Определение пути запуска скрипта
         */
        if (!defined('PUBLIC_PATH')) {
            $debug = debug_backtrace();
            define('PUBLIC_PATH', realpath(dirname($debug[0]['file'])) . DS);
        }

        if (!defined('BASE_PATH')) {
            define('BASE_PATH', realpath(dirname(PUBLIC_PATH)) . DS);
        }

        if (getenv('SOFI_DEVELOPER_MACHINE') === 'TRUE') {
            define('SOFI_MODE', 4);
            self::$dev = new Dev();
        } else {
            define('SOFI_MODE', 0);
        }
        
        register_shutdown_function([Sofi::class,'done']);

        self::addConfig(self::getConfigFile('sofi', BASE_PATH));
        self::addConfig($config);

        if (self::$general['project'] == '') {
            echo 'Empty Project name';
            die();
        }

        if (is_object($loader)) {
            self::$Loader = $loader;
            foreach (self::$general['paths'] as $name => $path) {
                self::$Loader->addPsr4($name, BASE_PATH . $path);
            }
        }

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

    public static function done()
    {
        if (SOFI_MODE > 0) {
            self::$dev->done();
        }
    }

    /**
     * 
     * @param array $config
     * @return \Sofi\Application
     */
    static function app($config = null): \Sofi\Application
    {
        if (self::$app == null) {
//            Sofi::d(debug_backtrace());
            if (is_array($config)) {
//                var_dump($config);
                $config = array_merge(static::$baseAppConfig, $config);
            } else {
                $config = static::$baseAppConfig;
            }
            self::$app = self::createObject(self::$general['application-class'], [null], []);
            self::$app->init($config);
//            Sofi::d(self::$app);
        }

//        Sofi::d(self::$app);
        return self::$app;
    }

    /**
     * 
     * @param mixed $type - Параметры создания объекта (строка, массив, замыкание)
     * @param mixed $constructParams - Параметр используемый при создании объекта
     * @param array $initParams - Параметры инициализации
     * @return \Sofi\Base\type - Возвращает созданный объект
     * @throws exceptions\InvalidConfig
     */
    public static function createObject($type, $constructParams = null, $initParams = [])
    {
        if (is_string($type)) {
            if (is_array($constructParams)) {
                $r = new \ReflectionClass($type);
                if ($r->getConstructor()) {
                    $obj = $r->newInstanceArgs($constructParams);
                } else {
                    $obj = new $type;
                }
//                $obj = new $type(...$constructParams);
            } else {
                $obj = new $type();
            }
            if ($initParams != [] && method_exists($obj, 'init')) {
                $obj->init($initParams);
            }

            return $obj;
        } elseif (is_array($type) && isset($type['class'])) {
            $class = $type['class'];
            unset($type['class']);

            if (isset($type['creator'])) {
                $creator_params = (is_array($type['creator']['params'])) ? $type['creator']['params'] : $constructParams;
                if (isset($type['creator']['method'])) {
                    $method = $type['creator']['method'];
                    $obj = $class::$method($creator_params);
                    if (method_exists($obj, 'init')) {
                        unset($type['creator']);
                        if ($type != [])
                            $obj->init($type);
                    }
                } else {
                    unset($type['creator']);
                    return static::createObject($class, $creator_params, $type);
                }
                return $obj;
            } else {
                return static::createObject($class, $constructParams, $type);
            }
        } elseif (is_callable($type, true)) {
            return call_user_func_array($type, $initParams);
        } elseif (is_array($type)) {
            throw new exceptions\InvalidConfig('Object configuration must be an array containing a "class" element.');
        } else {
            throw new exceptions\InvalidConfig('Unsupported configuration type: ' . gettype($type));
        }
    }

    static function exec($action, array $params = [], array $injections = [])
    {
        // замыкание
        if ($action instanceof \Closure) {
//            return call_user_func_array($action, $params);
            return $action(...array_values($params), ...array_values($injections));
        } elseif (is_callable($action)) {
            return $action(...array_values($params), ...array_values($injections));

            // Строка
        } elseif (is_string($action)) {
            if (mb_strpos($action, '{')) {
                foreach ($params as $key => $val) {
                    $action = str_replace('{' . $key . '}', $val, $action);
                }
            }

            $callback = explode('@', $action);
//            Sofi::d($injections);
            $obj = static::createObject($callback[0], [], $injections);
            
            if ($obj instanceof \Sofi\mvc\BaseController) {
                return $obj->run($callback[1], $params);
            }elseif ($obj instanceof \Sofi\mvc\Action) {
                return $obj->run(...$params);
            }elseif (method_exists($obj, $callback[1])) {
                return call_user_func_array([$obj, $callback[1]], $params);
            } else {
                throw new exceptions\InvalidRouteCallback('Bad method callback ' . $action);
            }

            //  Массив
        } elseif (is_array($action)) {
            $obj = static::createObject($action, [], $injections);

            return $obj->__invoke($params);
        }
    }

    public static function getObjectVars($object): array
    {
        return get_object_vars($object);
    }

    protected static function out($var, $shift = '', $index = 0, $level = 10)
    {
        self::$dev->out($var, $shift, $index, $level);
    }

    /**
     * 
     * @param mixed $expression
     * @param type $level
     * @param type $terminate
     */
    static function d($expression, $terminate = false, $level = 7)
    {
        if (SOFI_MODE == 0) {
            return;
        }
        
        self::out($expression, '', 0, $level);

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

function _($msgid)
{
    \_($msgid);
}

function d($expression, $level = 7, $terminate = false)
{
    Sofi::d($expression, $level, $terminate);
}
