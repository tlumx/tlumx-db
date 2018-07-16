<?php
/**
 * Tlumx (https://tlumx.com/)
 *
 * @author    Yaroslav Kharitonchuk <yarik.proger@gmail.com>
 * @link      https://github.com/tlumx/tlumx-db
 * @copyright Copyright (c) 2016-2018 Yaroslav Kharitonchuk
 * @license   https://github.com/tlumx/tlumx-db/blob/master/LICENSE  (MIT License)
 */
namespace Tlumx\Tests\Db;

use Tlumx\Db\DbProfiler;

class DbProfilerTest extends \PHPUnit\Framework\TestCase
{
    public function testProfiler()
    {
        $profiler = new DbProfiler();
        $key = $profiler->start('some query');
        usleep(1000);
        $profiler->end($key);

        $profile = $profiler->getProfile($key);

        $this->assertEquals($profile['sql'], 'some query');
        $this->assertEquals($profile['params'], null);
        $this->assertEquals($profile['total'], $profile['end'] - $profile['start']);

        $key = $profiler->start('another query', ['a' => 100, 'b' => 'str']);
        usleep(1000);
        $profiler->end($key);

        $profile = $profiler->getProfile($key);

        $this->assertEquals($key, 1);
        $this->assertEquals(count($profiler->getProfiles()), 2);
        $this->assertEquals($profile['sql'], 'another query');
        $this->assertEquals($profile['params'], ['a' => 100, 'b' => 'str']);
        $this->assertEquals($profile['total'], $profile['end'] - $profile['start']);

        // no "end"
        $key2 = $profiler->start('another query 2', ['a' => 200, 'b' => 'str2']);
        usleep(1000);
        $this->assertEquals($key2, 2);
        $this->assertEquals(count($profiler->getProfiles()), 2); // not 3

        // ---
        $profiler->clear();
        $this->assertEquals(count($profiler->getProfiles()), 0);
    }


    public function testInvalidKey()
    {
        $profiler = new DbProfiler();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Profiler has no query with handle '100'.");
        $profiler->end(100);
    }

    public function testInvalidKey2()
    {
        $profiler = new DbProfiler();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Profiler has no query with handle '500'.");
        $profiler->getProfile(500);
    }
}
