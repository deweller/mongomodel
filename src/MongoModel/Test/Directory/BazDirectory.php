<?php

namespace MongoModel\Test\Directory;


use MongoModel\Directory\BaseDirectory;
use \Exception;

/*
* BazDirectory
*/
class BazDirectory extends BaseDirectory
{

    protected $model_class = 'MongoModel\\Test\\Model\\Special\\SpecialBazModel';

}
