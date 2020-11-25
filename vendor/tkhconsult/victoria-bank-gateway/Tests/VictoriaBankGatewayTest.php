<?php

namespace TkhConsult\KinaBankGateway\Tests;

use TkhConsult\KinaBankGateway\KinaBankGateway;
use PHPUnit_Framework_TestCase;

/**
 * Class KinaBankGatewayTest
 *
 * @package TkhConsult\KinaBankGateway\Tests
 */
class KinaBankGatewayTest extends PHPUnit_Framework_TestCase
{
    public function testInit() {
        $victoriaBankGatewayTest =  new KinaBankGateway();
        $victoriaBankGatewayTest
            ->configureFromEnv(__DIR__.'/certificates')
            ->setDebug(false)
            ->setDefaultLanguage('en')
        ;
        static::assertEquals('1', '1');
    }
}