<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit182ac5b9ce49438bde2ffdede721857e
{
    public static $files = array (
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
        '2cffec82183ee1cea088009cef9a6fc3' => __DIR__ . '/..' . '/ezyang/htmlpurifier/library/HTMLPurifier.composer.php',
        '9e4824c5afbdc1482b6025ce3d4dfde8' => __DIR__ . '/..' . '/league/csv/src/functions_include.php',
    );

    public static $prefixLengthsPsr4 = array (
        'Z' => 
        array (
            'ZipStream\\' => 10,
        ),
        'S' => 
        array (
            'Symfony\\Polyfill\\Mbstring\\' => 26,
        ),
        'P' => 
        array (
            'Psr\\SimpleCache\\' => 16,
            'Psr\\Http\\Message\\' => 17,
            'Psr\\Http\\Client\\' => 16,
            'PhpOffice\\PhpSpreadsheet\\' => 25,
        ),
        'M' => 
        array (
            'MyCLabs\\Enum\\' => 13,
            'Matrix\\' => 7,
        ),
        'L' => 
        array (
            'League\\Csv\\' => 11,
        ),
        'C' => 
        array (
            'Complex\\' => 8,
            'Carbon_Fields\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'ZipStream\\' => 
        array (
            0 => __DIR__ . '/..' . '/maennchen/zipstream-php/src',
        ),
        'Symfony\\Polyfill\\Mbstring\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-mbstring',
        ),
        'Psr\\SimpleCache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/simple-cache/src',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-factory/src',
            1 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'Psr\\Http\\Client\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-client/src',
        ),
        'PhpOffice\\PhpSpreadsheet\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet',
        ),
        'MyCLabs\\Enum\\' => 
        array (
            0 => __DIR__ . '/..' . '/myclabs/php-enum/src',
        ),
        'Matrix\\' => 
        array (
            0 => __DIR__ . '/..' . '/markbaker/matrix/classes/src',
        ),
        'League\\Csv\\' => 
        array (
            0 => __DIR__ . '/..' . '/league/csv/src',
        ),
        'Complex\\' => 
        array (
            0 => __DIR__ . '/..' . '/markbaker/complex/classes/src',
        ),
        'Carbon_Fields\\' => 
        array (
            0 => __DIR__ . '/..' . '/htmlburger/carbon-fields/core',
        ),
    );

    public static $prefixesPsr0 = array (
        'K' => 
        array (
            'KubAT\\PhpSimple\\HtmlDomParser' => 
            array (
                0 => __DIR__ . '/..' . '/kub-at/php-simple-html-dom-parser/src',
            ),
        ),
        'H' => 
        array (
            'HTMLPurifier' => 
            array (
                0 => __DIR__ . '/..' . '/ezyang/htmlpurifier/library',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit182ac5b9ce49438bde2ffdede721857e::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit182ac5b9ce49438bde2ffdede721857e::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit182ac5b9ce49438bde2ffdede721857e::$prefixesPsr0;
            $loader->classMap = ComposerStaticInit182ac5b9ce49438bde2ffdede721857e::$classMap;

        }, null, ClassLoader::class);
    }
}
