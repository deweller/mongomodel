<?php

namespace MongoModel\Util;

use \Exception;


/*
* SerialUtil
*/
class SerialUtil
{

    // milliseconds from epoch and unique 6 digit string
    public static function newSerial() {
        $pieces = explode(' ', microtime(false));
        return $pieces[1].substr($pieces[0], 2, 3) . "-" . substr(md5(uniqid()), 0, 6);
    }
}