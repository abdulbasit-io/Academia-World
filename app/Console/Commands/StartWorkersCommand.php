<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class StartWorkersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'workers:start {--stop : Stop all workers} {--restart : Restart all workers} {--status : Check worker status}';

    /**
     * The console command description.
     */
    protected $description = 'Start, stop, restart or check status of queue workers and schedulers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('stop')) {
            return $this->stopWorkers();
        }

        if ($this->option('restart')) {
            $this->stopWorkers();
            sleep(2);
            return $this->startWorkers();
        }

        if ($this->option('status')) {
            return $this->checkStatus();
        }

        return $this->startWorkers();
    }

    /**
     * Start queue workers
     */
    private function startWorkers(): int
    {
        $this->info('Starting Academia World workers...');

        // Check if supervisor is available
        if ($this->isSupervisorAvailable()) {
            $this->info('Using Supervisor for process management...');
            return $this->startWithSupervisor();
        }

        // Fallback to manual process management
        $this->info('Starting workers manually...');
        return $this->startManually();
    }

    /**
     * Stop all workers
     */
    private function stopWorkers(): int
    {
        $this->info('Stopping all workers...');

        if ($this->isSupervisorAvailable()) {
            $this->stopWithSupervisor();
        } else {
            $this->stopManually();
        }

        $this->info('All workers stopped.');
        return 0;
    }

    /**
     * Check if supervisor is available
     */
    private function isSupervisorAvailable(): bool
    {
        $process = new Process(['which', 'supervisorctl']);
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Start workers with supervisor
     */
    private function startWithSupervisor(): int
    {
        $configPath = base_path('supervisor.conf');
        
        if (!file_exists($configPath)) {
            $this->error('Supervisor configuration not found at: ' . $configPath);
            return 1;
        }

        // Copy config to supervisor directory (you may need to adjust this path)
        $supervisorDir = '/etc/supervisor/conf.d/';
        $configName = 'academia-world.conf';

        if (is_dir($supervisorDir)) {
            $this->info('Copying supervisor configuration...');
            $copyCommand = "sudo cp {$configPath} {$supervisorDir}{$configName}";
            $this->line("Running: {$copyCommand}");
            exec($copyCommand, $output, $returnCode);

            if ($returnCode === 0) {
                // Reload supervisor configuration
                exec('sudo supervisorctl reread', $output, $returnCode);
                exec('sudo supervisorctl update', $output, $returnCode);
                exec('sudo supervisorctl start academia-world-worker:*', $output, $returnCode);
                exec('sudo supervisorctl start academia-world-scheduler', $output, $returnCode);

                $this->info('Workers started with Supervisor.');
                return 0;
            }
        }

        $this->warn('Could not use system supervisor, falling back to manual startup...');
        return $this->startManually();
    }

    /**
     * Start workers manually
     */
    private function startManually(): int
    {
        $this->info('Starting queue workers in background...');

        // Create log directory if it doesn't exist
        $logDir = storage_path('logs');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Start queue workers
        $workerCommand = sprintf(
            'cd %s && php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --memory=512 > %s/queue-worker.log 2>&1 &',
            base_path(),
            $logDir
        );

        exec($workerCommand, $output, $returnCode);

        // Start a second worker for redundancy
        exec($workerCommand, $output, $returnCode);

        // Create PID file for tracking
        $pidFile = storage_path('app/workers.pid');
        $processes = [];

        // Get the PIDs of started processes
        exec('pgrep -f "queue:work"', $processes);
        
        if (!empty($processes)) {
            file_put_contents($pidFile, implode("\n", $processes));
            $this->info('Started ' . count($processes) . ' queue workers.');
            $this->info('PIDs: ' . implode(', ', $processes));
        } else {
            $this->error('Failed to start queue workers.');
            return 1;
        }

        return 0;
    }

    /**
     * Stop workers with supervisor
     */
    private function stopWithSupervisor(): void
    {
        exec('sudo supervisorctl stop academia-world-worker:*');
        exec('sudo supervisorctl stop academia-world-scheduler');
    }

    /**
     * Stop workers manually
     */
    private function stopManually(): void
    {
        // Kill queue worker processes
        exec('pkill -f "queue:work"');
        
        // Remove PID file
        $pidFile = storage_path('app/workers.pid');
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
    }

    /**
     * Check worker status
     */
    private function checkStatus(): int
    {
        $this->info('Checking worker status...');

        if ($this->isSupervisorAvailable()) {
            $this->info('Supervisor status:');
            $process = new Process(['sudo', 'supervisorctl', 'status']);
            $process->run();
            $this->line($process->getOutput());
        }

        // Check for running queue workers
        $process = new Process(['pgrep', '-f', 'queue:work']);
        $process->run();

        if ($process->isSuccessful()) {
            $pids = trim($process->getOutput());
            if ($pids) {
                $pidArray = explode("\n", $pids);
                $this->info('Found ' . count($pidArray) . ' running queue workers.');
                $this->info('PIDs: ' . implode(', ', $pidArray));
            }
        } else {
            $this->warn('No queue workers found running.');
        }

        // Check queue status
        $this->info('Queue status:');
        Artisan::call('queue:monitor');
        $this->line(Artisan::output());

        return 0;
    }
}
