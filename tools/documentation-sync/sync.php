#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace OpenFGA\DocumentationSync;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class DocumentationSync
{
    private const USER_AGENT = 'OpenFGA-MCP-Documentation-Sync/1.0';
    private const GITHUB_API_BASE = 'https://api.github.com';
    private const GITHUB_RAW_BASE = 'https://raw.githubusercontent.com';

    private readonly Client $httpClient;
    private readonly string $githubToken;
    private readonly string $outputDir;
    private readonly bool $verbose;

    private array $sourceMapping = [
        'OPENFGA_DOCS' => [
            'repo' => 'openfga/openfga.dev',
            'branch' => 'main',
            'paths' => ['docs/content'],
            'recursive' => true,
            'output' => 'OPENFGA_DOCS.md',
        ],
        'PYTHON_SDK' => [
            'repo' => 'openfga/python-sdk',
            'branch' => 'main',
            'paths' => ['README.md'],
            'recursive' => false,
            'output' => 'PYTHON_SDK.md',
        ],
        'JAVA_SDK' => [
            'repo' => 'openfga/java-sdk',
            'branch' => 'main',
            'paths' => ['README.md'],
            'recursive' => false,
            'output' => 'JAVA_SDK.md',
        ],
        'JS_SDK' => [
            'repo' => 'openfga/js-sdk',
            'branch' => 'main',
            'paths' => ['README.md'],
            'recursive' => false,
            'output' => 'JS_SDK.md',
        ],
        'DOTNET_SDK' => [
            'repo' => 'openfga/dotnet-sdk',
            'branch' => 'main',
            'paths' => ['README.md'],
            'recursive' => false,
            'output' => 'DOTNET_SDK.md',
        ],
        'GO_SDK' => [
            'repo' => 'openfga/go-sdk',
            'branch' => 'main',
            'paths' => ['README.md'],
            'recursive' => false,
            'output' => 'GO_SDK.md',
        ],
        'PHP_SDK' => [
            'repo' => 'evansims/openfga-php',
            'branch' => 'main',
            'paths' => ['README.md', 'docs'],
            'recursive' => true,
            'output' => 'PHP_SDK.md',
        ],
        'LARAVEL_SDK' => [
            'repo' => 'evansims/openfga-laravel',
            'branch' => 'main',
            'paths' => ['README.md', 'docs'],
            'recursive' => true,
            'output' => 'LARAVEL_SDK.md',
        ],
    ];

    public function __construct(string $outputDir, ?string $githubToken = null, bool $verbose = false)
    {
        $this->outputDir = rtrim($outputDir, '/');
        $this->githubToken = $githubToken ?? '';
        $this->verbose = $verbose;

        $headers = [
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/vnd.github.v3+json',
        ];

        if ($this->githubToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->githubToken;
        }

        $this->httpClient = new Client([
            'headers' => $headers,
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        if (!is_dir($this->outputDir)) {
            if (!mkdir($this->outputDir, 0755, true)) {
                throw new RuntimeException("Failed to create output directory: {$this->outputDir}");
            }
        }
    }

    public function sync(?array $sources = null): void
    {
        $sources = $sources ?? array_keys($this->sourceMapping);

        foreach ($sources as $source) {
            if (!isset($this->sourceMapping[$source])) {
                $this->log("⚠️  Unknown source: {$source}", true);
                continue;
            }

            $this->log("📚 Syncing {$source}...");
            
            try {
                $this->syncSource($source, $this->sourceMapping[$source]);
                $this->log("✅ {$source} synced successfully");
            } catch (\Exception $e) {
                $this->log("❌ Failed to sync {$source}: " . $e->getMessage(), true);
            }
        }
    }

    private function syncSource(string $name, array $config): void
    {
        $outputPath = $this->outputDir . '/' . $config['output'];
        $compiledContent = $this->compileHeader($name, $config['repo']);

        foreach ($config['paths'] as $path) {
            $this->log("  📁 Fetching {$path}...");
            
            if ($config['recursive'] && !str_ends_with($path, '.md') && !str_ends_with($path, '.mdx')) {
                $content = $this->fetchDirectoryContent($config['repo'], $config['branch'], $path);
            } else {
                $content = $this->fetchFileContent($config['repo'], $config['branch'], $path);
            }

            if ($content !== '') {
                $compiledContent .= $content;
            }
        }

        file_put_contents($outputPath, $compiledContent);
        $this->log("  💾 Saved to {$outputPath}");
    }

    private function fetchFileContent(string $repo, string $branch, string $path): string
    {
        try {
            $url = sprintf('%s/%s/%s/%s', self::GITHUB_RAW_BASE, $repo, $branch, $path);
            $response = $this->httpClient->get($url);
            $content = (string) $response->getBody();

            return $this->processMarkdownContent($content, $repo, $path);
        } catch (GuzzleException $e) {
            $this->log("    ⚠️  Failed to fetch {$path}: " . $e->getMessage());
            return '';
        }
    }

    private function fetchDirectoryContent(string $repo, string $branch, string $path): string
    {
        try {
            $url = sprintf('%s/repos/%s/contents/%s', self::GITHUB_API_BASE, $repo, $path);
            $response = $this->httpClient->get($url, [
                'query' => ['ref' => $branch],
            ]);
            
            $items = json_decode((string) $response->getBody(), true);
            if (!is_array($items)) {
                return '';
            }

            $compiledContent = '';
            $this->processDirectoryItems($items, $repo, $branch, $path, $compiledContent);

            return $compiledContent;
        } catch (GuzzleException $e) {
            $this->log("    ⚠️  Failed to fetch directory {$path}: " . $e->getMessage());
            return '';
        }
    }

    private function processDirectoryItems(array $items, string $repo, string $branch, string $basePath, string &$compiledContent): void
    {
        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }
            return strcmp($a['name'], $b['name']);
        });

        foreach ($items as $item) {
            if ($item['type'] === 'file' && (str_ends_with($item['name'], '.md') || str_ends_with($item['name'], '.mdx'))) {
                $this->log("    📄 Processing {$item['path']}");
                $content = $this->fetchFileContent($repo, $branch, $item['path']);
                if ($content !== '') {
                    $compiledContent .= $content;
                }
            } elseif ($item['type'] === 'dir') {
                $this->log("    📂 Entering {$item['path']}");
                $subContent = $this->fetchDirectoryContent($repo, $branch, $item['path']);
                if ($subContent !== '') {
                    $compiledContent .= $subContent;
                }
            }
        }
    }

    private function processMarkdownContent(string $content, string $repo, string $path): string
    {
        $processed = "\n\n<!-- Source: {$repo}/{$path} -->\n\n";
        
        $lines = explode("\n", $content);
        $processedLines = [];
        $inCodeBlock = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, '```')) {
                $inCodeBlock = !$inCodeBlock;
            }

            if (!$inCodeBlock) {
                $line = $this->adjustHeadingLevel($line, $path);
                $line = $this->fixRelativeLinks($line, $repo);
                $line = $this->fixImageUrls($line, $repo);
            }

            $processedLines[] = $line;
        }

        $processed .= implode("\n", $processedLines);
        $processed .= "\n\n<!-- End of {$repo}/{$path} -->\n";

        return $processed;
    }

    private function adjustHeadingLevel(string $line, string $path): string
    {
        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
            $level = strlen($matches[1]);
            
            if (str_contains($path, 'README')) {
                $level = min($level + 1, 6);
            } else {
                $level = min($level + 2, 6);
            }

            return str_repeat('#', $level) . ' ' . $matches[2];
        }

        return $line;
    }

    private function fixRelativeLinks(string $line, string $repo): string
    {
        $pattern = '/\[([^\]]+)\]\((?!https?:\/\/)([^)]+)\)/';
        $replacement = function ($matches) use ($repo) {
            $text = $matches[1];
            $url = $matches[2];
            
            if (str_starts_with($url, '#')) {
                return "[{$text}]({$url})";
            }
            
            return "[{$text}](https://github.com/{$repo}/blob/main/{$url})";
        };

        return preg_replace_callback($pattern, $replacement, $line);
    }

    private function fixImageUrls(string $line, string $repo): string
    {
        $pattern = '/!\[([^\]]*)\]\((?!https?:\/\/)([^)]+)\)/';
        $replacement = function ($matches) use ($repo) {
            $alt = $matches[1];
            $path = $matches[2];
            
            return "![{$alt}](https://raw.githubusercontent.com/{$repo}/main/{$path})";
        };

        return preg_replace_callback($pattern, $replacement, $line);
    }

    private function compileHeader(string $name, string $repo): string
    {
        $header = "# {$name} Documentation\n\n";
        $header .= "> Compiled from: https://github.com/{$repo}\n";
        $header .= "> Generated: " . date('Y-m-d H:i:s') . " UTC\n\n";
        $header .= "---\n";

        return $header;
    }

    private function log(string $message, bool $isError = false): void
    {
        if ($this->verbose || $isError) {
            echo $message . PHP_EOL;
        }
    }
}

