<?php
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

//define('AMQP_DEBUG', true);

function publishSample($_this, $routingKey, $body) {
    //echo "\npublish {$routingKey}";
    $msg = new AMQPMessage($body);
    $_this->channel->basic_publish($msg, $_this->samples, $routingKey);
}

function connect($_this) {
    $host = isset($config['host']) ? $config['host'] : 'localhost';
    $port = 5672;
    $user = 'guest';
    $password = 'guest';
    $_this->connection = new AMQPConnection($host, $port, $user, $password);
    $_this->channel = $_this->connection->channel();
    $_this->samples = declareExchange($_this, 'notrace-samples', 'topic', false, false, false);
    $_this->requests = declareExchange($_this, 'notrace-requests', 'fanout', false, false, false);
    $_this->requestsQueue = declareQueue($_this, '', false, false, true, true);

    $_this->channel->queue_bind($_this->requestsQueue, $_this->requests, '');
    $consumer_tag = $_this->id;
    $_this = $_this;
    $_this->channel->basic_consume($_this->requestsQueue, $consumer_tag, false, false, true, false, function($message) use($_this) {
        $message = bson_decode($message->body);
        $_this->processRequestMessage($message);
    });
}

function declareExchange($_this, $name, $type, $passive, $durable, $autoDelete)
{
    $_this->channel->exchange_declare($name, $type, $passive, $durable, $autoDelete);
    return $name;
}

function declareQueue($_this, $name, $passive, $durable, $exclusive, $autoDelete)
{
    $_this->channel->queue_declare($name, $passive, $durable, $exclusive, $autoDelete);
    return $name;
}

function processRequests($_this) {
    // Loop as long as the channel has callbacks registered
    for($i = 0; $i < count($_this->channel->callbacks); $i++) {
//echo "\ncallback:" . $i;
        $read   = array($_this->connection->getSocket()); // add here other sockets that you need to attend
        $write  = NULL;
        $except = NULL;
        if (false === ($num_changed_streams = stream_select($read, $write, $except, 0, 1000))) {
//echo "\nerror handling???";
            /* Error handling */
        } elseif ($num_changed_streams > 0) {
//echo "\nchannel wait";
//echo "#";
            $_this->channel->wait();
        }
//echo "({$num_changed_streams})";
    }
}
