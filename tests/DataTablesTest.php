<?php

namespace KPT\Tests;

use PHPUnit\Framework\TestCase;

class DataTablesTest extends TestCase
{
    public function testPhpFilesExist(): void
    {
        $this->assertFileExists(__DIR__ . '/../src/class/DataTables.php');
        $this->assertFileExists(__DIR__ . '/../src/class/Renderer.php');
        $this->assertFileExists(__DIR__ . '/../src/class/AjaxHandler.php');
    }

    public function testAssetFilesExist(): void
    {
        $this->assertFileExists(__DIR__ . '/../src/assets/js/datatables.js');
    }

    public function testComposerJsonExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../composer.json');
    }
}