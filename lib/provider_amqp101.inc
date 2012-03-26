<?php

function publishSample($_this, $routingKey, $body) {
    $_this->samples->publish($routingKey, $body);
}

function connect($_this) {
    $host = isset($config['host']) ? $config['host'] : 'localhost';
    $_this->connection = new AMQPConnection(Array('host' => $host));
    $_this->connection->connect();
    $_this->channel = new AMQPChannel($_this->connection);
    $_this->samples = declareExchange($_this, 'notrace-samples', 'topic', true);
    $_this->requests = declareExchange($_this, 'notrace-requests', 'fanout', true);
    $_this->requestsQueue = declareQueue($_this, '');
    //$queue->setFlags(AMQP_EXCLUSIVE);

    // WARNING: comment on php manual: "If the bind() operation is blocking for you, make sure you're performing it on a fresh connection - use reconnect() or so. This took me hours to figure out."
    $_this->requestsQueue->bind('notrace-requests', '');
}

function declareExchange($_this, $name, $type, $autoDelete)
{
    $exchange = new AMQPExchange($_this->channel);
    $exchange->setName($name);
    $exchange->setType($type);
    $flags = 0;
    if ($autoDelete) $flags |= AMQP_AUTODELETE;
    $exchange->setFlags($flags);
    $exchange->declare();
    return $exchange;
}

function declareQueue($_this, $name)
{
    $queue = new AMQPQueue($_this->channel);
    if (isset($name) && $name != '')
        $queue->setName($name);
    $queue->declare();
    return $queue;
}

function processRequests($_this) {
    while (true) {
        $message = $_this->requestsQueue->get(AMQP_AUTOACK);
        //$message = $_this->requestsQueue->get();
        if ($message === false) return;
        $message = bson_decode($message->getBody());
        //$message = json_decode($message->getBody(), true);

        $_this->processRequestMessage($message);
    }
}
