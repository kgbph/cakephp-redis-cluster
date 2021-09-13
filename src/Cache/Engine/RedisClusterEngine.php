<?php
declare(strict_types=1);

namespace Kgbph\RedisCluster\Cache\Engine;

use Cake\Cache\Engine\RedisEngine;
use Cake\Log\Log;

/**
 * Redis cluster cache storage engine
 */
class RedisClusterEngine extends RedisEngine
{
    /**
     * Redis cluster wrapper.
     *
     * @var \RedisCluster
     */
    protected $_Redis;

    /**
     * The default config used unless overridden by runtime configuration
     *
     * - `duration` Specify how long items in this cache configuration last.
     * - `failover` Automatic slave failover mode.
     * - `groups` List of groups or 'tags' associated to every key stored in this config.
     *    handy for deleting a complete group from cache.
     * - `name` Redis cluster name
     * - `nodes` URL or IP to the Redis cluster nodes.
     * - `password` Redis cluster password.
     * - `persistent` Connect to the Redis cluster with a persistent connection
     * - `prefix` Prefix prepended to all entries. Good for when you need to share a keyspace
     *    with either another cache config or another application.
     * - `read_timeout` Read timeout in seconds (float).
     * - `timeout` Timeout in seconds (float).
     *
     * @var array
     */
    protected $_defaultConfig = [
        'duration' => 3600,
        'failover' => null,
        'groups' => [],
        'name' => null,
        'nodes' => [],
        'password' => null,
        'persistent' => true,
        'prefix' => 'cake_',
        'read_timeout' => 0,
        'timeout' => 0,
    ];

    /**
     * @inheritDoc
     */
    protected function _connect(): bool
    {
        $connected = false;

        try {
            $this->_Redis = new \RedisCluster(
                $this->_config['name'],
                $this->_config['nodes'],
                (float)$this->_config['timeout'],
                (float)$this->_config['read_timeout'],
                $this->_config['persistent'],
                $this->_config['password'],
            );

            $slaveFailover = \RedisCluster::FAILOVER_NONE;
            switch ($this->_config['failover']) {
                case 'distribute':
                    $slaveFailover = \RedisCluster::FAILOVER_DISTRIBUTE;
                    break;
                case 'error':
                    $slaveFailover = \RedisCluster::FAILOVER_ERROR;
                    break;
                case 'slaves':
                    $slaveFailover = \RedisCluster::FAILOVER_DISTRIBUTE_SLAVES;
                    break;
            }
            /** @phpstan-ignore-next-line */
            $this->_Redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, $slaveFailover);

            $connected = true;
        } catch (\RedisClusterException $e) {
            if (class_exists(Log::class)) {
                Log::error('RedisClusterEngine could not connect. Got error: ' . $e->getMessage());
            }
        }

        return $connected;
    }

    /**
     * {@inheritdoc}
     */
    public function clear($check)
    {
        if ($check) {
            return true;
        }

        /** @phpstan-ignore-next-line */
        $this->_Redis->setOption(\RedisCluster::OPT_SCAN, \RedisCluster::SCAN_RETRY);

        $isAllDeleted = true;
        $pattern = $this->_config['prefix'] . '*';

        foreach ($this->_Redis->_masters() as $masterNode) {
            $iterator = null;

            while (true) {
                /** @phpstan-ignore-next-line */
                $keys = $this->_Redis->scan($iterator, $masterNode, $pattern);

                /** @phpstan-ignore-next-line */
                if ($keys === false) {
                    break;
                }

                foreach ($keys as $key) {
                    $isDeleted = ($this->_Redis->del($key) > 0);
                    $isAllDeleted = $isAllDeleted && $isDeleted;
                }
            }
        }

        return $isAllDeleted;
    }

    /**
     * @inheritDoc
     */
    public function __destruct()
    {
        if (empty($this->_config['persistent']) && $this->_Redis instanceof \RedisCluster) {
            $this->_Redis->close();
        }
    }
}
