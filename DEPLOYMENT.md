# Academia World - Development Setup & Worker Management

## Quick Start

### Method 1: Using the Startup Script (Recommended for Development)

The easiest way to start the application with all necessary workers:

```bash
# Start everything (API server + queue workers)
./start-dev.sh start

# Check status
./start-dev.sh status

# View live logs
./start-dev.sh logs

# Stop everything
./start-dev.sh stop

# Restart everything
./start-dev.sh restart

# Restart only workers
./start-dev.sh workers
```

### Method 2: Using npm Scripts

```bash
# Start API server + workers + frontend dev server
npm run start

# Start only API server + workers
npm run start:api

# Start only workers
npm run start:workers

# Check status
npm run status

# View logs
npm run logs

# Stop everything
npm run stop
```

### Method 3: Using Laravel Artisan Commands

```bash
# Start workers
php artisan workers:start

# Stop workers
php artisan workers:start --stop

# Restart workers
php artisan workers:start --restart

# Check worker status
php artisan workers:start --status

# Start development server separately
php artisan serve --host=0.0.0.0 --port=8000
```

### Method 4: Manual Process Management

```bash
# Start queue workers manually
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --memory=512 &

# Start development server
php artisan serve &
```

## Production Deployment

### Option 1: Using Supervisor (Recommended)

1. Install Supervisor:
```bash
sudo apt update
sudo apt install supervisor
```

2. Copy the supervisor configuration:
```bash
sudo cp supervisor.conf /etc/supervisor/conf.d/academia-world.conf
```

3. Update supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start academia-world-worker:*
sudo supervisorctl start academia-world-scheduler
```

4. Check status:
```bash
sudo supervisorctl status
```

### Option 2: Using systemd

1. Copy the service file:
```bash
sudo cp academia-world-workers.service /etc/systemd/system/
```

2. Enable and start the service:
```bash
sudo systemctl daemon-reload
sudo systemctl enable academia-world-workers
sudo systemctl start academia-world-workers
```

3. Check status:
```bash
sudo systemctl status academia-world-workers
```

### Option 3: Using Process Monitor

For basic monitoring without supervisor:

```bash
# Start the monitor in background
nohup ./monitor.sh > /dev/null 2>&1 &
```

## Monitoring & Logs

### Log Locations

- **Queue Workers**: `storage/logs/queue-worker-*.log`
- **Development Server**: `storage/logs/server.log`
- **Laravel Application**: `storage/logs/laravel.log`
- **Process Monitor**: `storage/logs/monitor.log`

### Monitoring Commands

```bash
# Check queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# View active jobs
php artisan horizon:status  # If using Horizon

# Check system processes
ps aux | grep "queue:work"
ps aux | grep "php artisan serve"
```

### Real-time Log Monitoring

```bash
# All logs
tail -f storage/logs/*.log

# Only worker logs
tail -f storage/logs/queue-worker-*.log

# Only application logs
tail -f storage/logs/laravel.log

# Using the startup script
./start-dev.sh logs
```

## Configuration

### Queue Configuration

The application uses database queues by default. Configuration in `.env`:

```env
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=jobs
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=90
```

### Worker Configuration

Workers are configured with these defaults:
- **Sleep**: 3 seconds between jobs
- **Tries**: 3 attempts per job
- **Max Time**: 3600 seconds (1 hour)
- **Memory**: 512MB limit
- **Number of Workers**: 2 (for redundancy)

### Email Queue Configuration

Email verification uses queued jobs:
- **Queue**: `database`
- **Connection**: Configured in `config/mail.php`
- **Processing**: Automatic with queue workers

## Troubleshooting

### Workers Not Processing Jobs

1. Check if workers are running:
```bash
./start-dev.sh status
```

2. Restart workers:
```bash
./start-dev.sh workers
```

3. Check for failed jobs:
```bash
php artisan queue:failed
```

4. Clear failed jobs and restart:
```bash
php artisan queue:flush
./start-dev.sh restart
```

### Email Not Sending

1. Check mail configuration in `.env`
2. Verify queue workers are running
3. Check worker logs for errors
4. Test mail configuration:
```bash
php artisan tinker
Mail::raw('Test email', function($message) { $message->to('test@example.com')->subject('Test'); });
```

### Performance Issues

1. Check worker memory usage:
```bash
ps aux | grep "queue:work"
```

2. Increase worker memory limit in supervisor.conf or startup script
3. Add more workers by modifying the configuration files

### Permission Issues

Ensure proper file permissions:
```bash
chmod +x start-dev.sh
chmod +x monitor.sh
chown -R www-data:www-data storage/
chmod -R 775 storage/
```

## Development vs Production

### Development Mode
- Uses `./start-dev.sh` for easy management
- Workers run in background with logging
- Automatic restart on failure
- Development server on port 8000

### Production Mode
- Uses Supervisor or systemd for process management
- Proper logging and monitoring
- Automatic startup on system boot
- Web server (nginx/apache) configuration required

Choose the appropriate method based on your environment and requirements.