function main(): void
{
    $options = getopt('o:t:s:vh', ['output:', 'token:', 'source:', 'verbose', 'help']);

    if (isset($options['h']) || isset($options['help'])) {
        showHelp();
        exit(0);
    }

    $outputDir = $options['o'] ?? $options['output'] ?? __DIR__ . '/../../docs';
    $githubToken = $options['t'] ?? $options['token'] ?? getenv('GITHUB_TOKEN') ?: null;
    $verbose = isset($options['v']) || isset($options['verbose']);
    $sources = isset($options['s']) ? explode(',', $options['s']) : (isset($options['source']) ? explode(',', $options['source']) : null);

    try {
        $sync = new DocumentationSync($outputDir, $githubToken, $verbose);
        $sync->sync($sources);
        
        echo "✅ Documentation sync completed successfully!" . PHP_EOL;
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

function showHelp(): void
{
    echo <<<HELP
OpenFGA Documentation Sync Tool

Usage: php sync.php [OPTIONS]

Options:
  -o, --output <dir>     Output directory for compiled documentation (default: ../../docs)
  -t, --token <token>    GitHub personal access token (optional, uses GITHUB_TOKEN env if not provided)
  -s, --source <sources> Comma-separated list of sources to sync (default: all)
                        Available sources: OPENFGA_DOCS, PYTHON_SDK, JAVA_SDK, JS_SDK, 
                                         DOTNET_SDK, GO_SDK, PHP_SDK, LARAVEL_SDK
  -v, --verbose         Enable verbose output
  -h, --help           Show this help message

Examples:
  # Sync all documentation
  php sync.php -v

  # Sync specific sources only
  php sync.php -s OPENFGA_DOCS,PHP_SDK -v

  # Use custom output directory and GitHub token
  php sync.php -o /path/to/docs -t ghp_xxxxxxxxxxxx

  # Set token via environment variable
  GITHUB_TOKEN=ghp_xxxxxxxxxxxx php sync.php

Note: Using a GitHub token is recommended to avoid rate limiting, especially
      when syncing large documentation sets.

HELP;
}

if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/vendor/autoload.php';
    main();
}