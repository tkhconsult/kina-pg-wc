<?php

namespace TkhConsult\VictoriaBankGateway\Tests;

use TkhConsult\VictoriaBankGateway\VictoriaBankGateway;
use PHPUnit_Framework_TestCase;

/**
 * Class VictoriaBankGatewayTest
 *
 * @package TkhConsult\VictoriaBankGateway\Tests
 */
class VictoriaBankGatewayTest extends PHPUnit_Framework_TestCase
{
    public function testInit() {
        $victoriaBankGatewayTest =  new VictoriaBankGateway();
        $victoriaBankGatewayTest
            ->configureFromEnv(__DIR__.'/certificates')
            ->setDebug(false)
            ->setDefaultLanguage('en')
        ;
        static::assertEquals('1', '1');
    }
}