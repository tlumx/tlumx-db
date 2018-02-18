<?php
/**
 * Tlumx (https://tlumx.github.io/)
 *
 * @author    Yaroslav Kharitonchuk <yarik.proger@gmail.com>
 * @link      https://github.com/tlumx/tlumx-db
 * @copyright Copyright (c) 2016-2018 Yaroslav Kharitonchuk
 * @license   https://github.com/tlumx/tlumx-db/blob/master/LICENSE  (MIT License)
 */

$autoloader = require dirname(__DIR__) . '/vendor/autoload.php';
$autoloader->addPsr4('Tlumx\Tests\\', __DIR__);

if (! class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias(\PHPUnit\Framework\TestCase::class, '\PHPUnit_Framework_TestCase');
}

require dirname(__FILE__) . '/functions.php';
