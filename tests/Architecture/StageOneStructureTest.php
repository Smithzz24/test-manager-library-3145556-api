<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class StageOneStructureTest extends TestCase
{
    public function test_core_layers_are_reserved_without_framework_or_business_code(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['Domain', 'Application'] as $layer) {
            $directory = $root.'/app/'.$layer;

            $this->assertDirectoryExists($directory);
            $this->assertFileExists($directory.'/.gitkeep');

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                $this->assertSame('.gitkeep', $file->getFilename());
            }
        }

        $this->assertDirectoryExists($root.'/app/Infrastructure');
        $this->assertDirectoryDoesNotExist($root.'/app/Models');
        $this->assertDirectoryDoesNotExist($root.'/database/migrations');
    }
}
