<?php

class Probe extends Events\GenericEmitter {

    function __construct($config) {

        parent::__construct();

        $this->id = uniqid();

        if (gettype($config) === 'string')
            $config = Array(
                'name' => $config,
                'types' => array_slice(func_get_args(), 1)
            );
        $this->name = $config['name'];

        if (array_key_exists('types', $config) && count($config['types']) > 0)
            $this->types = $config['types'];
        else
            $this->types = Array('number');

        $this->enabled = isset($config['enabled']) ? $config['enabled'] : false;
        $this->instant = isset($config['instant']) ? $config['instant'] : false;
        $this->sampleThreshold = isset($config['sampleThreshold']) ? $config['sampleThreshold'] : 1000;

        if (isset($config['args'])) {
            if (gettype($config['args'] === 'array'))
                $this->args = $config['args'];
            else
                $this->args = Array($config['args']);
            if (gettype($this->args) === 'array' && count($this->args) === 1 && gettype($this->args[0]) === 'function reference')
                $this->args = $this->args[0];
        }
        else {
            $this->args = array_map(function($type){
                return ($type === 'number') ? 0 : '';
            }, $this->types);
        }

        $this->consumerIds = Array();

        $this->hits = 0;
    }

    function update() {
        $this->args = func_get_args();
        $this->hits++;
        if ($this->enabled && $this->instant) {
            $evaluated = $this->evaluate($this->args);
            $this->sample(null, $evaluated);
        }
    }

    function increment($offset = 1, $index = 0) {
        $arg = $this->args[$index];
        if (gettype($arg) !== 'integer')
            throw new \Exception("Argument of wrong type. args in index {$index} can not be incremented.");
        $this->args[$index] = $arg + $offset;
        $this->hits++;
        if ($this->enabled && $this->instant)
            $this->sample(null, $this->args);
    }

    /**
     * Returns an args array.
     */
    function evaluate($args) {
        if (gettype($args) === 'array') {
            return Array($args);
        } else {
            return call_user_func($args);
        }
    }

    function sample($consumerId = null, $args_array = null, $timestamp = null) {
        $now = round(microtime(true) * 1000);
        if (!($this->sampleThreshold === 0 || !isset($this->lastTimestamp) || ($now - $this->lastTimestamp) >= $this->sampleThreshold))
            return null;
        $this->lastTimestamp = $now;

        if (!isset($args_array))
            $args_array = $this->evaluate($this->args);

        $samples = Array();
        foreach ($args_array as $args) {
            $payload = (object) Array(
                'timestamp' => isset($timestamp) ? $timestamp : $now,
                'hits' => $this->hits,
                'args' => $args
            );
            $consumerIds = isset($consumerId) ? Array($consumerId) : $this->consumerIds;
            $sample = Array(
                'payload' => $payload,
                'consumerIds' => $consumerIds
            );
            $this->notify('sample', $sample);
            $samples[] = (object) $sample;
        }
        return $samples;
    }

    function enableForConsumer($consumerId, $interval, $probeKey) {
        if ($this->instant) {
            if (!in_array($consumerId, $this->consumerIds)) {
                $this->consumerIds[] = $consumerId;
            }
        }
        else {
        }
        $this->enabled = true;
        $this->disableDelay = microtime(); //TODO: disable!
    }

    function stop($consumerId) {
        $index = array_search($consumerId, $this->consumerIds);
        if ($index !== -1)
            array_splice($this->consumerIds, $index, 1);
    }

}