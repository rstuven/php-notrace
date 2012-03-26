<?php

//include('provider_amqp101.inc');
include('provider_amqplib.inc');

class Provider extends Events\GenericEmitter {


    public function processRequests() {
        processRequests($this);
    }

    function __construct($config) {

        parent::__construct();


        if (!isset($config['name']))
            throw new \Exception("Argument missing: \$config['name']");

        $this->id = uniqid();
        $this->config = $config;

        $this->probes = (object) Array();
        if (isset($config['probes'])) {
            foreach ($config['probes'] as $name => $args) {
                if (gettype($args) !== 'array')
                    $args = Array('args' => $args);
                $args['name'] = $name;
                $this->addProbe($args);
            }
        }

        $_this = $this;
        $this->addProbe(Array(
            'name' => '_probes',
            'sampleThreshold' => 0,
            'instant' => true,
            'types' => Array('object'),
            'args' => function() use($_this) {
                $args_array = Array();
                foreach ($_this->probes as $name => $probe) {
                    $arg = Array(
                        'name' => $name,
                        'types' => $probe->types,
                        'instant' => $probe->instant,
                        'sampleThreshold' => $probe->sampleThreshold
                    );
                    $args_array[] = Array($arg);
                }
                return $args_array;
            }
        ));

    }

    public function notify($eventName, array $params = array())
    {
        return parent::notify($eventName, $params);
    }

    function addProbe($name) {
        $reflectProbe = new ReflectionClass('Probe');
        $probe = $reflectProbe->newInstanceArgs(func_get_args());
        if (gettype($name) !== 'string')
            $name = $name['name'];
        $this->probes->$name = $probe;
        $_this = $this;
        $probe->on('sample', function($event) use($_this, $probe) {
            $sample = $event->getParams();
            $sample['probe'] = $probe;
            $_this->notify('sample', $sample);
            if (!isset($_this->samples))
                return;
            $payload = $sample['payload'];
            $body = Array(
                'provider' => $_this->config['name'],
                'module' => $_this->module,
                'probe' => $probe->name,
                'timestamp' => $payload->timestamp,
                'hits' => $payload->hits,
                'args' => $payload->args
                //'args' => Array('name'=>'pro')
            );
            $body = bson_encode($body);
            $probeKey = "{$_this->config['name']}.{$_this->module}.{$probe->name}";
            try {
                foreach ($sample['consumerIds'] as $consumerId) {
                    publishSample($_this, $probeKey.'.'.$consumerId, $body);
                }
            } catch (Exception $e) {
                $_this->disconnect();
                return;
            }
        });
        return $probe;
    }

    private function call_probe_method($method, $name, $args) {
        if (gettype($name) === 'array')
            $name = $name['name'];
        if (property_exists($this->probes, $name))
            $probe = $this->probes->$name;
        else
            $probe = $this->addProbe($name);
        $reflectionMethod = new ReflectionMethod('Probe', $method);
        $reflectionMethod->invokeArgs($probe, array_slice($args, 1));
    }

    function update($name) {
        $this->call_probe_method('update', $name, func_get_args());
    }

    function increment($name) {
        $this->call_probe_method('increment', $name, func_get_args());
    }

    function start($module) {
        $this->module = $module;

        connect($this);

        $this->reconnectTimer = time();
    }

    function processRequestMessage($message) {
        if (!isset($message['request'])) return;
        if (!isset($message['probeKey'])) return;
        if (!isset($message['consumerId'])) return;
        $match = preg_match_all("/^([^\.]+)\.([^\.]+)\.([^\.]+)$/", $message['probeKey'], $matches, PREG_SET_ORDER);
        if ($match === 0) return;
        if (!in_array($matches[0][1], Array('*', $this->config['name']))) return;
        if (!in_array($matches[0][1], Array('*', $this->module))) return;
        $probeName = $matches[0][3];
        $probes = $probeName === '*' ? get_object_vars($this->probes) : Array($probeName);
        foreach ($probes as $probeName)
            if (property_exists($this->probes, $probeName))
                $this->doRequest($this->probes->$probeName, $message);
    }

    private function doRequest($probe, $message) {
        $probeKey = "{$this->config['name']}.{$this->module}.{$probe->name}";
        switch ($message['request']) {
        case 'sample':
            $probe->sample($message['consumerId']);
            break;
        case 'enable':
            $probe->enableForConsumer($message['consumerId'], $message['args'][0], $probeKey);
            break;
        case 'stop':
            $probe->stop($message['consumerId']);
            break;
        }
    }
}
