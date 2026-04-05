<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class TemplateLocationTest extends TestCase
{
    public function testTemplateClassesAreRelocatedOutOfPluginDirectory(): void
    {
        $appRoot = dirname(__DIR__, 2);

        $legacyPluginTemplatePath = $appRoot . DIRECTORY_SEPARATOR . 'plugin/PluginTemplate/PluginTemplate.php';
        $legacyScriptTemplatePath = $appRoot . DIRECTORY_SEPARATOR . 'plugin/ScriptPluginTemplate/ScriptPluginTemplate.php';
        $newPluginTemplatePath = $appRoot . DIRECTORY_SEPARATOR . 'src/Plugin/Templates/PluginTemplate.php';
        $newScriptTemplatePath = $appRoot . DIRECTORY_SEPARATOR . 'src/Plugin/Templates/ScriptPluginTemplate.php';

        self::assertFileDoesNotExist($legacyPluginTemplatePath);
        self::assertFileDoesNotExist($legacyScriptTemplatePath);

        self::assertFileExists($newPluginTemplatePath);
        self::assertFileExists($newScriptTemplatePath);

        require_once $newPluginTemplatePath;
        require_once $newScriptTemplatePath;

        self::assertTrue(class_exists('PluginTemplate', false));
        self::assertTrue(class_exists('ScriptPluginTemplate', false));
    }
}
