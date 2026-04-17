<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Diff;

use Symfony\Component\Process\Process;

/**
 * Opens a side-by-side diff in the browser for review.
 * Starts a temporary local PHP server, serves the diff HTML, and waits for user action.
 */
final class BrowserRenderer
{
    private int $port;

    public function __construct(int $port = 0)
    {
        $this->port = $port ?: $this->findAvailablePort();
    }

    /**
     * Show diff in browser and wait for user decision.
     *
     * @return FixAction
     */
    public function renderAndWait(DiffResult $diff): FixAction
    {
        $tmpDir = sys_get_temp_dir() . '/php-migrater-diff-' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            $this->writeDiffPage($tmpDir, $diff);
            $this->writeRouterScript($tmpDir);

            $process = new Process(
                ['php', '-S', "127.0.0.1:{$this->port}", 'router.php'],
                $tmpDir,
            );
            $process->setTimeout(null);
            $process->start();

            // Give server time to start
            usleep(500000);

            $url = "http://127.0.0.1:{$this->port}/";
            $this->openBrowser($url);

            // Poll for decision file
            $decisionFile = $tmpDir . '/decision.txt';
            $timeout = 300; // 5 minutes
            $start = time();

            while (!file_exists($decisionFile) && (time() - $start) < $timeout) {
                usleep(250000); // 250ms
                if (!$process->isRunning()) {
                    break;
                }
            }

            $decision = file_exists($decisionFile) ? trim(file_get_contents($decisionFile)) : 'skip';

            $process->stop(2);

            return match ($decision) {
                'apply' => FixAction::Apply,
                'apply_all' => FixAction::ApplyAll,
                'quit' => FixAction::Quit,
                default => FixAction::Skip,
            };
        } finally {
            $this->removeDirectory($tmpDir);
        }
    }

    private function writeDiffPage(string $dir, DiffResult $diff): void
    {
        $originalLines = explode("\n", htmlspecialchars($diff->original, ENT_QUOTES | ENT_HTML5));
        $modifiedLines = explode("\n", htmlspecialchars($diff->modified, ENT_QUOTES | ENT_HTML5));

        $originalHtml = '';
        foreach ($originalLines as $i => $line) {
            $num = $i + 1;
            $originalHtml .= "<tr><td class='line-num'>{$num}</td><td class='code'>{$line}</td></tr>\n";
        }

        $modifiedHtml = '';
        foreach ($modifiedLines as $i => $line) {
            $num = $i + 1;
            $modifiedHtml .= "<tr><td class='line-num'>{$num}</td><td class='code'>{$line}</td></tr>\n";
        }

        $fileName = htmlspecialchars($diff->fileName, ENT_QUOTES | ENT_HTML5);
        $added = $diff->getAddedLineCount();
        $removed = $diff->getRemovedLineCount();

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP-Migrater - Diff Review</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, monospace; background: #1e1e1e; color: #d4d4d4; }
        .header { background: #252526; padding: 16px 24px; border-bottom: 1px solid #3c3c3c; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 16px; color: #569cd6; }
        .header .stats { font-size: 13px; }
        .stats .added { color: #4ec9b0; }
        .stats .removed { color: #f44747; }
        .actions { padding: 12px 24px; background: #2d2d2d; border-bottom: 1px solid #3c3c3c; display: flex; gap: 12px; }
        .btn { padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-apply { background: #4ec9b0; color: #1e1e1e; }
        .btn-skip { background: #3c3c3c; color: #d4d4d4; }
        .btn-apply-all { background: #569cd6; color: #1e1e1e; }
        .btn-quit { background: #f44747; color: #fff; }
        .btn:hover { opacity: 0.85; }
        .diff-container { display: flex; height: calc(100vh - 110px); overflow: hidden; }
        .diff-panel { flex: 1; overflow: auto; border-right: 1px solid #3c3c3c; }
        .diff-panel:last-child { border-right: none; }
        .diff-panel h2 { font-size: 12px; padding: 8px 16px; background: #252526; color: #808080; position: sticky; top: 0; z-index: 1; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        td.line-num { width: 50px; text-align: right; padding: 0 8px; color: #606060; user-select: none; }
        td.code { padding: 0 12px; white-space: pre; }
        tr:hover { background: #2a2d2e; }
        .sending { opacity: 0.5; pointer-events: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$fileName}</h1>
        <div class="stats">
            <span class="added">+{$added} added</span> &nbsp;
            <span class="removed">-{$removed} removed</span>
        </div>
    </div>
    <div class="actions" id="actions">
        <button class="btn btn-apply" onclick="decide('apply')">Apply (a)</button>
        <button class="btn btn-skip" onclick="decide('skip')">Skip (s)</button>
        <button class="btn btn-apply-all" onclick="decide('apply_all')">Apply All (A)</button>
        <button class="btn btn-quit" onclick="decide('quit')">Quit (q)</button>
    </div>
    <div class="diff-container">
        <div class="diff-panel">
            <h2>Original</h2>
            <table>{$originalHtml}</table>
        </div>
        <div class="diff-panel">
            <h2>Modified</h2>
            <table>{$modifiedHtml}</table>
        </div>
    </div>
    <script>
        function decide(action) {
            document.getElementById('actions').classList.add('sending');
            fetch('/decide?action=' + action, { method: 'POST' })
                .then(() => { document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-size:20px">Decision recorded. You can close this tab.</div>'; })
                .catch(() => alert('Failed to send decision'));
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'a' && !e.shiftKey) decide('apply');
            if (e.key === 's') decide('skip');
            if (e.key === 'A' || (e.key === 'a' && e.shiftKey)) decide('apply_all');
            if (e.key === 'q') decide('quit');
        });
    </script>
</body>
</html>
HTML;

        file_put_contents($dir . '/index.html', $html);
    }

    private function writeRouterScript(string $dir): void
    {
        $router = <<<'PHP'
<?php
$uri = $_SERVER['REQUEST_URI'];

if (str_starts_with($uri, '/decide')) {
    parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $params);
    $action = $params['action'] ?? 'skip';
    $allowed = ['apply', 'skip', 'apply_all', 'quit'];
    if (in_array($action, $allowed, true)) {
        file_put_contents(__DIR__ . '/decision.txt', $action);
        http_response_code(200);
        echo 'OK';
    } else {
        http_response_code(400);
        echo 'Invalid action';
    }
    return true;
}

return false; // serve static files
PHP;

        file_put_contents($dir . '/router.php', $router);
    }

    private function openBrowser(string $url): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process(['start', '', $url]);
            $process->setOptions(['create_no_window' => true]);
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $process = new Process(['open', $url]);
        } else {
            $process = new Process(['xdg-open', $url]);
        }

        $process->setTimeout(5);
        $process->run();
    }

    private function findAvailablePort(): int
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($sock, '127.0.0.1', 0);
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);
        return $port;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
