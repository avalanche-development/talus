<?php

namespace Jacobemerick\Talus;

use PHPUnit_Framework_TestCase;

class TalusTest extends PHPUnit_Framework_TestCase
{

    public function testIsInstanceOfTalus()
    {
        $talus = new Talus([]);

        $this->assertInstanceOf('Jacobemerick\Talus\Talus', $talus);
    }

    public function testConstructSetsConfig()
    {
        $config = [
            'foo' => 'bar',
        ];
        $talus = new Talus($config);

        $this->assertAttributeEquals($config, 'config', $talus);
    }
}
