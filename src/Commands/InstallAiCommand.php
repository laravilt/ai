<?php

declare(strict_types=1);

namespace Laravilt\AI\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallAiCommand extends Command
{
    protected $signature = 'laravilt:ai:install
                            {--force : Overwrite existing files}
                            {--without-migrations : Skip running migrations}';

    protected $description = 'Install Laravilt AI plugin';

    public function handle(): int
    {
        $this->info('Installing Laravilt AI plugin...');
        $this->newLine();

        $this->publishConfig();

        if (! $this->option('without-migrations')) {
            $this->runMigrations();
        }

        $this->newLine();
        $this->info('✅ Laravilt AI plugin installed successfully!');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $this->info('Publishing configuration...');

        $params = ['--tag' => 'laravilt-ai-config'];

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        Artisan::call('vendor:publish', $params, $this->output);
    }

    protected function runMigrations(): void
    {
        $this->info('Running migrations...');

        $this->call('migrate');

        $this->components->success('Migrations ran successfully!');
    }
}
