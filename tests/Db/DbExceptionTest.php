<?php
/**
 * Tlumx (https://tlumx.github.io/)
 *
 * @author    Yaroslav Kharitonchuk <yarik.proger@gmail.com>
 * @link      https://github.com/tlumx/tlumx-db
 * @copyright Copyright (c) 2016-2018 Yaroslav Kharitonchuk
 * @license   https://github.com/tlumx/tlumx-db/blob/master/LICENSE  (MIT License)
 */
namespace Tlumx\Tests\Db;

use Tlumx\Db\Exception\DbException;
use Tlumx\Tests\Db\Foo;

class DbExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testImplements()
    {
        $this->assertInstanceOf(\Exception::class, new DbException);
    }
}
