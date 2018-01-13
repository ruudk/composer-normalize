<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018 Andreas Möller.
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/localheinz/composer-normalize
 */

namespace Localheinz\Composer\Normalize\Test\Unit\Normalizer;

use Localheinz\Composer\Normalize\Normalizer\BinNormalizer;
use Localheinz\Composer\Normalize\Normalizer\ComposerJsonNormalizer;
use Localheinz\Composer\Normalize\Normalizer\ConfigHashNormalizer;
use Localheinz\Composer\Normalize\Normalizer\PackageHashNormalizer;
use Localheinz\Json\Normalizer\AutoFormatNormalizer;
use Localheinz\Json\Normalizer\ChainNormalizer;
use Localheinz\Json\Normalizer\NormalizerInterface;
use Localheinz\Json\Normalizer\SchemaNormalizer;

final class ComposerJsonNormalizerTest extends AbstractNormalizerTestCase
{
    public function testComposesNormalizers()
    {
        $normalizer = new ComposerJsonNormalizer();

        $this->assertComposesNormalizer(AutoFormatNormalizer::class, $normalizer);

        $autoFormatNormalizer = $this->composedNormalizer($normalizer);

        $this->assertComposesNormalizer(ChainNormalizer::class, $autoFormatNormalizer);

        $chainNormalizer = $this->composedNormalizer($autoFormatNormalizer);

        $normalizerClassNames = [
            SchemaNormalizer::class,
            BinNormalizer::class,
            ConfigHashNormalizer::class,
            PackageHashNormalizer::class,
        ];

        $this->assertComposesNormalizers($normalizerClassNames, $chainNormalizer);

        $chainedNormalizers = $this->composedNormalizers($chainNormalizer);

        $schemaNormalizer = \array_shift($chainedNormalizers);

        $this->assertInstanceOf(SchemaNormalizer::class, $schemaNormalizer);
        $this->assertAttributeSame('https://getcomposer.org/schema.json', 'schemaUri', $schemaNormalizer);
    }

    public function testNormalizeNormalizes()
    {
        $json = <<<'JSON'
{
  "name": "foo/bar",
  "description": "In der Fantasie geht alles",
  "type": "library",
  "license": "MIT",
  "keywords": [
    "null",
    "helmut",
    "körschgen"
  ],
  "authors": [
    {
      "role": "Lieutenant",
      "homepage": "http://example.org",
      "name": "Helmut Körschgen"
    }
  ],
  "config": {
    "sort-packages": true,
    "preferred-install": "dist"
  },
  "require": {
    "localheinz/json-printer": "^1.0.0",
    "php": "^7.0"
  },
  "require-dev": {
    "localheinz/test-util": "0.6.1",
    "phpunit/phpunit": "^6.5.5",
    "localheinz/php-cs-fixer-config": "~1.11.0"
  },
  "autoload": {
    "psr-4": {
      "Helmut\\Foo\\Bar\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Helmut\\Foo\\Bar\\Test\\": "test/"
    }
  },
  "bin": [
    "scripts/null-null.php",
    "hasenbein.php"
  ]
}
JSON;

        $normalized = <<<'JSON'
{
  "name": "foo/bar",
  "type": "library",
  "description": "In der Fantasie geht alles",
  "keywords": [
    "null",
    "helmut",
    "körschgen"
  ],
  "license": "MIT",
  "authors": [
    {
      "name": "Helmut Körschgen",
      "homepage": "http://example.org",
      "role": "Lieutenant"
    }
  ],
  "require": {
    "php": "^7.0",
    "localheinz/json-printer": "^1.0.0"
  },
  "require-dev": {
    "localheinz/php-cs-fixer-config": "~1.11.0",
    "localheinz/test-util": "0.6.1",
    "phpunit/phpunit": "^6.5.5"
  },
  "config": {
    "preferred-install": "dist",
    "sort-packages": true
  },
  "autoload": {
    "psr-4": {
      "Helmut\\Foo\\Bar\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Helmut\\Foo\\Bar\\Test\\": "test/"
    }
  },
  "bin": [
    "hasenbein.php",
    "scripts/null-null.php"
  ]
}
JSON;

        $normalizer = new ComposerJsonNormalizer(\sprintf(
            'file://%s',
            \realpath(__DIR__ . '/../../Fixture/composer-schema.json')
        ));

        $this->assertSame($normalized, $normalizer->normalize($json));
    }

    private function assertComposesNormalizer(string $className, NormalizerInterface $normalizer)
    {
        $this->assertClassExists($className);
        $this->assertClassImplementsInterface(NormalizerInterface::class, $className);

        $attributeName = 'normalizer';

        $this->assertObjectHasAttribute($attributeName, $normalizer, \sprintf(
            'Failed asserting that a normalizer has an attribute "%s".',
            $attributeName
        ));

        $composedNormalizer = $this->composedNormalizer($normalizer);

        $this->assertInstanceOf($className, $composedNormalizer, \sprintf(
            'Failed asserting that a normalizer composes a normalizer of type "%s".',
            $className
        ));
    }

    private function assertComposesNormalizers(array $classNames, NormalizerInterface $normalizer)
    {
        foreach ($classNames as $className) {
            $this->assertClassExists($className);
            $this->assertClassImplementsInterface(NormalizerInterface::class, $className);
        }

        $attributeName = 'normalizers';

        $this->assertObjectHasAttribute($attributeName, $normalizer, \sprintf(
            'Failed asserting that a normalizer has an attribute "%s".',
            $attributeName
        ));

        $composedNormalizers = $this->composedNormalizers($normalizer);

        $composedNormalizerClassNames = \array_map(function ($normalizer) {
            return \get_class($normalizer);
        }, $composedNormalizers);

        $this->assertSame(
            $classNames,
            $composedNormalizerClassNames,
            'Failed asserting that a normalizer composes normalizers as expected.'
        );
    }

    private function composedNormalizer(NormalizerInterface $normalizer)
    {
        return $this->attributeValue(
            'normalizer',
            $normalizer
        );
    }

    private function composedNormalizers(NormalizerInterface $normalizer)
    {
        return $this->attributeValue(
            'normalizers',
            $normalizer
        );
    }

    private function attributeValue(string $name, NormalizerInterface $normalizer)
    {
        $reflection = new \ReflectionObject($normalizer);

        $property = $reflection->getProperty($name);

        $property->setAccessible(true);

        return $property->getValue($normalizer);
    }
}
