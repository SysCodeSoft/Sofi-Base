<?php

namespace Sofi\Base\exceptions;

class RouteNotFound extends Exception
{

    /**
     * @return string the name of this exception
     */
    public function getName()
    {
        return 'Route not found Exception';
    }

}
