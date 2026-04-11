<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:run
        {command : The artisan command to run}
        {--tenant= : Tenant ID or slug to run the command for}
        {--all : Run the command for all active tenants}
        {--arguments= : Additional arguments as JSON string}
        {--force : Bypass command allowlist check (requires super admin confirmation)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run an artisan command within a tenant context';

    /**
     * Allowed commands that can be run via tenant:run.
     *
     * This allowlist prevents execution of dangerous commands
     * (like migrate:fresh, db:seed, cache:clear, etc.) that could
     *破坏 data or affect all tenants.
     *
     * @var array<string>
     */
    protected array $allowedCommands = [
        // Safe read-only commands
        'list',
        'route:list',
        'about',

        // Tenant-specific data commands (safe)
        'config:clear',
        'view:clear',
        'route:clear',
        'optimize:clear',
        'optimize',

        // Queue management (read-only / retry operations)
        'queue:failed',
        'queue:retry',
        'queue:flush',
        'queue:prune-failed',
        'queue:prune-batches',

        // Storage commands
        'storage:link',
    ];

    /**
     * Commands that are never allowed regardless of --force flag.
     *
     * @var array<string>
     */
    protected array $blockedCommands = [
        // Destructive database commands
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:seed',
        'db:wipe',

        // Commands that affect global state (not tenant-specific)
        'cache:clear',
        'queue:work',
        'queue:listen',
        'queue:restart',

        // Security-sensitive commands
        'key:generate',
        'passport:install',
        'sanctum:prune-expired',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $commandToRun = $this->argument('command');
        $tenantOption = $this->option('tenant');
        $allTenants = $this->option('all');
        $arguments = $this->option('arguments');
        $force = $this->option('force');

        // Extract the base command (without arguments/options)
        $baseCommand = explode(' ', $commandToRun)[0];

        // Check if command is in the blocked list (never allowed)
        if (in_array($baseCommand, $this->blockedCommands, true)) {
            $this->error("Command '{$baseCommand}' is blocked for safety reasons. It cannot be run via tenant:run.");

            return Command::FAILURE;
        }

        // Check if command is in the allowlist (or --force is used)
        if (! $force && ! in_array($baseCommand, $this->allowedCommands, true)) {
            $this->error("Command '{$baseCommand}' is not in the allowed list.");
            $this->line('Allowed commands: '.implode(', ', $this->allowedCommands));
            $this->line('Use --force to bypass the allowlist (only for trusted administrators).');

            return Command::FAILURE;
        }

        $parsedArguments = [];

        if ($arguments !== null) {
            $parsedArguments = json_decode($arguments, true) ?? [];
        }

        if ($allTenants) {
            return $this->runForAllTenants($commandToRun, $parsedArguments);
        }

        if ($tenantOption === null) {
            $this->error('You must specify --tenant=<id|slug> or use --all.');

            return Command::FAILURE;
        }

        // Find the tenant
        $tenant = is_numeric($tenantOption)
            ? Tenant::find($tenantOption)
            : Tenant::where('slug', $tenantOption)->first();

        if ($tenant === null) {
            $this->error("Tenant not found: {$tenantOption}");

            return Command::FAILURE;
        }

        return $this->runForTenant($tenant, $commandToRun, $parsedArguments);
    }

    /**
     * Run the command for a specific tenant.
     */
    protected function runForTenant(Tenant $tenant, string $command, array $arguments): int
    {
        $this->info("Running command '{$command}' for tenant: {$tenant->name} (ID: {$tenant->id})");

        // Set the tenant context
        Tenant::setCurrent($tenant);

        try {
            $exitCode = Artisan::call($command, $arguments);

            $output = Artisan::output();

            if ($output) {
                $this->line($output);
            }

            if ($exitCode === 0) {
                $this->info("Command completed successfully for tenant: {$tenant->name}");
            } else {
                $this->error("Command failed for tenant: {$tenant->name} (exit code: {$exitCode})");
            }

            return $exitCode;
        } finally {
            Tenant::clearCurrent();
        }
    }

    /**
     * Run the command for all active tenants.
     */
    protected function runForAllTenants(string $command, array $arguments): int
    {
        $tenants = Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return Command::SUCCESS;
        }

        $this->info("Running command '{$command}' for {$tenants->count()} active tenants");

        $successCount = 0;
        $failCount = 0;

        foreach ($tenants as $tenant) {
            Tenant::setCurrent($tenant);

            try {
                $exitCode = Artisan::call($command, $arguments);

                if ($exitCode === 0) {
                    $this->info("  ✓ {$tenant->name} (ID: {$tenant->id})");
                    $successCount++;
                } else {
                    $this->error("  ✗ {$tenant->name} (ID: {$tenant->id}) - exit code: {$exitCode}");
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ {$tenant->name} (ID: {$tenant->id}) - {$e->getMessage()}");
                $failCount++;
            } finally {
                Tenant::clearCurrent();
            }
        }

        $this->newLine();
        $this->info("Completed: {$successCount} succeeded, {$failCount} failed");

        return $failCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
