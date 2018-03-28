<?php

namespace Sofi\Base;

class Dev extends \stdClass
{
    public $time;
    public $mem;
    
    function getTime($time = false)
    {
        return $time === false ? microtime(true) : microtime(true) - $time;
    }

    function getMemory($memory = false)
    {
        return $memory === false ? memory_get_usage() : memory_get_usage() - $memory;
    }

    function out($var, $shift = '', $index = 0, $level = 10)
    {
        if ($index > $level) //$level
            return;

        if ($index == 0)
            echo '<div class="sofi-debug">';

        $index++;

        $new_shift = '&nbsp;&nbsp;&nbsp;';
        if (is_array($var)) {
            if ($index == 1) {
                echo '<h3>' . $shift . '<b>Array</b></h3>';
                $shift .= $new_shift;
//                $index++;
            }
            foreach ($var as $key => $item) {
                if (is_array($item)) {
                    echo '<h4>' . $shift . '<b>' . $key . '</b> <i>(' . gettype($item) . ')</i></h4>';
                    self::out($item, $shift . $new_shift, $index, $level);
                } else {
                    if (is_object($item)) {
                        echo '<h4>' . $shift . '<b><a href="#' . spl_object_hash($item) . '">' . $key . '</a></b> <i>(' . get_class($item) . ')</i></h4>';
                        self::out($item, $shift . $new_shift, $index, $level);
                    } else {
                        echo '<p>' . $shift . '<b>' . $key . '</b> = ' . $item . ' <i>(' . gettype($item) . ')</i></p>';
                    }
                }
            }
        } elseif (is_object($var)) {
            if ($index == 1) {
                echo '<a name="' . spl_object_hash($var) . '"></a>';
                echo '<h3>' . $shift . '<b>Object</b><i>(' . get_class($var) . ')</i></h3>';
            }
            self::out((array) ($var), $shift . $new_shift, $index, $level);
        } else {
            echo $var . '<br>';
        }
        if ($index == 1)
            echo '</div>';
    }
    
    function __construct()
    {
        $this->time = $this->getTime();
        $this->mem = $this->getMemory();
    }
            
    function done()
    {
        $this->time = $this->getTime($this->time);
        $this->mem = $this->getMemory($this->mem);
    }

}
