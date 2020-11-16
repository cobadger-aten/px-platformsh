<?php

declare(strict_types=1);

namespace Pr0jectX\PxPlatformsh\ProjectX\Plugin\CommandType\Commands;

use Pr0jectX\Px\ProjectX\Plugin\PluginCommandTaskBase;
use Pr0jectX\Px\PxApp;
use Pr0jectX\PxPlatformsh\Platformsh;
use Pr0jectX\PxPlatformsh\ProjectX\Plugin\CommandType\PlatformshCommandType;
use Pr0jectX\Px\Task\LoadTasks as PxTasks;
use Psr\Cache\CacheItemInterface;
use Robo\Collection\CollectionBuilder;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Define the platformsh command.
 *
 * TODO: Discuss the difference in CLI capabilities for these methods:
 * - platformshInfo
 * - platformshImport
 *   for pantheon this is connection:info, this isn't really available the same way
 *
 * - create a base class
 * - potentially abstract out "isCliInstalled", "installCli"
 *   and add to a base class
 *
 */
class PlatformshCommand extends PluginCommandTaskBase
{
    use PxTasks;

    /**
     * Authenticate with the platformsh service.
     */
    public function platformshLogin(): void
    {
        $login_type = $this->askChoice('Select the login type', [
            'api-token-login' => 'Login in to Platform.sh using an API token',
            'browser-login' => 'Log in to Platform.sh via a browser',
        ], 'api-token-login');
        Platformsh::displayBanner();

        $this->cliCommand()
            ->setSubCommand("auth:$login_type")
            ->run();
    }

    public function platformshInfo($siteEnv = null): void
    {

        Platformsh::displayBanner();

        $siteName = $this->getPlugin()->getPlatformshSite();
        $siteEnv = $siteEnv ?? $this->askForPlatformshSiteEnv();
        try {
        $this->cliCommand()
             ->setSubCommand('environment:info')
             ->option('environment', $siteEnv)
           ->run();
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }

    }

//    /**
//     * Setup the project to use the platformsh service.
//     */
//    public function platformshSetup(): void
//    {
//        Platformsh::displayBanner();
//
//        $phpVersions = PxApp::activePhpVersions();
//
//        $phpVersion = $this->askChoice(
//            'Select the PHP version',
//            $phpVersions,
//            $phpVersions[1]
//        );
//
//        $this->taskWriteToFile(PxApp::projectRootPath() . '/platformsh.yml')
//            ->text(Platformsh::loadTemplateFile('platformsh.yml'))
//            ->place('PHP_VERSION', $phpVersion)
//            ->run();
//
//        $framework = $this->askChoice('Select the PHP framework', [
//            'drupal' => 'Drupal',
//            'wordpress' => 'Wordpress'
//        ], 'drupal');
//
//        try {
//            if ($framework === 'drupal') {
//                $this->setupDrupal();
//            }
//        } catch (\Exception $exception) {
//            $this->error($exception->getMessage());
//        }
//    }

    /**
     * Install the platform.sh command utility system-wide.
     */
    public function platformshInstallCli(): void
    {
        if (!$this->isCliInstalled()) {
            try {
                $userDir = PxApp::userDir();

                $stack = $this->taskExecStack()
                    ->exec("curl -sS https://platform.sh/cli/installer | php");

                $results = $stack->run();

                if ($results->wasSuccessful()) {
                    $this->success(
                        'Platform CLI has successfully been installed!'
                    );
                }
            } catch (\Exception $exception) {
                $this->error($exception->getMessage());
            }
        } else {
            $this->note('Platform CLI is already installed!');
        }
    }

//    /**
//     * Import the local database with the remote platformsh site.
//     *
//     * @param string $dbFile
//     *   The local path to the database file.
//     * @param string $siteEnv
//     *   The platformsh site environment.
//     */
//    public function platformshImport(
//        string $dbFile = null,
//        string $siteEnv = 'dev'
//    ): void {
//
//        try {
//            $siteName = $this->getPlatformshSiteName();
//
//            if (
//                !isset(Platformsh::environments()[$siteEnv])
//                || in_array($siteEnv, ['test', 'live'])
//            ) {
//                throw new \RuntimeException(
//                    'The environment is invalid! Only the dev environment is allowed at this time!'
//                );
//            }
//            $dbFile = $dbFile ?? $this->exportEnvDatabase();
//
//            if (!file_exists($dbFile)) {
//                throw new \RuntimeException(
//                    'The database file path is invalid!'
//                );
//            }
//
//            $result = $this->cliCommand()
//                ->setSubCommand('import:database')
//                ->args(["{$siteName}.{$siteEnv}", $dbFile])
//                ->run();
//
//            if ($result->wasSuccessful()) {
//                $this->success(
//                    'The database was successfully imported into the platformsh site.'
//                );
//            }
//        } catch (\Exception $exception) {
//            $this->error($exception->getMessage());
//        }
//    }

