<?php

namespace MongoModel\Util;

use \Exception;


/*
* SerialUtil
*/
class SerialUtil
{

    public static function newSerial() {
        return (string)floor(microtime(true) * 1000000) . "-" . substr(md5(uniqid()), 0, 6);
    }
}