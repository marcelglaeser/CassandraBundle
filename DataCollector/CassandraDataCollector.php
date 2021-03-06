<?php

namespace CassandraBundle\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use CassandraBundle\EventDispatcher\CassandraEvent;
use Cassandra;

/**
 * Collect information about cassandra command.
 */
class CassandraDataCollector extends DataCollector
{
    /**
     * Human readable values for consistency.
     *
     * @var array
     */
    protected static $consistency = [
        Cassandra::CONSISTENCY_ANY => 'any',
        Cassandra::CONSISTENCY_ONE => 'one',
        Cassandra::CONSISTENCY_TWO => 'two',
        Cassandra::CONSISTENCY_THREE => 'three',
        Cassandra::CONSISTENCY_QUORUM => 'quorum',
        Cassandra::CONSISTENCY_ALL => 'all',
        Cassandra::CONSISTENCY_LOCAL_QUORUM => 'local quorum',
        Cassandra::CONSISTENCY_EACH_QUORUM => 'each quorum',
        Cassandra::CONSISTENCY_SERIAL => 'serial',
        Cassandra::CONSISTENCY_LOCAL_SERIAL => 'local serial',
        Cassandra::CONSISTENCY_LOCAL_ONE => 'local one',
    ];

    /**
     * Construct the data collector.
     */
    public function __construct()
    {
        $this->data['cassandra'] = new \SplQueue();
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'cassandra';
    }

    /**
     * Collect data for casandra command.
     *
     * Listen for cassandra event
     *
     * @param CassandraEvent $event
     */
    public function onCassandraCommand(CassandraEvent $event)
    {
        $data = [
            'keyspace' => $event->getKeyspace(),
            'command' => $event->getCommand(),
            'argument' => $this->getArguments($event),
            'executionOptions' => $this->getExecutionOptions($event),
            'executionTime' => $event->getExecutionTime(),
        ];

        $this->data['cassandra']->enqueue($data);
    }

    /**
     * Return cassandra command list.
     *
     * @return array
     */
    public function getCommands()
    {
        return $this->data['cassandra'];
    }

    /**
     * Return the total time spent by cassandra commands.
     *
     * @return float
     */
    public function getTotalExecutionTime()
    {
        return array_reduce(iterator_to_array($this->getCommands()), function ($time, $value) {
            $time += $value['executionTime'];

            return $time;
        });
    }

    /**
     * Return average time spent by cassandra command.
     *
     * @return float
     */
    public function getAvgExecutionTime()
    {
        $totalExecutionTime = $this->getTotalExecutionTime();

        return ($totalExecutionTime) ? ($totalExecutionTime / \count($this->getCommands())) : 0;
    }

    /**
     * Get argument to display in datacollector panel.
     *
     * @param CassandraEvent $event
     *
     * @return string
     */
    protected function getArguments(CassandraEvent $event)
    {
        $arguments = $event->getArguments();

        if (\is_object($arguments[0])) {
            return 'Statement';
        }

        return $arguments[0];
    }

    /**
     * Return the cassandra options defined at runtime.
     *
     * @param CassandraEvent $event
     *
     * @return array
     */
    protected function getExecutionOptions(CassandraEvent $event)
    {
        $arguments = $event->getArguments();

        if (empty($arguments[1])) {
            return [
                'consistency' => '',
                'serialConsistency' => '',
                'pageSize' => '',
                'timeout' => '',
                'arguments' => '',
            ];
        }

        $options = $arguments[1];

        return [
            'consistency' => self::getConsistency(isset($options['consistency']) ? $options['consistency'] : null),
            'serialConsistency' => self::getConsistency(isset($options['serialConsistency']) ? $options['serialConsistency'] : null),
            'pageSize' => isset($options['pageSize']) ? $options['pageSize'] : null,
            'timeout' => isset($options['timeout']) ? $options['timeout'] : null,
            'arguments' => var_export($options['arguments'], true),
        ];
    }

    /**
     * Get human readable value of consistency.
     *
     * @param int $intval
     *
     * @return string|null
     */
    protected static function getConsistency($intval)
    {
        if (\array_key_exists($intval, self::$consistency)) {
            return self::$consistency[$intval];
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
    }
}
