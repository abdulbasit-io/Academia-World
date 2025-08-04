#!/bin/bash

# Academia World Development Setup Script
# This script helps maintain IDE helper files and prevents read-only issues

echo "🚀 Academia World Development Setup"
echo "==================================="

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to handle file permissions
fix_permissions() {
    echo "📁 Fixing file permissions..."
    
    # Make storage and bootstrap/cache writable
    chmod -R 775 storage/
    chmod -R 775 bootstrap/cache/
    
    # Fix IDE helper files if they exist
    if [ -f "_ide_helper.php" ]; then
        chmod 664 _ide_helper.php
    fi
    
    if [ -f "_ide_helper_models.php" ]; then
        chmod 664 _ide_helper_models.php
    fi
    
    if [ -f ".phpstorm.meta.php" ]; then
        chmod 664 .phpstorm.meta.php
    fi
    
    echo "✅ File permissions fixed"
}

# Function to regenerate IDE helper files
regenerate_ide_helpers() {
    echo "🔧 Regenerating IDE helper files..."
    
    # Remove existing files to prevent conflicts
    rm -f _ide_helper.php
    rm -f _ide_helper_models.php
    rm -f .phpstorm.meta.php
    
    # Generate new helper files
    php artisan ide-helper:generate
    php artisan ide-helper:models --write --reset
    php artisan ide-helper:meta
    
    echo "✅ IDE helper files regenerated"
}

# Function to clear caches
clear_caches() {
    echo "🧹 Clearing application caches..."
    
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear
    
    echo "✅ Caches cleared"
}

# Function to optimize for development
optimize_dev() {
    echo "⚡ Optimizing for development..."
    
    # Install/update dependencies
    composer install
    
    # Generate application key if missing
    if ! grep -q "APP_KEY=" .env || [ -z "$(grep "APP_KEY=" .env | cut -d'=' -f2)" ]; then
        php artisan key:generate
    fi
    
    # Run migrations
    php artisan migrate --force
    
    echo "✅ Development optimization complete"
}

# Main execution
main() {
    # Check if we're in a Laravel project
    if [ ! -f "artisan" ]; then
        echo "❌ Error: This doesn't appear to be a Laravel project directory"
        exit 1
    fi
    
    # Check if composer is available
    if ! command_exists composer; then
        echo "❌ Error: Composer is not installed"
        exit 1
    fi
    
    # Check if PHP is available
    if ! command_exists php; then
        echo "❌ Error: PHP is not installed"
        exit 1
    fi
    
    # Parse command line arguments
    case "${1:-all}" in
        "permissions")
            fix_permissions
            ;;
        "ide")
            regenerate_ide_helpers
            ;;
        "cache")
            clear_caches
            ;;
        "optimize")
            optimize_dev
            ;;
        "all")
            fix_permissions
            clear_caches
            optimize_dev
            regenerate_ide_helpers
            ;;
        *)
            echo "Usage: $0 [permissions|ide|cache|optimize|all]"
            echo ""
            echo "Options:"
            echo "  permissions  - Fix file permissions"
            echo "  ide         - Regenerate IDE helper files"
            echo "  cache       - Clear Laravel caches"
            echo "  optimize    - Run development optimizations"
            echo "  all         - Run all tasks (default)"
            exit 1
            ;;
    esac
    
    echo ""
    echo "🎉 Development setup complete!"
    echo ""
    echo "💡 Tips to avoid read-only file issues:"
    echo "   - Run 'composer ide-helper' after model changes"
    echo "   - Add IDE helper files to .gitignore (already done)"
    echo "   - Use './scripts/dev-setup.sh ide' to regenerate helpers"
}

# Run main function with all arguments
main "$@"
