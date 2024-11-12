<?php
// app/Console/Commands/GenerateApplicationToken.php
namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateApplicationToken extends Command
{
    protected $signature = 'application:token {name} {description?}';
    protected $description = 'Generate a new application API token';

    public function handle()
    {
        $application = Application::create([
            'name' => $this->argument('name'),
            'description' => $this->argument('description'),
            'api_key' => Str::random(32),
            'status' => Application::STATUS_ACTIVE
        ]);

        $token = $application->createToken(
            $this->argument('name'),
        );

        $this->info('Application created successfully!');
        $this->info('API Key: ' . $application->api_key);
        $this->info('Access Token: ' . $token->plainTextToken);

        return Command::SUCCESS;
    }
}
