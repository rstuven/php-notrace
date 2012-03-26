<?php

function publishSample($_this, $routingKey, $body) {
    $_this->samples->publish($routingKey, $body);
}

function connect($_this) {
    $host = isset($config['host']) ? $config['host'] : 'localhost';
    $_this->connection = new AMQPConnection(Array('host' => $host));
    $_this->connection->connect();
    $_this->samples = declareExchange($_this, 'notrace-samples', 'topic', true);
    $_this->requests = declareExchange($_this, 'notrace-requests', 'fanout', true);
    $_this->requestsQueue = declareQueue($_this, '');
    //$queue->setFlags(AMQP_EXCLUSIVE);

    // WARNING: comment on php manual: "If the bind() operation is blocking for you, make sure you're performing it on a fresh connection - use reconnect() or so. This took me hours to figure out."
    $_this->requestsQueue->bind('notrace-requests', '');
}

function declareExchange($_this, $name, $type, $autoDelete)
{
    $exchange = new AMQPExchange($this->connection, $name);
    $exchange->declare($name, $type, $autoDelete);
}

function declareQueue($_this, $name)
{
    $queue = new AMQPQueue($this->connection);
    $queue->declare($name);
    return $queue;
}

function processRequests($_this) {
    while (true) {
        $message = $_this->requestsQueue->get();
        if ($message === false) return;
        if ($message['count'] === -1) return;
        $message = bson_decode($message['msg']);
        //$message = json_decode($message['msg'], true);

/*
        $options = Array('min' => 1, 'max' => 1, 'ack' => true);
        $message = $_this->requestsQueue->consume($options);
        if ($message === false) return;
        $message = bson_decode($message[0]['message_body']);
        //$message = json_decode($message['msg'], true);
*/

        $_this->processRequestMessage($message);
    }
}

