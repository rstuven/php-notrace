<?php

describe('Probe', function() {

    describe('#constructor', function() {

        it('should initialize properties', function() {
            $p = new Probe('probe name', 'number', 'string', 'number');
            expect($p->name)->to_be('probe name');
            expect($p->enabled)->to_be(false);
            expect($p->instant)->to_be(false);
            expect($p->types)->to_be(Array('number', 'string', 'number'));
            expect($p->args)->to_be(Array(0, '', 0));
        });

        it('should accept a function as argument', function() {
            $p = new Probe(Array(
                'name' => 'p',
                'args' => function() {
                    return 123;
                }
            ));
            expect($p->args)->to_be_type('object');
            expect(call_user_func($p->args))->to_be(123);
        });
/*
     it('should throw error on missing name', function() {
       should["throw"](function() {
         $p = new Probe;
       });
       should["throw"](function() {
         $p = new Probe({});
       });
     });
     it('should throw error on name containing reserved chars', function() {
       should["throw"](function() {
         $p = new Probe('.');
       });
       should["throw"](function() {
         $p = new Probe('#');
       });
       should["throw"](function() {
         $p = new Probe('*');
       });
       should.not["throw"](function() {
         $p = new Probe('\'$!"%&/\\(){}[]=?');
       });
     });
 */
    });

    describe('#update', function() {

        it('should change if disabled', function() {
            $p = new Probe('p', 'string');
            $p->update('an argument');
            expect($p->args)->to_be(Array('an argument'));
        });

        it('should change a single argument', function() {
            $p = new Probe('p', 'string');
            $p->update('an argument');
            expect($p->args)->to_be_type('array');
            expect($p->args)->to_be(Array('an argument'));
        });

        it('should change a tuple', function() {
            $p = new Probe('p', 'string', 'string');
            $p->update('a', 'b');
            expect($p->args)->to_be_type('array');
            expect($p->args)->to_be(Array('a', 'b'));
        });

        it('should not throw error on set argument of wrong type (number)', function() {
            $p = new Probe('p');
            $p->update('x');
        });

        it('should not throw error on set argument of wrong type (string)', function() {
            $p = new Probe('p', 'number', 'string');
            $p->update(1, 2);
        });

    });

    describe('#evaluate', function() {

        it('should accept an argument', function() {
            $p = new Probe('p');
            $args = $p->evaluate(Array(123));
            expect($args)->to_be(Array(Array(123)));
        });

        it('should accept a function as argument', function() {
            $p = new Probe('p');
            $args = $p->evaluate(function() {
                return Array(Array(123)); // must return an array of args array.
            });
            expect($args)->to_be(Array(Array(123)));
        });

    });
    describe('#sample', function() {

        it('should return a sample on sync argument', function() {
            $p = new Probe(Array(
                'name' => 'p',
                'args' => function() {
                    return Array(Array(123));
                }
            ));
            $s = $p->sample('c1');
            expect($s[0]->payload->args)->not_to_be_null();
            expect($s[0]->payload->args)->to_be(Array(123));
            expect($s[0]->consumerId)->to_be('c1');
        });
/*
    it('should return multiple samples on async argument', function(done) {
      $p = new Probe({
        name: 'p',
        args: function(cb) {
          setTimeout((function() {
            cb(null, 123);
          }), 1);
          setTimeout((function() {
            cb(null, 456);
          }), 2);
          return;
        }
      });
      spy = sinon.spy();
      setTimeout(function() {
        $p->sample('c1', spy);
      }, 3);
      setTimeout(function() {
        spy.should.have.been.calledTwice;
        done();
      }, 10);
    });
 */
        it('should return a timestamp', function() {
            $p = new Probe('p');
            $s = $p->sample('c1');
            expect($s[0]->payload->timestamp)->not_to_be_null();
        });

        it('should change the timestamp', function() {
            $p = new Probe(Array(
                'name' => 'p',
                'sampleThreshold' => 0
            ));
            $s1 = $p->sample('c1');
            $ts = $s1[0]->payload->timestamp;
            usleep(1 * 1000);
            $s2 = $p->sample('c2');
            expect($s2[0])->not_to_be_null();
            expect($s2[0]->payload->timestamp)->not_to_be($ts);
        });

        it('should not emit event', function() {
            $p = new Probe(Array(
                'name' => 'p'
            ));
            $p->on('sample', function($sample) {
                throw new \Exception("sample event emitted");
            });
            $p->update(123);
        });

        it('should emit event explicitly', function() {
            $p = new Probe(Array(
                'name' => 'p',
                'enabled' => true
            ));
            $p->on('sample', function($sample) {
                expect($sample->payload->args)->to_be(Array(123));
            });
            $p->update(123);
            $p->sample();
        });

        it('should emit event implicitly', function() {
            $p = new Probe(Array(
                'name' => 'p',
                'instant' => true,
                'enabled' => true
            ));
            $p->on('sample', function($sample) {
                expect($sample->payload->args)->to_be(Array(123));
            });
            $p->update(123);
        });

        it('should not emit below threshold', function() {
            $p = new Probe(Array(
                'name' => 'p',
                'sampleThreshold' => 1000
            ));
            $count = 0;
            $p->on('sample', function($sample) use (&$count) {
                $count++;
            });
            $p->sample();
            $p->sample();
            expect($count)->to_be(1);
        });

        it('should emit above threshold', function() {
            $p = new Probe(Array(
                'name' => 'p',
                'sampleThreshold' => 5
            ));
            $count = 0;
            $p->on('sample', function($sample) use (&$count) {
                $count++;
            });
            $p->sample();
            usleep(10 *999);
            $p->sample();
            expect($count)->to_be(2);
        });

        it('should emit if threshold is 0', function() {
            $p = new Probe(Array(
                'name' => 'p',
                'sampleThreshold' => 0
            ));
            $count = 0;
            $p->on('sample', function($sample) use (&$count) {
                $count++;
            });
            $p->sample();
            $p->sample();
            expect($count)->to_be(2);
        });
    });

    describe('#increment', function() {

        it('should add an offset in the first argument by default', function() {
            $p = new Probe('p', 'number');
            $p->increment(1);
            expect($p->args)->to_be(Array(1));
            $p->increment(2);
            expect($p->args)->to_be(Array(3));
        });

        it('should add an offset by index', function() {
            $p = new Probe('p', 'number', 'number');
            $p->increment(1, 1);
            expect($p->args)->to_be(Array(0, 1));
            $p->increment(2, 1);
            expect($p->args)->to_be(Array(0, 3));
        });

        it('should throw error on increment non number', function() {
            $p = new Probe('p', 'string');
            expect(function() use($p) {
                $p->increment(1);
            })->to_throw();
        });

        it('should throw error on outbound index', function() {
            $p = new Probe('p', 'string');
            expect(function() use($p) {
                $p->increment(1, 1);
            })->to_throw();
        });

        it('should emit a sample', function() {
            $p = new Probe('p', 'number');
            $p->enabled = true;
            $p->instant = true;
            $p->sampleThreshold = 0;
            $count = 0;
            $p->on('sample', function($sample) use (&$count) {
                expect($sample->payload->args)->to_be(Array(1));
                $count++;
            });
            $p->increment(1);
            expect($count)->to_be(1);
            expect(1)->to_be(1);
        });


    });

});

