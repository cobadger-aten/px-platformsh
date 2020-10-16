<?php

declare(strict_types=1);

namespace Pr0jectX\PxPlatformsh;

/**
 * Define the platformsh reusable commands.
 */
class Platformsh
{
    /**
     * Display the platformsh banner artwork.
     */
    public static function displayBanner(): void
    {
        print file_get_contents(
            dirname(__DIR__) . '/banner.txt'
        );
    }

    /**
     * Get the platformsh environments.
     *
     * @return array
     *   An array of environments.
     */
    public static function environments(): array
    {
        return [
            'dev' => 'Dev',
            'test' => 'Test',
            'live' => 'Live'
        ];
    }

    /**
     * Load the template file contents.
     *
     * @param string $filename
     *   The filename to the template to load.
     *
     * @return string
     *   The loaded template file path.
     */
    public static function loadTemplateFile(string $filename): string
    {
        return file_get_contents(
            static::getTemplatePath($filename)
        );
    }

    /**
     * Get the template path.
     *
     * @param string $filename
     *   The filename to template path to retrieve.
     *
     * @return string
     *   The full path to the template file.
     */
    protected static function getTemplatePath(string $filename): string
    {
        $baseTemplateDir = dirname(__DIR__) . '/templates';
        $templateFullPath = "{$baseTemplateDir}/{$filename}";

        if (!file_exists($templateFullPath)) {
            throw new \RuntimeException(
                sprintf('Unable to locate the %s template file!', $filename)
            );
        }

        return $templateFullPath;
    }
}
