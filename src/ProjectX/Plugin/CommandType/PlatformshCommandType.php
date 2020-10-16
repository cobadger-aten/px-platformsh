<?php

declare(strict_types=1);

namespace Pr0jectX\PxPlatformsh\ProjectX\Plugin\CommandType;

use Pr0jectX\Px\ConfigTreeBuilder\ConfigTreeBuilder;
use Pr0jectX\Px\ProjectX\Plugin\PluginCommandRegisterInterface;
use Pr0jectX\Px\ProjectX\Plugin\PluginConfigurationBuilderInterface;
use Pr0jectX\Px\ProjectX\Plugin\PluginTasksBase;
use Pr0jectX\PxPlatformsh\ProjectX\Plugin\CommandType\Commands\PlatformshCommand;
use Symfony\Component\Console\Question\Question;

/**
 * Define the platformsh command type.
 */
class PlatformshCommandType extends PluginTasksBase implements PluginConfigurationBuilderInterface, PluginCommandRegisterInterface
{
    /**
     * @inheritDoc
     */
    public static function pluginId(): string
    {
        return 'platformsh';
    }

    /**
     * @inheritDoc
     */
    public static function pluginLabel(): string
    {
        return 'Platformsh';
    }

    /**
     * @inheritDoc
     */
    public function registeredCommands(): array
    {
        return [
            PlatformshCommand::class,
        ];
    }

    /**
     * Get the platformsh site machine name.
     *
     * @return string
     *   The platformsh site machine name.
     */
    public function getPlatformshSite(): string
    {
        return $this->getConfigurations()['site'] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function pluginConfiguration(): ConfigTreeBuilder
    {
        return (new ConfigTreeBuilder())
            ->setQuestionInput($this->input)
            ->setQuestionOutput($this->output)
            ->createNode('site')
                ->setValue((new Question(
                    $this->formatQuestion('Input the site machine name')
                ))->setValidator(function ($value) {
                    if (empty($value)) {
                        throw new \RuntimeException(
                            'The site machine name is required!'
                        );
                    }
                    if (!preg_match('/^[\w-]+$/', $value)) {
                        throw new \RuntimeException(
                            'The site machine name format is invalid!'
                        );
                    }
                    return $value;
                }))
            ->end();
    }
}
