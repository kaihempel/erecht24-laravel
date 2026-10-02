<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Storage;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\DTOs\LegalTextMetadata;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\LegalTextStoreException;

/**
 * Persists legal text content and its metadata as plain files on a configurable
 * Laravel filesystem disk.
 *
 * Atomicity note: writes are performed as "write to a `.tmp` path, then move into
 * place". On local-type disks this move is an atomic `rename()`, so a failure
 * never exposes a partially-written file at the final path. On remote/cloud disks
 * (e.g. S3), the underlying move is a copy-then-delete, which is NOT atomic — a
 * failure between the copy and the delete can leave the temp object behind (it is
 * cleaned up on a best-effort basis) but will not corrupt or truncate the final
 * path, since the final path is only ever replaced once the copy has fully
 * succeeded. Callers configuring `erecht24.disk` to a non-local driver should be
 * aware the "atomic" guarantee here is "never a truncated/partial file at the
 * final path", not "a single atomic filesystem operation".
 */
final class LegalTextStore
{
    public function __construct(private readonly Erecht24Settings $settings) {}

    /**
     * @throws \InvalidArgumentException when $lang is not a supported language code
     * @throws LegalTextStoreException when the underlying write fails
     */
    public function put(
        LegalTextType $type,
        string $lang,
        string $html,
        ?CarbonImmutable $sourceModifiedAt = null,
    ): void {
        $this->assertSupportedLanguage($lang);

        $metadata = new LegalTextMetadata(
            fetchedAt: CarbonImmutable::now(),
            sourceModifiedAt: $sourceModifiedAt,
        );

        $this->atomicWrite($this->contentPath($type, $lang), $html);
        $this->atomicWrite($this->metaPath($type, $lang), $metadata->toJson());
    }

    /**
     * @throws \InvalidArgumentException when $lang is not a supported language code
     */
    public function get(LegalTextType $type, string $lang): ?string
    {
        $this->assertSupportedLanguage($lang);

        $disk = Storage::disk($this->settings->disk());
        $path = $this->contentPath($type, $lang);

        if (! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);

        return $contents === null ? null : (string) $contents;
    }

    /**
     * @throws \InvalidArgumentException when $lang is not a supported language code
     */
    public function has(LegalTextType $type, string $lang): bool
    {
        $this->assertSupportedLanguage($lang);

        return Storage::disk($this->settings->disk())->exists($this->contentPath($type, $lang));
    }

    /**
     * @throws \InvalidArgumentException when $lang is not a supported language code
     */
    public function lastModified(LegalTextType $type, string $lang): ?CarbonImmutable
    {
        $this->assertSupportedLanguage($lang);

        $disk = Storage::disk($this->settings->disk());
        $metaPath = $this->metaPath($type, $lang);

        if (! $disk->exists($metaPath)) {
            return null;
        }

        $metadata = LegalTextMetadata::fromJson((string) $disk->get($metaPath));

        return $metadata->fetchedAt;
    }

    /**
     * @return CarbonImmutable|null the source system's modification date passed to `put()`, or
     *                              null if never saved or no source modification date was supplied
     *
     * @throws \InvalidArgumentException when $lang is not a supported language code
     */
    public function sourceModifiedAt(LegalTextType $type, string $lang): ?CarbonImmutable
    {
        $this->assertSupportedLanguage($lang);

        $disk = Storage::disk($this->settings->disk());
        $metaPath = $this->metaPath($type, $lang);

        if (! $disk->exists($metaPath)) {
            return null;
        }

        $metadata = LegalTextMetadata::fromJson((string) $disk->get($metaPath));

        return $metadata->sourceModifiedAt;
    }

    /**
     * @throws \InvalidArgumentException when $lang is not a supported language code
     */
    public function forget(LegalTextType $type, string $lang): void
    {
        $this->assertSupportedLanguage($lang);

        $disk = Storage::disk($this->settings->disk());

        foreach ([$this->contentPath($type, $lang), $this->metaPath($type, $lang)] as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    private function contentPath(LegalTextType $type, string $lang): string
    {
        return sprintf('%s/%s.%s.html', $this->settings->directory(), $type->fileSlug(), $lang);
    }

    private function metaPath(LegalTextType $type, string $lang): string
    {
        return sprintf('%s/%s.%s.meta.json', $this->settings->directory(), $type->fileSlug(), $lang);
    }

    private function assertSupportedLanguage(string $lang): void
    {
        if (! in_array($lang, $this->settings->languages(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported language code [%s].', $lang));
        }
    }

    /**
     * Writes to a `.tmp` path first, then moves it into place.
     *
     * On local-type disks, `move()` is an atomic `rename()`, so a failure never
     * exposes a partially-written file at the final path. On remote/cloud disks,
     * `move()` is a copy-then-delete, so the operation as a whole is not a single
     * atomic filesystem call — see the class-level docblock. Either way, `put()`
     * or `move()` returning `false` (a driver reporting failure without throwing)
     * is treated the same as a thrown exception: wrapped in
     * {@see LegalTextStoreException} rather than silently ignored.
     */
    private function atomicWrite(string $path, string $contents): void
    {
        $disk = Storage::disk($this->settings->disk());
        $tempPath = $path.'.tmp';

        try {
            $written = $disk->put($tempPath, $contents);

            if ($written === false) {
                throw LegalTextStoreException::forPath($path);
            }

            $moved = $disk->move($tempPath, $path);

            if ($moved === false) {
                throw LegalTextStoreException::forPath($path);
            }
        } catch (LegalTextStoreException $e) {
            if ($disk->exists($tempPath)) {
                $disk->delete($tempPath);
            }

            throw $e;
        } catch (\Throwable $e) {
            if ($disk->exists($tempPath)) {
                $disk->delete($tempPath);
            }

            throw LegalTextStoreException::forPath($path, $e);
        }
    }
}
