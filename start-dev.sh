#!/bin/bash

# Academia World Development Server Startup Script
# This script starts the Laravel development server along with all necessary background workers

set -e

PROJECT_DIR="/home/abdulbasit/academia-world"
LOG_DIR="$PROJECT_DIR/storage/logs"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[Academia World]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if a process is running
is_running() {
    pgrep -f "$1" > /dev/null 2>&1
}

# Function to stop existing workers
stop_existing_workers() {
    print_status "Stopping existing workers..."
    
    # Stop queue workers
    if is_running "queue:work"; then
        pkill -f "queue:work" || true
        print_status "Stopped existing queue workers"
    fi
    
    # Stop development server
    if is_running "php artisan serve"; then
        pkill -f "php artisan serve" || true
        print_status "Stopped existing development server"
    fi
    
    sleep 2
}

# Function to start queue workers
start_workers() {
    print_status "Starting queue workers..."
    
    # Ensure log directory exists
    mkdir -p "$LOG_DIR"
    
    # Start queue workers in background
    cd "$PROJECT_DIR"
    
    # Worker 1 - General queue processing
    nohup php artisan queue:work database \
        --queue=emails,default \
        --sleep=3 \
        --tries=3 \
        --max-time=3600 \
        --memory=512 \
        --name=worker-1 \
        > "$LOG_DIR/queue-worker-1.log" 2>&1 &
    
    # Worker 2 - General queue processing for redundancy
    nohup php artisan queue:work database \
        --queue=emails,default \
        --sleep=3 \
        --tries=3 \
        --max-time=3600 \
        --memory=512 \
        --name=worker-2 \
        > "$LOG_DIR/queue-worker-2.log" 2>&1 &
    
    # Worker 3 - Dedicated Telescope queue worker
    nohup php artisan queue:work database \
        --queue=telescope \
        --sleep=1 \
        --tries=3 \
        --max-time=3600 \
        --memory=256 \
        --name=telescope-worker \
        > "$LOG_DIR/telescope-worker.log" 2>&1 &
    
    sleep 2
    
    # Check if workers started successfully
    if is_running "queue:work"; then
        WORKER_COUNT=$(pgrep -f "queue:work" | wc -l)
        print_success "Started $WORKER_COUNT queue workers"
    else
        print_error "Failed to start queue workers"
        return 1
    fi
}

# Function to start development server
start_server() {
    print_status "Starting Laravel development server..."
    
    cd "$PROJECT_DIR"
    
    # Start the development server in background
    nohup php artisan serve --host=0.0.0.0 --port=8000 \
        > "$LOG_DIR/server.log" 2>&1 &
    
    sleep 3
    
    # Check if server started successfully
    if curl -s "http://localhost:8000" > /dev/null 2>&1; then
        print_success "Development server started at http://localhost:8000"
    else
        print_warning "Development server started but may not be fully ready yet"
        print_status "Check server logs: tail -f $LOG_DIR/server.log"
    fi
}

# Function to display status
show_status() {
    print_status "Current status:"
    
    echo
    echo "=== Queue Workers ==="
    if is_running "queue:work"; then
        WORKER_PIDS=$(pgrep -f "queue:work")
        echo "Running queue workers (PIDs: $WORKER_PIDS)"
        
        # Show worker details
        ps aux | grep "queue:work" | grep -v grep | while read line; do
            echo "  $line"
        done
    else
        echo "No queue workers running"
    fi
    
    echo
    echo "=== Development Server ==="
    if is_running "php artisan serve"; then
        SERVER_PID=$(pgrep -f "php artisan serve")
        echo "Development server running (PID: $SERVER_PID)"
        echo "Available at: http://localhost:8000"
    else
        echo "Development server not running"
    fi
    
    echo
    echo "=== Queue Status ==="
    cd "$PROJECT_DIR"
    # Check pending jobs count
    PENDING_JOBS=$(php artisan queue:monitor --once 2>/dev/null | grep -o "pending: [0-9]*" | grep -o "[0-9]*" 2>/dev/null || echo "0")
    FAILED_JOBS=$(php artisan queue:monitor --once 2>/dev/null | grep -o "failed: [0-9]*" | grep -o "[0-9]*" 2>/dev/null || echo "0")
    
    echo "Pending jobs: $PENDING_JOBS"
    echo "Failed jobs: $FAILED_JOBS"
    
    # Show recent job activity
    echo "Recent job activity:"
    if [ -f "$LOG_DIR/queue-worker-1.log" ]; then
        tail -n 3 "$LOG_DIR/queue-worker-1.log" | grep -E "(DONE|FAIL|RUNNING)" || echo "No recent activity"
    fi
    
    echo
    echo "=== Log Files ==="
    echo "Server logs: tail -f $LOG_DIR/server.log"
    echo "Worker logs: tail -f $LOG_DIR/queue-worker-*.log"
    echo "Application logs: tail -f $LOG_DIR/laravel.log"
}

# Main script logic
case "${1:-start}" in
    "start")
        print_status "Starting Academia World development environment..."
        stop_existing_workers
        start_workers
        start_server
        echo
        show_status
        echo
        print_success "Academia World is now running!"
        print_status "Use './start-dev.sh stop' to stop all services"
        print_status "Use './start-dev.sh status' to check status"
        print_status "Use './start-dev.sh logs' to view logs"
        ;;
    
    "stop")
        print_status "Stopping Academia World development environment..."
        stop_existing_workers
        print_success "All services stopped"
        ;;
    
    "restart")
        print_status "Restarting Academia World development environment..."
        stop_existing_workers
        start_workers
        start_server
        echo
        show_status
        echo
        print_success "Academia World restarted!"
        ;;
    
    "status")
        show_status
        ;;
    
    "logs")
        print_status "Showing live logs (Ctrl+C to exit)..."
        echo
        tail -f "$LOG_DIR"/*.log
        ;;
    
    "workers")
        print_status "Managing workers only..."
        stop_existing_workers
        start_workers
        print_success "Workers restarted!"
        ;;
    
    *)
        echo "Usage: $0 {start|stop|restart|status|logs|workers}"
        echo
        echo "Commands:"
        echo "  start    - Start development server and workers"
        echo "  stop     - Stop all services"
        echo "  restart  - Restart all services"
        echo "  status   - Show current status"
        echo "  logs     - Show live logs"
        echo "  workers  - Restart only workers"
        exit 1
        ;;
esac
