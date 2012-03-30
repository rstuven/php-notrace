<?php
require 'vendor/.composer/autoload.php';
require 'lib/probe.php';
require 'lib/provider.php';

$provider = new Provider(Array(
    'name' => 'php_provider',
    'probes' => Array(
        'random' => Array(
            'types' => Array('number', 'number'),
            'instant' => false,
            'sampleThreshold' => 0
        )
    )
));

$provider->start('php_module');

$dist = Array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
$length = count($dist);
$dist = array_map(function($i) use($length) { $x = $i / $length; return exp(-(pi()*$x*$x)); }, $dist);

declare(ticks=100);
while (true) {

    $arg0 = rand(0, 1000000) / 1000;
    $index = rand(0, $length - 1);
    if ($dist[$index] > rand(0, 1000000) / 1000000)
        $provider->probes->random->update($index, $arg0);

//echo ".";
    $provider->processRequests();
    usleep(1000);

}
