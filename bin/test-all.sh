#!/usr/bin/env bash

# Run all plugin unit tests across the NRFC WordPress project
# Usage: ./bin/test-all.sh [verbose|-v] [coverage|--coverage]

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PLUGIN_DIR="$PROJECT_ROOT/wordpress/wp-content/plugins"

VERBOSE=0
COVERAGE=0
COVERAGE_DIR="$PROJECT_ROOT/coverage/plugins"
FAILED=0
PASSED=0

usage() {
    cat <<'EOF'
Usage: ./bin/test-all.sh [options]

Options:
  -v, --verbose, verbose    Run tests in debug mode (PHPUnit output)
  --coverage, coverage      Generate per-plugin code coverage reports
  -h, --help                Show this help message
EOF
}

for arg in "$@"; do
    case "$arg" in
        -v|--verbose|verbose)
            VERBOSE=1
            ;;
        --coverage|coverage)
            COVERAGE=1
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $arg"
            usage
            exit 1
            ;;
    esac
done

echo "========================================"
echo "Running NRFC Plugin Test Suite"
echo "========================================"
echo ""

if [[ $COVERAGE -eq 1 ]]; then
    mkdir -p "$COVERAGE_DIR"
    echo "Coverage reports will be written to: $COVERAGE_DIR"
    echo ""
fi

# Test plugins that have test setup
TESTABLE_PLUGINS=(
    "nrfc-fixtures"
    "nrfc-match-reports"
    "nrfc-auto-links"
    "nrfc-person-directory"
    "nrfc-sponsor-management"
)

for plugin in "${TESTABLE_PLUGINS[@]}"; do
    plugin_path="$PLUGIN_DIR/$plugin"

    if [[ ! -d "$plugin_path" ]]; then
        echo "⊘ Skipping $plugin (directory not found)"
        continue
    fi

    if [[ ! -f "$plugin_path/composer.json" ]]; then
        echo "⊘ Skipping $plugin (no composer.json)"
        continue
    fi

    if [[ ! -f "$plugin_path/phpunit.xml.dist" ]]; then
        echo "⊘ Skipping $plugin (no phpunit.xml.dist)"
        continue
    fi

    echo "Running tests for: $plugin"
    echo "  Path: $plugin_path"

    cd "$plugin_path"

    # Install dependencies if vendor not present
    if [[ ! -d "$plugin_path/vendor" ]]; then
        echo "  Installing dependencies..."
        composer install --no-interaction --quiet
    fi

    # Run tests (optionally with per-plugin coverage output)
    test_args=()
    if [[ $VERBOSE -eq 1 ]]; then
        # PHPUnit 11 no longer supports -v; --debug is the closest equivalent.
        test_args+=("--debug")
    fi

    if [[ $COVERAGE -eq 1 ]]; then
        plugin_coverage_dir="$COVERAGE_DIR/$plugin"
        mkdir -p "$plugin_coverage_dir"

        # Restrict coverage to plugin implementation code.
        coverage_filter_dirs=()
        for candidate in src includes inc classes; do
            if [[ -d "$plugin_path/$candidate" ]]; then
                coverage_filter_dirs+=("$plugin_path/$candidate")
            fi
        done

        if [[ ${#coverage_filter_dirs[@]} -eq 0 ]]; then
            coverage_filter_dirs+=("$plugin_path")
        fi

        for coverage_dir in "${coverage_filter_dirs[@]}"; do
            test_args+=("--coverage-filter" "$coverage_dir")
        done

        test_args+=("--coverage-clover" "$plugin_coverage_dir/clover.xml")
        test_args+=("--coverage-html" "$plugin_coverage_dir/html")
    fi

    if [[ $COVERAGE -eq 1 ]]; then
        run_test_command=(env XDEBUG_MODE=coverage composer test -- "${test_args[@]}")
    else
        run_test_command=(composer test -- "${test_args[@]}")
    fi

    if "${run_test_command[@]}"; then
        echo "  ✓ PASSED"
        ((PASSED+=1))
    else
        echo "  ✗ FAILED"
        ((FAILED+=1))
    fi
    echo ""
done

echo "========================================"
echo "Test Summary"
echo "========================================"
echo "Passed: $PASSED"
echo "Failed: $FAILED"
echo ""

if [[ $COVERAGE -eq 1 ]]; then
    echo "Coverage output: $COVERAGE_DIR"
    echo ""
fi

if [[ $FAILED -gt 0 ]]; then
    echo "Some tests failed!"
    exit 1
else
    echo "All tests passed!"
    exit 0
fi

