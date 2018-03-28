<?php

namespace Sofi\Base;

/**
 * Description of Session
 *
 * @author hawk
 */
class Session
{

    protected $namespace;
    protected $storage = [];

    public function create($namespace)
    {
        return new Session($namespace);
    }

    public function __construct($namespace = 'sofi')
    {
        $this->namespace = $namespace;

        if (empty($_SESSION[$this->namespace])) {
            $_SESSION[$this->namespace] = [];
        }

        $this->storage = $_SESSION[$this->namespace];
    }
    
    public function reset()
    {
        $this->storage = [];
        unset($_SESSION[$this->namespace]);
    }

    public function __set($name, $value)
    {
        $this->storage[$name] = $value;
    }

    public function __get($name)
    {
        return $this->storage[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->storage[$name]);
    }

    public function __unset($name)
    {
        unset($this->storage[$name]);
    }

    public function flush()
    {
        $_SESSION[$this->namespace] = $this->storage;
    }

    public function __destruct()
    {
        $_SESSION[$this->namespace] = $this->storage;
    }
    
    public function storage()
    {
        return $this->storage;
    }
    
    public function __invoke()
    {
        return $this->storage();
    }

}
