<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class StageOneStructureTest extends TestCase
{
    public function test_core_layers_have_no_framework_or_outward_dependencies(): void
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
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
                    if (! is_array($token) || ! in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        continue;
                    }
                    $name = ltrim($token[1], '\\');
                    $allowed = ! str_contains($name, '\\') || $name === 'App\\Domain'
                        || str_starts_with($name, 'App\\Domain\\')
                        || ($layer === 'Application' && ($name === 'App\\Application' || str_starts_with($name, 'App\\Application\\')));
                    $this->assertTrue($allowed, "{$file->getFilename()} references forbidden namespace {$name}");
                }
            }
        }

        $this->assertDirectoryExists($root.'/app/Infrastructure');
        $this->assertDirectoryDoesNotExist($root.'/app/Models');
        $this->assertDirectoryExists($root.'/database/migrations');
    }
}
