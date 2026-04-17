<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Reporter;

use Ylab\PhpMigrater\Analyzer\Severity;

final class HtmlReporter implements ReporterInterface
{
    public function getName(): string
    {
        return 'html';
    }

    public function render(MigrationReport $report): string
    {
        $source = htmlspecialchars($report->getSourceVersion()->value);
        $target = htmlspecialchars($report->getTargetVersion()->value);
        $duration = round($report->getDuration(), 2);
        $severities = $report->getIssueSeverityCounts();
        $categories = $report->getIssueCategoryCounts();

        $fileRows = '';
        foreach ($report->getIssuesByFile() as $file => $issues) {
            if (empty($issues)) {
                continue;
            }
            $escapedFile = htmlspecialchars($file);
            $issueRows = '';
            foreach ($issues as $issue) {
                $severityClass = match ($issue->severity) {
                    Severity::Error => 'error',
                    Severity::Warning => 'warning',
                    Severity::Info => 'info',
                };
                $msg = htmlspecialchars($issue->message);
                $cat = htmlspecialchars($issue->category->value);
                $issueRows .= <<<HTML
                <tr class="{$severityClass}">
                    <td>{$issue->line}</td>
                    <td>{$issue->severity->value}</td>
                    <td>{$msg}</td>
                    <td>{$cat}</td>
                </tr>
HTML;
            }

            $count = count($issues);
            $fileRows .= <<<HTML
            <details class="file-section">
                <summary>{$escapedFile} ({$count} issues)</summary>
                <table class="issues-table">
                    <thead><tr><th>Line</th><th>Severity</th><th>Message</th><th>Category</th></tr></thead>
                    <tbody>{$issueRows}</tbody>
                </table>
            </details>
HTML;
        }

        $categoryRows = '';
        foreach ($categories as $cat => $count) {
            $catEsc = htmlspecialchars($cat);
            $categoryRows .= "<tr><td>{$catEsc}</td><td>{$count}</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHP Migration Report: {$source} → {$target}</title>
<style>
  :root { --bg: #1e1e2e; --surface: #2a2a3e; --text: #cdd6f4; --accent: #89b4fa; --error: #f38ba8; --warning: #fab387; --info: #89dceb; --border: #45475a; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); padding: 2rem; line-height: 1.6; }
  h1, h2 { color: var(--accent); }
  h1 { font-size: 1.8rem; margin-bottom: 0.5rem; }
  h2 { font-size: 1.3rem; margin: 1.5rem 0 0.5rem; }
  .meta { color: #a6adc8; margin-bottom: 1.5rem; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin: 1rem 0; }
  .card { background: var(--surface); border-radius: 8px; padding: 1rem; border: 1px solid var(--border); }
  .card .label { font-size: 0.85rem; color: #a6adc8; }
  .card .value { font-size: 1.8rem; font-weight: bold; }
  .card .value.error { color: var(--error); }
  .card .value.warning { color: var(--warning); }
  .card .value.info { color: var(--info); }
  table { width: 100%; border-collapse: collapse; margin: 0.5rem 0; }
  th, td { padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid var(--border); }
  th { background: var(--surface); color: var(--accent); font-size: 0.85rem; text-transform: uppercase; }
  tr.error td:nth-child(2) { color: var(--error); }
  tr.warning td:nth-child(2) { color: var(--warning); }
  tr.info td:nth-child(2) { color: var(--info); }
  .file-section { margin: 0.75rem 0; background: var(--surface); border-radius: 8px; border: 1px solid var(--border); }
  .file-section summary { padding: 0.75rem 1rem; cursor: pointer; font-weight: 500; }
  .file-section summary:hover { color: var(--accent); }
  .issues-table { margin: 0; }
</style>
</head>
<body>
<h1>PHP Migration Report</h1>
<p class="meta">PHP {$source} → PHP {$target} &bull; {$duration}s</p>

<div class="grid">
  <div class="card"><div class="label">Files Analyzed</div><div class="value">{$report->getFilesAnalyzed()}</div></div>
  <div class="card"><div class="label">Files With Issues</div><div class="value">{$report->getFilesWithIssues()}</div></div>
  <div class="card"><div class="label">Total Issues</div><div class="value">{$report->getTotalIssueCount()}</div></div>
  <div class="card"><div class="label">Files Fixed</div><div class="value">{$report->getFilesFixed()}</div></div>
  <div class="card"><div class="label">Tests Generated</div><div class="value">{$report->getTestsGenerated()}</div></div>
</div>

<h2>Severity Breakdown</h2>
<div class="grid">
  <div class="card"><div class="label">Errors</div><div class="value error">{$severities['error']}</div></div>
  <div class="card"><div class="label">Warnings</div><div class="value warning">{$severities['warning']}</div></div>
  <div class="card"><div class="label">Info</div><div class="value info">{$severities['info']}</div></div>
</div>

<h2>Categories</h2>
<table><thead><tr><th>Category</th><th>Count</th></tr></thead><tbody>{$categoryRows}</tbody></table>

<h2>Files</h2>
{$fileRows}

</body>
</html>
HTML;
    }
}
