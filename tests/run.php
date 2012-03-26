<?php

require 'vendor/rstuven/pecs/lib/pecs.php';
require 'vendor/.composer/autoload.php';
require 'lib/probe.php';
require 'lib/provider.php';

// include the tests
require __DIR__.'/probe.php';
require __DIR__.'/provider.php';

// run them
pecs\run();
