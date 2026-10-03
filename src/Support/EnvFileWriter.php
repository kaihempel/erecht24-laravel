<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Support;

final class EnvFileWriter
{
    /**
     * Replaces an existing `KEY=...` line in place, or appends one if absent.
     * Returns false (does not throw) when the key/value is unsafe, or the file
     * does not exist or is not writable. Symlinks are resolved and the file's
     * permissions are preserved.
     */
    public function write(string $envPath, string $key, string $value): bool
    {
        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $key) !== 1 || preg_match('/[\r\n\0]/', $value) === 1) {
            return false;
        }

        $path = realpath($envPath);

        if ($path === false || ! is_file($path) || ! is_writable($path) || ! is_writable(dirname($path))) {
            return false;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        $line = $key.'='.$this->formatValue($value);
        $pattern = '/^'.preg_quote($key, '/').'=[^\r\n]*/m';

        if (preg_match($pattern, $contents) === 1) {
            $updated = preg_replace_callback($pattern, static fn (): string => $line, $contents, 1);
        } else {
            $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
            $updated = ($contents === '' ? '' : rtrim($contents, "\r\n").$eol).$line.$eol;
        }

        if ($updated === null) {
            return false;
        }

        return $this->replaceFile($path, $updated);
    }

    private function formatValue(string $value): string
    {
        if (preg_match('/[\s#"\'$\\\\]/', $value) !== 1) {
            return $value;
        }

        return '"'.addcslashes($value, '"\\$').'"';
    }

    private function replaceFile(string $path, string $contents): bool
    {
        $tempPath = tempnam(dirname($path), '.env-tmp');

        if ($tempPath === false) {
            return false;
        }

        $mode = fileperms($path);

        if (file_put_contents($tempPath, $contents) === false
            || ($mode !== false && ! chmod($tempPath, $mode & 0777))
            || ! rename($tempPath, $path)) {
            @unlink($tempPath);

            return false;
        }

        return true;
    }
}
