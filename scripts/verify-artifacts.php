#!/usr/bin/env php
<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

/**
 * Ensures Wayfinder artifacts are treated as build outputs:
 *  - validates ignore rules so generated files stay out of git
 *  - runs the generator in a sandbox to prove it still succeeds
 */
final class ArtifactVerifier
{
    private string $projectRoot;

    private string $sandbox;

    /**
     * @var array<string, array{expected: string, generated: string}>
     */
    private array $targets = [];

    /**
     * @var list<string>
     */
    private array $errors = [];

    public function __construct()
    {
        $root = realpath(__DIR__.'/..');

        if ($root === false) {
            throw new RuntimeException('Unable to determine project root.');
        }

        $this->projectRoot = $root;
        $this->sandbox = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.
            'verify-artifacts-'.bin2hex(random_bytes(5));

        $this->targets = [
            'actions' => [
                'expected' => $this->projectRoot.'/resources/js/actions',
                'generated' => $this->sandbox.'/actions',
            ],
            'routes' => [
                'expected' => $this->projectRoot.'/resources/js/routes',
                'generated' => $this->sandbox.'/routes',
            ],
            'wayfinder' => [
                'expected' => $this->projectRoot.'/resources/js/wayfinder',
                'generated' => $this->sandbox.'/wayfinder',
            ],
        ];
    }

    public function __destruct()
    {
        $this->deleteDirectory($this->sandbox);
    }

    public function run(): int
    {
        $this->ensureSandbox();
        $this->assertGitPolicy();
        $this->generateArtifacts();
        $this->assertSandboxOutputs();

        if (! empty($this->errors)) {
            fwrite(STDERR, "verify-artifacts: Wayfinder generator policy failed:\n");
            foreach ($this->errors as $error) {
                fwrite(STDERR, "  - {$error}\n");
            }

            fwrite(
                STDERR,
                "Ensure the artifacts stay ignored and rerun `php artisan wayfinder:generate --with-form` locally.\n"
            );

            return 1;
        }

        fwrite(STDOUT, "verify-artifacts: sandbox generation + git policy ✅\n");

        return 0;
    }

    private function ensureSandbox(): void
    {
        if (! is_dir($this->sandbox) && ! mkdir($concurrentDirectory = $this->sandbox, 0777, true) && ! is_dir($concurrentDirectory)) {
            throw new RuntimeException('Unable to create sandbox directory: '.$this->sandbox);
        }
    }

    private function generateArtifacts(): void
    {
        $command = [
            'php',
            'artisan',
            'wayfinder:generate',
            '--with-form',
            '--path='.$this->sandbox,
            '--no-interaction',
            '--ansi',
        ];

        $process = new Process(
            $command,
            $this->projectRoot,
            array_merge(
                $_ENV,
                [
                    'APP_ENV' => $_ENV['APP_ENV'] ?? 'testing',
                    'APP_KEY' => $_ENV['APP_KEY'] ?? ('base64:'.base64_encode(random_bytes(32))),
                    'APP_DEBUG' => 'false',
                ],
            ),
        );

        $process->setTimeout(120);
        $process->run(function ($type, $buffer): void {
            if ($type === Process::ERR) {
                fwrite(STDERR, $buffer);
            } else {
                fwrite(STDOUT, $buffer);
            }
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Failed to regenerate Wayfinder artifacts.');
        }
    }

    private function assertGitPolicy(): void
    {
        foreach ($this->targets as $label => $paths) {
            $relative = ltrim(str_replace($this->projectRoot, '', $paths['expected']), DIRECTORY_SEPARATOR);

            if (! $this->ignoredInGit($relative)) {
                $this->errors[] = sprintf('[%s] Missing ignore rule for %s in .gitignore', $label, $relative);
            }

            if ($this->hasTrackedFiles($relative)) {
                $this->errors[] = sprintf('[%s] Tracked files detected under %s; generated outputs must stay untracked.', $label, $relative);
            }
        }
    }

    private function assertSandboxOutputs(): void
    {
        foreach ($this->targets as $label => $paths) {
            if (! is_dir($paths['generated'])) {
                $this->errors[] = sprintf('[%s] Generator output missing: %s', $label, $paths['generated']);
            }
        }
    }

    private function ignoredInGit(string $relativePath): bool
    {
        $ignoreFile = $this->projectRoot.'/.gitignore';

        if (! file_exists($ignoreFile)) {
            return false;
        }

        $needle = rtrim(ltrim($relativePath, '/'), '/');

        foreach (file($ignoreFile, FILE_IGNORE_NEW_LINES) as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $normalized = rtrim(ltrim($trimmed, '/'), '/');

            if ($normalized === $needle) {
                return true;
            }
        }

        return false;
    }

    private function hasTrackedFiles(string $relativePath): bool
    {
        $process = Process::fromShellCommandline(
            'git ls-files -- '.escapeshellarg($relativePath),
            $this->projectRoot,
        );

        $process->run();

        return trim($process->getOutput()) !== '';
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($path);
    }
}

exit((new ArtifactVerifier)->run());
