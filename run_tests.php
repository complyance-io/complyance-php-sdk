<?php
/**
 * Test runner script for Complyance PHP SDK
 * 
 * This script runs the test suite and generates code coverage reports.
 * It also provides a summary of test results and code coverage metrics.
 */

// Check if XDEBUG is enabled
if (!extension_loaded('xdebug')) {
    echo "Warning: Xdebug extension is not loaded. Code coverage will not be generated.\n";
    echo "To enable code coverage, install Xdebug and configure it in your php.ini.\n\n";
}

// Define colors for console output
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");
define('COLOR_BOLD', "\033[1m");

// Parse command line arguments
$options = getopt('s:f:c', ['suite:', 'filter:', 'coverage']);
$suite = $options['s'] ?? $options['suite'] ?? 'All';
$filter = $options['f'] ?? $options['filter'] ?? null;
$coverage = isset($options['c']) || isset($options['coverage']);

// Build the PHPUnit command
$command = './vendor/bin/phpunit';

// Add testsuite option if specified
if ($suite) {
    $command .= ' --testsuite ' . escapeshellarg($suite);
}

// Add filter option if specified
if ($filter) {
    $command .= ' --filter ' . escapeshellarg($filter);
}

// Add coverage options if requested
if ($coverage) {
    $command .= ' --coverage-html coverage/html';
    $command .= ' --coverage-clover coverage/clover.xml';
    $command .= ' --coverage-text';
}

// Print header
echo COLOR_BOLD . COLOR_BLUE . "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║                Complyance PHP SDK Test Runner                 ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n" . COLOR_RESET . "\n";

// Print configuration
echo COLOR_BOLD . "Configuration:" . COLOR_RESET . "\n";
echo "  Test Suite: " . COLOR_BLUE . $suite . COLOR_RESET . "\n";
if ($filter) {
    echo "  Filter: " . COLOR_BLUE . $filter . COLOR_RESET . "\n";
}
echo "  Coverage: " . ($coverage ? COLOR_GREEN . "Enabled" : COLOR_YELLOW . "Disabled") . COLOR_RESET . "\n\n";

// Run the tests
echo COLOR_BOLD . "Running tests..." . COLOR_RESET . "\n\n";
$startTime = microtime(true);
passthru($command, $returnCode);
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

// Print footer with execution time
echo "\n" . COLOR_BOLD . "Execution completed in " . COLOR_BLUE . $executionTime . COLOR_RESET . COLOR_BOLD . " seconds." . COLOR_RESET . "\n";

// Print coverage report location if coverage was generated
if ($coverage) {
    echo "\n" . COLOR_BOLD . "Coverage reports:" . COLOR_RESET . "\n";
    echo "  HTML: " . COLOR_BLUE . "coverage/html/index.html" . COLOR_RESET . "\n";
    echo "  XML: " . COLOR_BLUE . "coverage/clover.xml" . COLOR_RESET . "\n\n";
    
    // Parse clover.xml to get coverage metrics if it exists
    if (file_exists('coverage/clover.xml')) {
        $xml = simplexml_load_file('coverage/clover.xml');
        if ($xml) {
            $metrics = $xml->project->metrics;
            $statements = (int)$metrics['statements'];
            $coveredStatements = (int)$metrics['coveredstatements'];
            $methods = (int)$metrics['methods'];
            $coveredMethods = (int)$metrics['coveredmethods'];
            $elements = (int)$metrics['elements'];
            $coveredElements = (int)$metrics['coveredelements'];
            
            $statementCoverage = $statements > 0 ? round(($coveredStatements / $statements) * 100, 2) : 0;
            $methodCoverage = $methods > 0 ? round(($coveredMethods / $methods) * 100, 2) : 0;
            $elementCoverage = $elements > 0 ? round(($coveredElements / $elements) * 100, 2) : 0;
            
            echo COLOR_BOLD . "Coverage summary:" . COLOR_RESET . "\n";
            echo "  Statements: " . formatCoverageMetric($statementCoverage) . "% (" . $coveredStatements . "/" . $statements . ")\n";
            echo "  Methods: " . formatCoverageMetric($methodCoverage) . "% (" . $coveredMethods . "/" . $methods . ")\n";
            echo "  Elements: " . formatCoverageMetric($elementCoverage) . "% (" . $coveredElements . "/" . $elements . ")\n\n";
        }
    }
}

// Exit with the PHPUnit return code
exit($returnCode);

/**
 * Format a coverage metric with color coding
 *
 * @param float $percentage Coverage percentage
 * @return string Formatted coverage string
 */
function formatCoverageMetric($percentage) {
    if ($percentage >= 90) {
        return COLOR_GREEN . $percentage . COLOR_RESET;
    } elseif ($percentage >= 75) {
        return COLOR_YELLOW . $percentage . COLOR_RESET;
    } else {
        return COLOR_RED . $percentage . COLOR_RESET;
    }
}