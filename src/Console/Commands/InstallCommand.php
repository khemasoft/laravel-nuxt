<?php

namespace Khemasoft\LaravelNuxt\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nuxt:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new nuxt project or setup integration of an existing one';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->addArgument(
            'source',
            InputArgument::OPTIONAL,
            'Root folder of the nuxt application',
            'resources/nuxt'
        );

        $this->addOption('yarn', 'y', InputOption::VALUE_NONE, 'Use yarn package manager');
        $this->addOption('typescript', 't', InputOption::VALUE_NONE, 'Use typescript runtime');
        $this->addOption('cache', 'c', InputOption::VALUE_OPTIONAL, 'Optional caching endpoint (e.g. /api/cache)');
        $this->addOption(
            'prefix',
            'p',
            InputOption::VALUE_OPTIONAL,
            "Prefix for the nuxt application (will use value from `config('nuxt.prefix')` if omitted)"
        );

        $this->addOption('no-export', null, InputOption::VALUE_NONE, 'Do not export env variable on build');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $source = base_path($this->argument('source'));
        $yarn = $this->option('yarn') ?: $this->confirm('Use yarn package manager?');
        $typescript = $this->option('typescript') ?: $this->confirm('Use typescript runtime?');
        $prefixConfig = trim(config('nuxt.prefix'), '/');
        $prefix = trim($this->option('prefix') ?: $prefixConfig, '/');

        $this->installNuxtRemote($source, $yarn);
        $this->installNuxtLocal($source, $prefix, $yarn, $typescript);
        $this->updatePackageJson($typescript);

        $this->output->success("Nuxt integration for source '$source' set up successfully");

        if ($prefixConfig !== $prefix) {
            $this->output->warning("Set up with custom prefix: '$prefix'
Current setting: '$prefixConfig'
Please make sure to publish configuration and to adjust the 'prefix' setting accordingly.");
            $this->output->write('Publish command: ');
            $this->comment('php artisan vendor:publish --provider="M2S\\LaravelNuxt\\LaravelNuxtServiceProvider"');
        }
    }

    protected function installNuxtRemote(string $source, bool $yarn)
    {
        if (!file_exists($source)) {
            mkdir($source, 0777, true);
        }

        if (2 === count(scandir($source))) {
            passthru(
                ($yarn ? 'yarn create ' : 'npx create-').'nuxt-app '.$source
            );
        }

        passthru(
            (
                $yarn
                    ? 'yarn add --dev --cwd'
                    : 'npm i -D --prefix'
            ).
            " $source ".
            'nuxt-laravel @nuxtjs/axios'.
            ($this->option('cache') ? ' @nuxtjs/pwa' : '')
        );
    }

    protected function installNuxtLocal(string $source, string $prefix, bool $yarn, bool $typescript)
    {
        $configFile = base_path('nuxt.config.'.($typescript ? 'ts' : 'js'));

        passthru(
            (
                $yarn
                    ? 'yarn add --dev '
                    : 'npm i -D '
            ).
            'nuxt'.
            ($typescript ? ' @nuxt/typescript-runtime @nuxt/types' : '')
        );

        $config = view(
            'nuxt::config',
            [
                'source'     => $source,
                'prefix'     => $prefix,
                'typescript' => $typescript,
                'cache'      => trim($this->option('cache'), '/'),
                'export'     => !$this->option('no-export'),
            ]
        )->render();

        file_put_contents($configFile, $config);

        if ($typescript) {
            $tsconfigFile = base_path('tsconfig.json');

            if (file_exists($tsconfigFile)) {
                $this->output->warning("'tsconfig.json' file exists writing to 'tsconfig.nuxt.json'
Please compare the files and configure typescript appropriately.");

                $tsconfigFile = base_path('tsconfig.nuxt.json');
            }

            $tsconfig = view(
                'nuxt::tsconfig',
                [
                    'source' => $source,
                ]
            )->render();

            file_put_contents($tsconfigFile, $tsconfig);
        }
    }

    protected function updatePackageJson(bool $typescript)
    {
        $packageFile = base_path('package.json');

        $package = json_decode(file_get_contents($packageFile), true);
        $nuxt = $typescript ? 'nuxt-ts' : 'nuxt';

        $scripts = [
            'dev'      => $nuxt,
            'build'    => $nuxt.' build',
            'generate' => $nuxt.' generate',
            'start'    => $nuxt.' start',
        ];

        if (!isset($package['scripts'])) {
            $package['scripts'] = [];
        }

        foreach ($scripts as $key => $script) {
            $unique = $key;
            while (isset($package['scripts']['nuxt:'.$unique])) {
                $unique .= $key.'_'.Str::random(4);
            }

            $package['scripts']['nuxt:'.$unique] = $script;
        }

        file_put_contents($packageFile, json_encode($package, JSON_PRETTY_PRINT));
    }
}
