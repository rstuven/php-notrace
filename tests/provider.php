<?php
describe('Provider', function() {

    describe('#constructor', function() {

        it('should throw error on name missing', function() {
            expect(function() {
                $p = new Provider();
            })->to_throw();
            expect(function() {
                $p = new Provider(Array(
                    'probes' => Array(
                        'p1' => function() {
                            return 'a';
                        }
                )
                ));
            })->to_throw();
        });

        it('should initialize probes', function() {
            $p = new Provider(Array(
                'name' => 'p'
            ));
            expect($p->probes)->not_to_be_null();
            expect($p->probes->_probes)->not_to_be_null();
        });

        it('should initialize probes config', function() {
            $p = new Provider(Array(
                'name' => 'p',
                'probes' => Array(
                    'p1' => Array(
                        'types' => Array('string')
                    ),
                    'p2' => Array(
                        'args' => function() {
                            return 123;
                        }
                ),
                'p3' => function() {
                    return 123;
                })
            ));
            expect($p->probes->p1->args)->to_be(Array(''));
            expect(call_user_func($p->probes->p2->args))->to_be(123);
            expect(call_user_func($p->probes->p3->args))->to_be(123);
        });

/*
        it('should support functions', function() {
            $p = new Provider(Array(
                'name' => 'node',
                    'probes' => Array(
                        'uptime' => function() {
                            return $process->uptime();
                        },
                            'memory_heap_used' => function() {
                                return $process->memoryUsage().heapUsed;
                            },
                                'memory_heap_total' => function() {
                                    return $process->memoryUsage().heapTotal;
                                    })
                    )
            );
            setTimeout(function() {
                $p->probes->test_async->sample('c1', function($err, $s) {
                    $p->stop();
                    done();
                });
            }, 5);
            $p->start();
        });
 */
    });

    describe('#addProbe', function() {

        it('should add a probe', function() {
            $p = new Provider(Array(
                'name' => 'p'
            ));
            $p->addProbe('p1', 'number', 'string');
            expect($p->probes->p1->name)->to_be('p1');
            expect($p->probes->p1->types)->to_be(Array('number', 'string'));
        });

    });

    describe('#update', function() {

        before_each(function($scope) {
            $scope = (object) $scope;
            $scope->p = new Provider(Array(
                'name' => 'p'
            ));
            return $scope;
        });

        after_each(function($scope) {
            expect(get_object_vars($scope->p->probes))->to_have_count(2);
            $count = 0;
            $scope->p->on('sample', function() use(&$count) {
                $count++;
            });
            $scope->p->probes->p1->sample('c1');
            expect($count)->to_be(1);
        });

        it('should change args', function($scope) {
            $po = $scope->p->addProbe('p1', 'string');
            $scope->p->probes->p1->update('val');
            expect($scope->p->probes->p1->args)->to_be(Array('val'));
            expect($scope->p->probes->p1->id)->to_be($po->id);
        });

        it('should use an existent probe by name', function($scope) {
            $po = $scope->p->addProbe('p1', 'number');
            $scope->p->update('p1', 123);
            expect($scope->p->probes->p1->args)->to_be(Array(123));
            expect($scope->p->probes->p1->id)->to_be($po->id);
        });

        it('should add a new probe by name if not exist', function($scope) {
            $scope->p->update('p1', 123);
            expect($scope->p->probes->p1->args)->to_be(Array(123));
        });

        it('should use an existent probe by config', function($scope) {
            $po = $scope->p->addProbe('p1', 'number');
            $scope->p->update(Array(
                'name' => 'p1'
            ), 123);
            expect($scope->p->probes->p1->args)->to_be(Array(123));
            expect($scope->p->probes->p1->id)->to_be($po->id);
        });

        it('should add a new probe by config if not exist', function($scope) {
            $scope->p->update(Array(
                'name' => 'p1'
            ), 123);
            expect($scope->p->probes->p1->args)->to_be(Array(123));
        });

    });

    describe('#increment', function() {

        before_each(function($scope) {
            $scope = (object) $scope;
            $scope->p = new Provider(Array(
                'name' => 'p'
            ));
            return $scope;
        });

        after_each(function($scope) {
            expect(get_object_vars($scope->p->probes))->to_have_count(2);
            $count = 0;
            $scope->p->on('sample', function() use(&$count) {
                $count++;
            });
            $scope->p->probes->p1->sample('c1');
            expect($count)->to_be(1);
        });

        it('should use an existent probe by name', function($scope) {
            $po = $scope->p->addProbe('p1', 'number');
            $scope->p->probes->p1->update(5);
            $scope->p->increment('p1');
            expect($scope->p->probes->p1->args)->to_be(Array(6));
            expect($scope->p->probes->p1->id)->to_be($po->id);
        });

        it('should add a new probe by name if not exist', function($scope) {
            $scope->p->increment('p1');
            expect($scope->p->probes->p1->args)->to_be(Array(1));
        });

        it('should use an existent probe by config', function($scope) {
            $po = $scope->p->addProbe('p1', 'number');
            $scope->p->probes->p1->update(5);
            $scope->p->increment(Array(
                'name' => 'p1'
            ));
            expect($scope->p->probes->p1->args)->to_be(Array(6));
            expect($scope->p->probes->p1->id)->to_be($po->id);
        });

        it('should add a new probe by config if not exist', function($scope) {
            $scope->p->increment(Array(
                'name' => 'p1'
            ));
            expect($scope->p->probes->p1->args)->to_be(Array(1));
        });

    });

    describe('#on sample', function() {

        it('should receive sample event from probes', function() {
            $p = new Provider(Array(
                'name' => 'p'
            ));
            $p->addProbe(Array(
                'name' => 'p1',
                'types' => Array('string'),
                'enabled' => true,
                'instant' => true
            ));
            $p->addProbe(Array(
                'name' => 'p2',
                'types' => Array('string'),
                'enabled' => true,
                'instant' => true
            ));
            $samples = Array();
            $p->on('sample', function($event) use(&$samples) {
                $samples[] = Array($event->getParam('probe'), $event->getParam('payload'));
            });
            $p->probes->p2->update('a');
            $p->probes->p1->update('b');
            expect($samples)->to_have_count(2);
            expect($samples[0][0])->to_be($p->probes->p2);
            expect($samples[0][1]->args)->to_be(Array('a'));
            expect($samples[1][0])->to_be($p->probes->p1);
            expect($samples[1][1]->args)->to_be(Array('b'));
        });
/*
        it('should publish sample', function() {
            $amqpscope = amqpmock();
            $p = new Provider(Array(
                'name' => 'p'
            ));
            $p->addProbe(Array(
                'name' => 'p1',
                    'types' => Array('string'),
                    'enabled' => true,
                    'instant' => true
            ));
            $p->start('m');
            $p->probes->p1->enableForConsumer('c1', 0, 'p.m.p1');
            setTimeout(function() {
                expect($p->requests)->not_to_be_null();
                expect($p->samples)->not_to_be_null();
                $mock = $sinon->mock($p->samples);
                $mock->expects('publish').withArgs('p.m.p1.c1', $sinon->predicate(function($actual) {
                    $actual = $BSON->deserialize(actual);
                    expect($actual->provider)->to_be('p');
                    expect($actual->module)->to_be('m');
                    expect($actual->probe)->to_be('p1');
                    expect($actual->args)->to_be(Array('b'));
                    return true;
                }));
                $p->probes->p1->update('b');
                setTimeout(function() {
                    $mock->verify();
                    $amqpscope->done();
                    done();
                }, 1);
            }, 1);
        });
 */
    });

/*
    describe('#start', function() {
        it('should connect', function() {
            $p = new Provider(Array(
                'name' => 'p'
            ));
            $mock = \Mockery::mock($p);
            $mock->shouldReceive('connect')->once();
            $mock->start('m');
            //expect($p->module)->to_be('m');
            //expect($p->reconnectTimer->isStarted())->to_be(true);
            \Mockery::close();
        });
    });

    describe('#stop', function() {
        it('should disconnect', function() {
            $p = new Provider(Array(
                'name' => 'p'
            ));
            $p->reconnectTimer->start(10000, function() {});
            $mock = $sinon->mock($p);
            $mock->expects('disconnect').once();
            $p->stop();
            expect($p->reconnectTimer->isStarted())->to_be(false);
            $mock->verify();
        });
    });
 */
    describe('#connect', function() {
/*
        it('should initialize connection', function() {
            $amqpscope = amqpmock();
            $p = new Provider(Array(
                'name' => 'p'
            ));
            expect($p->connection)->to_be_null();
            expect($p->samples)->to_be_null();
            expect($p->requests)->to_be_null();
            $p->connect();
            setTimeout(function() {
                expect($p->connection)->not_to_be_null();
                expect($p->samples)->not_to_be_null();
                expect($p->requests)->not_to_be_null();
                $amqpscope->done();
                done();
            }, 1);
        });
        describe('requests', function() {
            beforeEach(function() {
                $scope->p = new Provider(Array(
                    'name' => 'p'
                ));
                $scope->p->addProbe('p1');
                $scope->p->module = 'm';
                $scope->mock = $sinon->mock($scope->p->probes->p1);
                $scope->amqpscope = amqpmock().exchange('notrace-requests');
            });

            it('should sample', function() {
                $scope->amqpscope->publish('', $BSON->serialize(Array(
                    'request' => 'sample',
                        'probeKey' => 'p.m.p1',
                        'consumerId' => 'c1'
                )));
                $scope->mock->expects('sample').withExactArgs('c1');
                $scope->p->connect();
                setTimeout(function() {
                    $scope->mock->verify();
                    $scope->amqpscope->done();
                    done();
                }, 1);
            });

            it('should enable', function() {
                $scope->amqpscope->publish('', $BSON->serialize(Array(
                    'request' => 'enable',
                        'probeKey' => 'p.m.p1',
                        'consumerId' => 'c1',
                        'args' => Array(123)
                )));
                $scope->mock->expects('enableForConsumer').withExactArgs('c1', 123, 'p.m.p1');
                $scope->p->connect();
                setTimeout(function() {
                    $scope->mock->verify();
                    $scope->amqpscope->done();
                    done();
                }, 1);
            });

            it('should stop', function() {
                $scope->amqpscope->publish('', $BSON->serialize(Array(
                    'request' => 'stop',
                        'probeKey' => 'p.m.p1',
                        'consumerId' => 'c1'
                )));
                $scope->mock->expects('stop').withExactArgs('c1');
                $scope->p->connect();
                setTimeout(function() {
                    $scope->mock->verify();
                    $scope->amqpscope->done();
                    done();
                }, 1);
            });
        });
 */
    });

    describe('#disconnect', function() {
/*
        it('should end connection', function() {
            $p = new Provider(Array(
                'name' => 'p'
            ));
            $p->samples = Array();
            $p->requests = Array();
            $p->connection = Array(
                'end' => function() {}
            );
            $mock = $sinon->mock($p->connection);
            $mock->expects('end').once();
            $p->disconnect();
            $mock->verify();
            expect($p->connection)->to_be_null();
            expect($p->samples)->to_be_null();
            expect($p->requests)->to_be_null();
        });
 */

    });

});