    /**
     * Run drush commands against the remote Drupal site.
     *
     * @aliases remote:drupal
     *
     * @param array $cmd
     *   An arbitrary drush command.
     */
    public function platformshDrupal(array $cmd): void
    {
        try {
            $siteName = $this->getPlatformshSiteName();
            $siteEnv = $this->askForPlatformshSiteEnv();

            $command = $this->cliCommand()
              ->setSubCommand('drush')
              ->option('environment', $siteEnv);

            if (!empty($cmd)) {
                $command->args(['--', implode(' ', $cmd)]);
            }
            $command->run();
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Create a site on the remote platformsh service.
     *
     * @param string|null $label
     *   Set the site human readable label.
     * @param string|null $upstream
     *   Set the site upstream.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function platformshCreateSite(
        string $label = null,
        string $upstream = null
    ): void {
        Platformsh::displayBanner();

        try {
            $label = $label ?? $this->doAsk((new Question(
                $this->formatQuestion('Input the site label')
            ))->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException(
                        'The site label is required!'
                    );
                }
                return $value;
            }));

            $upstreamOptions = $this->getUpstreamOptions();

            $upstream = $upstream ?? $this->askChoice(
                'Select the site upstream',
                $upstreamOptions,
                'empty'
            );

            if (isset($upstream) && !isset($upstreamOptions[$upstream])) {
                throw new \InvalidArgumentException(
                    sprintf('The site upstream value is invalid!')
                );
            }
            $name = strtolower(strtr($label, ' ', '-'));

            /** @var \Robo\Collection\CollectionBuilder $command */
            $command = $this->cliCommand()
                ->setSubCommand('site:create')
                ->args([
                    $name,
                    $label,
                    $upstream
                ]);

            if ($this->confirm('Associate the site with an organization?', false)) {
                $orgOptions = $this->getOrgOptions();

                if (count($orgOptions) !== 0) {
                    if ($org = $this->askChoice('Select an organization', $orgOptions)) {
                        $command->option('org', $org);
                    }
                }
            }
            $result = $command->run();

            if ($result->wasSuccessful()) {
                $this->success(
                    'The platformsh site was successfully created!'
                );
            }
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Add a team member to the remote platformsh service.
     *
     * @param string $email
     *   The member email address.
     * @param string $role
     *   The member role name, e.g. (developer, team_member).
     */
    public function platformshAddMember(
        string $email = null,
        string $role = 'team_member'
    ): void {
        Platformsh::displayBanner();

        try {
            $siteName = $this->getPlatformshSiteName();

            $email = $email ?? $this->doAsk(
                (new Question(
                    $this->formatQuestion('Input the site user email address')
                ))->setValidator(function ($value) {
                    if (empty($value)) {
                        throw new \RuntimeException(
                            'The user email address is required!'
                        );
                    }
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new \RuntimeException(
                            'The user email address is invalid!'
                        );
                    }
                    return $value;
                })
            );
            $roleOptions = ['developer', 'team_member'];

            if (!in_array($role, $roleOptions)) {
                $this->error(
                    sprintf('The user role is invalid!')
                );
                return;
            }

            $result = $this->cliCommand()
                ->setSubCommand('site:team:add')
                ->args([$siteName, $email, $role])
                ->run();

            if ($result->wasSuccessful()) {
                $this->success(sprintf(
                    'The user with %s email has successfully been added!',
                    $email
                ));
            }
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    protected function askForPlatformApp(array $exclude = [])
    {
      $exclude = [];
      $output = $this->cliCommand()
          ->setSubCommand('app:list')
          ->option('format', 'csv')
          ->printOutput(false)
          ->silent(true)
          ->run();

      $csv_string = $output->getMessage();
      $choices = $this->csvStringToOptions($csv_string, 'Name');

      return $this->askChoice(
          'Select the environment app',
          array_filter($choices, function ($key) use ($exclude) {
              return !in_array($key, $exclude);
          }, ARRAY_FILTER_USE_KEY)
      );
    }

    private function csvStringToOptions($csv_string, $key)
    {
      $lines = explode(PHP_EOL, $csv_string);
      $csv_parsed = array_map('str_getcsv', $lines);
      $header = array_shift($csv_parsed);

      return array_reduce($csv_parsed, function($carry, $item) use ($header, $key) {
          $arr = array_combine($header, $item);
          $carry[$arr[$key]] = $arr[$key];
          return $carry;
      }, []);
    }

    private function csvToArray($csv_string, $column_count) {
      $lines = explode(PHP_EOL, $csv_string);
      $csv_parsed = array_map('str_getcsv', $lines);

      $header = array_shift($csv_parsed);
      $header_key = array_shift($header);
      $info_array = [];
      foreach ($csv_parsed as $row) {
        $key = array_shift($row);
        $row = array_pad($row, count($header), '');

        $info_array[$key] = count($row) == 1 ? reset($row) : array_combine($header, $row);
      }

      return $info_array;
    }

    private function getEnvironmentList() {
      $info = $this->cliCommand()
        ->setSubCommand('environment:list')
        ->option('format', 'csv')
        ->printOutput(false)
        ->silent(true)
        ->run();

      $csv_string = $info->getMessage();
      $csv_array = $this->csvToArray($csv_string, 3);

      return array_filter($csv_array, function($value) {
          return !empty($value['Status']) && $value['Status'] == 'Active';
      });
    }

    private function getEnvironmentInfo() {
      $info = $this->cliCommand()
        ->setSubCommand('environment:info')
        ->option('format', 'csv')
        ->printOutput(false)
        ->silent(true)
        ->run();

      $csv_string = $info->getMessage();

      return $this->csvToArray($csv_string, 2);
    }

    /**
     *  Sync the remote platformsh database with the local environment.
     *
     * @param string $siteEnv
     *   The environment to sync the database from e.g (dev, test, live).
     * @param array $opts
     * @option $no-backup
     *   Don't create a backup prior to database retrieval.
     * @option $filename
     *   The filename of the remote database that's downloaded.
     * @option $env
     *   The branch name with an active environment.
     */
    public function platformshSync(string $siteEnv = null, $opts = [
        'no-backup' => false,
        'filename' => 'remote.db.sql.gz',
        'env' => 'auto',
    ]): void
    {
        Platformsh::displayBanner();

        if ($siteEnv) {
          $list = $this->getEnvironmentList();
          if (empty($list[$siteEnv])) {
            $this->error("The branch name $siteEnv is either invalid or has no active environment");
          }
        }
        if (!$siteEnv) {
          $env_info = $this->getEnvironmentInfo();
          if ($env_info['status'] == 'inactive') {
            $this->warning("The current branch {$env_info['id']} does not have an active environment");
            $siteEnv = $this->askForPlatformshSiteEnv('master');
          }
          else {
            $siteEnv = $env_info['id'];
          }
        }

        try {
            $app = $this->askForPlatformApp();

            $collection = $this->collectionBuilder();

            // TODO: backup:create might not be needed for this.

            $dbBackupFilename = implode(DIRECTORY_SEPARATOR, [
                PxApp::projectTempDir(),
                $opts['filename']
            ]);

            if (file_exists($dbBackupFilename)) {
                $this->_remove($dbBackupFilename);
            }

            $collection->addTask($this->cliCommand()
                ->setSubCommand('db:dump')
                ->option('app', $app)
                ->option('environment', $siteEnv)
                ->option('file', $dbBackupFilename));

            $backupResult = $collection->run();

            if ($backupResult->wasSuccessful()) {
                $this->importEnvDatabase($dbBackupFilename);
            } else {
                throw new \RuntimeException(sprintf(
                    'Unable to sync the %s.%s database with environment.',
                    $this->getPlatformshSiteName(),
                    $siteEnv
                ));
            }
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Determine if platform cli has been installed.
     *
     * @return bool
     *   Return true if platform cli is installed; otherwise false.
     */
    protected function isCliInstalled(): bool
    {
        return $this->hasExecutable('platform');
    }

    /**
     * Import the environment database.
     *
     * @param string $sourceFile
     *   The database source file.
     * @param bool $sourceCleanUp
     *   Delete the database source file after import.
     *
     * @return bool
     *   Return true if the import was successful; otherwise false.
     */
    protected function importEnvDatabase(
        string $sourceFile,
        bool $sourceCleanUp = true
    ): bool {
        if ($command = $this->findCommand('db:import')) {
            $syncDbCollection = $this->collectionBuilder();

            if (!PxApp::getEnvironmentInstance()->isRunning()) {
                $syncDbCollection->addTask(
                    $this->taskSymfonyCommand(
                        $this->findCommand('env:start')
                    )
                );
            }
            $syncDbCollection->addTask(
                $this->taskSymfonyCommand($command)->arg(
                    'source_file',
                    $sourceFile
                )
            );
            $importResult = $syncDbCollection->run();

            if ($importResult->wasSuccessful() && $sourceCleanUp) {
                $this->_remove($sourceFile);
                return true;
            }
        }

        throw new \RuntimeException(
            "The database was not automatically synced. As the environment doesn't support that feature!"
        );
    }

//    /**
//     * Export the environment database.
//     *
//     * @return string
//     *   Return the exported database file.
//     */
//    protected function exportEnvDatabase(): string
//    {
//        if ($command = $this->findCommand('db:export')) {
//            $dbFilename = 'local.db';
//
//            $dbExportFile = implode(
//                DIRECTORY_SEPARATOR,
//                [PxApp::projectTempDir(), $dbFilename]
//            );
//            $this->_remove($dbExportFile);
//
//            $exportResult = $this->taskSymfonyCommand($command)
//                ->arg('export_dir', PxApp::projectTempDir())
//                ->opt('filename', $dbFilename)
//                ->run();
//
//            if ($exportResult->wasSuccessful()) {
//                return "{$dbExportFile}.sql.gz";
//            }
//        }
//
//        throw new \RuntimeException('Unable to export the local database.');
//    }

//    /**
//     * Setup the Drupal platformsh integration.
//     */
//    protected function setupDrupal(): void
//    {
//        if (
//            !PxApp::composerHasPackage('drupal/core')
//            && !PxApp::composerHasPackage('drupal/core-recommended')
//        ) {
//            throw new \RuntimeException(
//                'Install Drupal core prior to running the platformsh setup.'
//            );
//        }
//        $drupalRoot = $this->findDrupalRoot() ?? $this->ask(
//            'Input the Drupal root'
//        );
//
//        $drupalRootPath = implode(
//            DIRECTORY_SEPARATOR,
//            [PxApp::projectRootPath(), $drupalRoot]
//        );
//
//        if (!file_exists($drupalRootPath)) {
//            throw new \RuntimeException(
//                "The Drupal root path doesn't exist!"
//            );
//        }
//        $collection = $this->collectionBuilder();
//        $drupalDefault = "{$drupalRootPath}/sites/default";
//
//        $collection
//            ->addTask(
//                $this->taskWriteToFile("{$drupalDefault}/settings.platformsh.php")
//                    ->text(Platformsh::loadTemplateFile('drupal/settings.platformsh.txt'))
//            );
//
//        $collection->addTask(
//            $this->taskWriteToFile("{$drupalDefault}/settings.php")
//                ->append()
//                ->appendUnlessMatches(
//                    '/^include.+settings.platformsh.php";$/m',
//                    Platformsh::loadTemplateFile('drupal/settings.include.txt')
//                )
//        );
//
//        if ($this->confirm('Add Drupal quicksilver scripts?', true)) {
//            $collection->addTaskList([
//                $this->taskWriteToFile(PxApp::projectRootPath() . '/platformsh.yml')
//                    ->append()
//                    ->appendUnlessMatches(
//                        '/^workflows:$/',
//                        Platformsh::loadTemplateFile('drupal/platformsh.workflows.txt')
//                    ),
//                $this->taskWriteToFile("{$drupalRootPath}/private/hooks/afterSync.php")
//                    ->text(Platformsh::loadTemplateFile('drupal/hooks/afterSync.txt')),
//                $this->taskWriteToFile("{$drupalRootPath}/private/hooks/afterDeploy.php")
//                    ->text(Platformsh::loadTemplateFile('drupal/hooks/afterDeploy.txt')),
//            ]);
//        }
//        $result = $collection->run();
//
//        if ($result->wasSuccessful()) {
//            $this->success(
//                sprintf('The platformsh setup was successful for the Drupal framework!')
//            );
//        }
//    }

    /**
     * Find the Drupal root directory.
     *
     * @return string
     *   The Drupal root directory if found.
     */
    protected function findDrupalRoot(): string
    {
        $composerJson = PxApp::getProjectComposer();

        if (
            isset($composerJson['extra'])
            && isset($composerJson['extra']['installer-paths'])
        ) {
            foreach ($composerJson['extra']['installer-paths'] as $path => $types) {
                if (!in_array('type:drupal-core', $types)) {
                    continue;
                }
                return dirname($path, 1);
            }
        }

        return '';
    }

    /**
     * Get the platformsh site name.
     *
     * @return string
     *   Return the platformsh site name.
     */
    protected function getPlatformshSiteName(): string
    {
        $siteName = $this->getPlugin()->getPlatformshSite();

        if (!isset($siteName) || empty($siteName)) {
            throw new \RuntimeException(
                "The platformsh site name is required.\nRun the `vendor/bin/px config:set platformsh` command."
            );
        }

        return $siteName;
    }

    /**
     * Ask to select the platformsh site environment.
     *
     * @param string $default
     *   The default site environment.
     *
     * @param array $exclude
     * @return string
     *   The platformsh site environment.
     */
    protected function askForPlatformshSiteEnv($default = 'dev', $exclude = []): string
    {
        return $this->askChoice(
            'Select the platformsh site environment',
            array_map(function($value) {
                return $value['Label'] ?? FALSE;
            }, $this->getEnvironmentList()),
            $default
        );
    }

    /**
     * Get the platformsh organization options.
     *
     * @return array
     *   An array of organization options.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getOrgOptions(): array
    {
        return $this->buildCommandListOptions(
            'org:list',
            'name',
            'label'
        );
    }

    /**
     * Get the platformsh upstream options.
     *
     * @return array
     *   An array of upstream options.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getUpstreamOptions(): array
    {
        return $this->buildCommandListOptions(
            'upstream:list',
            'machine_name',
            'label'
        );
    }

    /**
     * Build the command list options.
     *
     * @param string $subCommand
     *   The sub-command to execute.
     * @param string $optionKey
     *   The property to use for the option key.
     * @param string $valueKey
     *   The property to use for the value key.
     *
     * @return array
     *   An array of command list options.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function buildCommandListOptions(
        string $subCommand,
        string $optionKey,
        string $valueKey
    ): array {
        $options = [];

        foreach ($this->buildCommandListArray($subCommand) as $data) {
            if (!isset($data[$optionKey]) || !isset($data[$valueKey])) {
                continue;
            }
            $options[$data[$optionKey]] = $data[$valueKey];
        }
        ksort($options);

        return $options;
    }

    /**
     * Build the command list array output.
     *
     * @param string $subCommand
     *   The sub-command to execute.
     * @param int $cacheExpiration
     *   The cache expiration in seconds.
     *
     * @return array
     *   An array of the command output.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function buildCommandListArray(string $subCommand, $cacheExpiration = 3600): array
    {
        $cacheKey = strtr($subCommand, ':', '.');

        return $this->pluginCache()->get(
            "commandOutput.{$cacheKey}",
            function (CacheItemInterface $item) use ($subCommand, $cacheExpiration) {
                $item->expiresAfter($cacheExpiration);

                $result = $this->cliCommand()
                    ->setSubCommand($subCommand)
                    ->option('format', 'json')
                    ->printOutput(false)
                    ->silent(true)
                    ->run();

                if ($result->wasSuccessful()) {
                    return json_decode(
                        $result->getMessage(),
                        true
                    );
                }
            }
        ) ?? [];
    }

    /**
     * Determine if an executable exist.
     *
     * @param string $executable
     *   The name of the executable binary.
     *
     * @return bool
     *   Return true if executable exist; otherwise false.
     */
    protected function hasExecutable(string $executable): bool
    {
        return (new ExecutableFinder())->find($executable) !== null;
    }

    /**
     * Get the platformsh command type plugin.
     *
     * @return \Pr0jectX\PxPlatformsh\ProjectX\Plugin\CommandType\PlatformshCommandType
     */
    protected function getPlugin(): PlatformshCommandType
    {
        return $this->plugin;
    }

    /**
     * Retrieve the terminus command.
     *
     * @return \Pr0jectX\Px\Task\ExecCommand
     */
    protected function cliCommand(): CollectionBuilder
    {
        return $this->taskExecCommand('platform');
    }
}
