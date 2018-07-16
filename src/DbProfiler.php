<?php
/**
 * Tlumx (https://tlumx.com/)
 *
 * @author    Yaroslav Kharitonchuk <yarik.proger@gmail.com>
 * @link      https://github.com/tlumx/tlumx-db
 * @copyright Copyright (c) 2016-2018 Yaroslav Kharitonchuk
 * @license   https://github.com/tlumx/tlumx-db/blob/master/LICENSE  (MIT License)
 */
namespace Tlumx\Db;

/**
 * Db profiler class.
 */
class DbProfiler
{
    /**
     * @var array
     */
    private $profiles = [];

    /**
     * Clear profiler
     */
    public function clear()
    {
        $this->profiles = [];
    }

    /**
     * Start profiler
     *
     * @param string $sql
     * @param mixed $params
     * @return int
     */
    public function start($sql, $params = null)
    {
        $this->profiles[] = [
            'sql' => $sql,
            'params' => $params,
            'start' => microtime(true)
        ];

        end($this->profiles);

        return key($this->profiles);
    }

    /**
     * End profiler
     *
     * @param mixed $key
     * @throws \InvalidArgumentException
     */
    public function end($key)
    {
        if (!isset($this->profiles[$key])) {
            throw new \InvalidArgumentException("Profiler has no query with handle '$key'.");
        }

        $this->profiles[$key]['end'] = microtime(true);
    }

    /**
     * Get profile by key
     *
     * @param mixed $key
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getProfile($key)
    {
        if (!isset($this->profiles[$key])) {
            throw new \InvalidArgumentException("Profiler has no query with handle '$key'.");
        }

        $end = isset($this->profiles[$key]['end']) ? $this->profiles[$key]['end'] : null;
        $total = $end ? ($this->profiles[$key]['end'] - $this->profiles[$key]['start']) : null;

        return [
            'sql' => $this->profiles[$key]['sql'],
            'params' => $this->profiles[$key]['params'],
            'start' => $this->profiles[$key]['start'],
            'end' => $end,
            'total' => $total
        ];
    }

    /**
     * Get all profiles
     *
     * @return array
     */
    public function getProfiles()
    {
        $result = [];

        foreach ($this->profiles as $key => $profile) {
            if (!isset($profile['end'])) {
                continue;
            }

            $result[] = [
                'sql' => $profile['sql'],
                'params' => $profile['params'],
                'start' => $profile['start'],
                'end' => $profile['end'],
                'total' => ($profile['end'] - $profile['start'])
            ];
        }

        return $result;
    }
}
