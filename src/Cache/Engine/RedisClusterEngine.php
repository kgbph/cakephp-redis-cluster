<?php
declare(strict_types=1);

namespace Kgbph\RedisClusterEngine\Cache\Engine;

use Cake\Cache\Engine\RedisEngine;
use Cake\Log\Log;

/**
 * Redis cluster storage engine for cache.
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
     * - `password` Redis server password.
     * - `persistent` Connect to the Redis server with a persistent connection
     * - `prefix` Prefix appended to all entries. Good for when you need to share a keyspace
     *    with either another cache config or another application.
     * - `read_timeout` Read timeout in seconds (float).
     * - `servers` URL or IP to the Redis server hosts.
     * - `timeout` Timeout in seconds (float).
     *
     * @var array
     */
    protected $_defaultConfig = [
        'duration' => 3600,
        'failover' => null,
        'groups' => [],
        'host' => null,
        'name' => null,
        'password' => null,
        'persistent' => true,
        'prefix' => 'cake_',
        'read_timeout' => 0,
        'servers' => ['127.0.0.1:6379'],
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
                $this->_config['servers'],
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
     * @inheritDoc
     */
    public function __destruct()
    {
        if (empty($this->_config['persistent']) && $this->_Redis instanceof \RedisCluster) {
            $this->_Redis->close();
        }
    }
}
