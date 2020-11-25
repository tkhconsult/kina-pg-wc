<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit50478dfc75549edea87a587529262f06
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'TkhConsult\\KinaBankGateway\\' => 30,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'TkhConsult\\KinaBankGateway\\' =>
        array (
            0 => __DIR__ . '/..' . '/tkhconsult/kina-bank-gateway/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit50478dfc75549edea87a587529262f06::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit50478dfc75549edea87a587529262f06::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
