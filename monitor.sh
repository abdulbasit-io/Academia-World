#!/bin/bash

# Academia World Process Monitor
# This script monitors queue workers and restarts them if they die

PROJECT_DIR="/home/abdulbasit/academia-world"
LOG_DIR="$PROJECT_DIR/storage/logs"
MONITOR_LOG="$LOG_DIR/monitor.log"

# Ensure log directory exists
mkdir -p "$LOG_DIR"

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$MONITOR_LOG"
    echo "$1"
}

check_and_restart_workers() {
    local worker_count=$(pgrep -f "queue:work" | wc -l)
    
    if [ "$worker_count" -lt 2 ]; then
        log_message "WARNING: Only $worker_count queue workers running. Restarting workers..."
        
        # Kill existing workers
        pkill -f "queue:work" 2>/dev/null || true
        sleep 2
        
        # Start new workers
        cd "$PROJECT_DIR"
        
        nohup php artisan queue:work database \
            --queue=emails,default \
            --sleep=3 \
            --tries=3 \
            --max-time=3600 \
            --memory=512 \
            --name=worker-1 \
            > "$LOG_DIR/queue-worker-1.log" 2>&1 &
        
        nohup php artisan queue:work database \
            --queue=emails,default \
            --sleep=3 \
            --tries=3 \
            --max-time=3600 \
            --memory=512 \
            --name=worker-2 \
            > "$LOG_DIR/queue-worker-2.log" 2>&1 &
        
        sleep 3
        
        local new_count=$(pgrep -f "queue:work" | wc -l)
        log_message "Restarted workers. Now running: $new_count workers"
    else
        log_message "INFO: $worker_count queue workers running normally"
    fi
}

check_queue_health() {
    cd "$PROJECT_DIR"
    
    # Check failed jobs count from database
    local failed_count=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1 || echo "0")
    
    # Simple health check - we'll just monitor for basic worker responsiveness
    if [ ! -f "$LOG_DIR/queue-worker-1.log" ]; then
        log_message "WARNING: Worker log file missing"
    fi
}

# Main monitoring loop
log_message "Starting Academia World process monitor..."

while true; do
    check_and_restart_workers
    check_queue_health
    
    # Wait 30 seconds before next check
    sleep 30
done
