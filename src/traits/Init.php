<?php

namespace Sofi\Base\traits;

trait Init
{

    function init($params = [])
    {
        foreach ($params as $key => $value) {
            if (isset($this->{$key})) {
                $this->{$key} = $value;
            }
        }
    }

}
