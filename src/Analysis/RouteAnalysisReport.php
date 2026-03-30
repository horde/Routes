<?php
/**
 * Horde Routes package
 *
 * @author  Ralf Lang <ralf.lang@ralf-lang.de>
 * @license http://www.horde.org/licenses/bsd BSD
 * @package Routes
 */

declare(strict_types=1);

namespace Horde\Routes\Analysis;

/**
 * Route analysis report formatter
 *
 * Formats route analysis warnings into human-readable text or machine-readable JSON.
 * Used to present RouteAnalyzer results to developers or tooling.
 *
 * Usage:
 * <code>
 * $analyzer = new RouteAnalyzer($mapper);
 * $warnings = $analyzer->analyze();
 *
 * $report = new RouteAnalysisReport($warnings);
 * echo $report->formatText();  // Human-readable
 *
 * // Or for tooling
 * $json = $report->formatJson();  // Machine-readable
 * </code>
 *
 * @package Routes
 */
class RouteAnalysisReport
{
    /**
     * Array of warning objects
     *
     * @var array<array<string, mixed>>
     */
    private array $warnings;

    /**
     * Create a new report
     *
     * @param array<array<string, mixed>> $warnings Array of warning objects from RouteAnalyzer
     */
    public function __construct(array $warnings)
    {
        $this->warnings = $warnings;
    }

    /**
     * Format warnings as human-readable text
     *
     * Returns formatted text with ANSI colors for terminal output.
     * Includes sections for different warning types and severity indicators.
     *
     * @return string Formatted text report
     */
    public function formatText(): string
    {
        if (empty($this->warnings)) {
            return "\n✅ \033[32mNo issues found\033[0m - All routes appear to be correctly configured.\n";
        }

        $output = "\n\033[1m📋 Route Analysis Report\033[0m\n";
        $output .= str_repeat("=", 60) . "\n\n";

        // Group warnings by type
        $grouped = [];
        foreach ($this->warnings as $warning) {
            $type = $warning['type'] ?? 'unknown';
            $grouped[$type][] = $warning;
        }

        // Display each type
        foreach ($grouped as $type => $warnings) {
            $output .= $this->formatWarningSection($type, $warnings);
        }

        // Summary
        $output .= "\n" . str_repeat("=", 60) . "\n";
        $output .= sprintf("\n\033[1mTotal Issues:\033[0m %d\n", count($this->warnings));

        // Count by severity
        $bySeverity = [];
        foreach ($this->warnings as $warning) {
            $severity = $warning['severity'] ?? 'warning';
            $bySeverity[$severity] = ($bySeverity[$severity] ?? 0) + 1;
        }

        foreach ($bySeverity as $severity => $count) {
            $color = $severity === 'error' ? '31' : '33';
            $output .= sprintf("  \033[{$color}m%s:\033[0m %d\n", ucfirst($severity), $count);
        }

        $output .= "\n";

        return $output;
    }

    /**
     * Format a section of warnings by type
     *
     * @param string $type Warning type
     * @param array<array<string, mixed>> $warnings Warnings of this type
     * @return string Formatted section
     */
    private function formatWarningSection(string $type, array $warnings): string
    {
        $output = "\033[1m" . ucfirst(str_replace('_', ' ', $type)) . " Issues:\033[0m\n";
        $output .= str_repeat("-", 60) . "\n";

        foreach ($warnings as $warning) {
            $output .= $this->formatWarning($warning);
        }

        $output .= "\n";
        return $output;
    }

    /**
     * Format a single warning
     *
     * @param array<string, mixed> $warning Warning object
     * @return string Formatted warning
     */
    private function formatWarning(array $warning): string
    {
        $type = $warning['type'] ?? 'unknown';
        $severity = $warning['severity'] ?? 'warning';
        $severityColor = $severity === 'error' ? '31' : '33';
        $severityIcon = $severity === 'error' ? '❌' : '⚠️';

        $output = "\n{$severityIcon} \033[{$severityColor}m" . strtoupper($severity) . "\033[0m\n";
        $output .= "   " . ($warning['message'] ?? 'Unknown issue') . "\n";

        // Type-specific details
        switch ($type) {
            case 'shadowed':
                $output .= "\n";
                $output .= "   \033[1mShadowed route:\033[0m  " . ($warning['shadowed_route'] ?? 'unknown') . "\n";
                $output .= "   \033[1mShadowing route:\033[0m " . ($warning['shadowing_route'] ?? 'unknown') . "\n";
                if (isset($warning['test_url'])) {
                    $output .= "   \033[1mTest URL:\033[0m        " . $warning['test_url'] . "\n";
                }
                if (isset($warning['matched_route'])) {
                    $output .= "   \033[1mActually matched:\033[0m " . $warning['matched_route'] . "\n";
                }
                break;

            case 'invalid_requirement':
                $output .= "\n";
                $output .= "   \033[1mRoute:\033[0m      " . ($warning['route'] ?? 'unknown') . "\n";
                $output .= "   \033[1mParameter:\033[0m  " . ($warning['parameter'] ?? 'unknown') . "\n";
                $output .= "   \033[1mPattern:\033[0m    " . ($warning['pattern'] ?? 'unknown') . "\n";
                if (isset($warning['error'])) {
                    $output .= "   \033[1mError:\033[0m      " . $warning['error'] . "\n";
                }
                break;

            case 'duplicate':
                $output .= "\n";
                $output .= "   \033[1mRoute:\033[0m " . ($warning['route'] ?? 'unknown') . "\n";
                break;

            default:
                // Generic warning format
                foreach ($warning as $key => $value) {
                    if (!in_array($key, ['type', 'message', 'severity']) && is_scalar($value)) {
                        $output .= "   \033[1m" . ucfirst(str_replace('_', ' ', $key)) . ":\033[0m " . $value . "\n";
                    }
                }
        }

        return $output;
    }

    /**
     * Format warnings as JSON
     *
     * Returns machine-readable JSON with all warning details.
     * Suitable for CI/CD integration and tooling.
     *
     * @return string JSON-encoded report
     */
    public function formatJson(): string
    {
        $report = [
            'warnings' => $this->serializeWarnings(),
            'summary' => $this->generateSummary()
        ];

        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Serialize warnings for JSON output
     *
     * Excludes internal/debug fields and Route objects.
     *
     * @return array<array<string, mixed>> Serialized warnings
     */
    private function serializeWarnings(): array
    {
        $serialized = [];

        foreach ($this->warnings as $warning) {
            $clean = [];

            // Only include public fields (not internal/debug/object fields)
            foreach ($warning as $key => $value) {
                // Skip Route objects and internal debug data
                if (is_object($value) || $key === 'route_object' || $key === 'internal_data') {
                    continue;
                }

                $clean[$key] = $value;
            }

            $serialized[] = $clean;
        }

        return $serialized;
    }

    /**
     * Generate summary statistics
     *
     * @return array<string, mixed> Summary data
     */
    private function generateSummary(): array
    {
        $summary = [
            'total' => count($this->warnings),
            'by_type' => [],
            'by_severity' => []
        ];

        foreach ($this->warnings as $warning) {
            $type = $warning['type'] ?? 'unknown';
            $severity = $warning['severity'] ?? 'warning';

            $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + 1;
            $summary['by_severity'][$severity] = ($summary['by_severity'][$severity] ?? 0) + 1;
        }

        return $summary;
    }
}
