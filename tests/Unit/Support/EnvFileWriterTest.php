<?php

declare(strict_types=1);

use KaiHempel\ERecht24\Support\EnvFileWriter;

function makeEnvFixture(string $contents): string
{
    $dir = sys_get_temp_dir().'/erecht24-env-writer-tests';

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $path = tempnam($dir, 'env');
    file_put_contents($path, $contents);

    return $path;
}

afterEach(function () {
    $dir = sys_get_temp_dir().'/erecht24-env-writer-tests';

    if (is_dir($dir)) {
        foreach (glob($dir.'/*') as $file) {
            @chmod($file, 0644);
            @unlink($file);
        }
    }
});

it('replaces an existing key in place, leaving other lines untouched', function () {
    $path = makeEnvFixture("APP_NAME=Test\nERECHT24_PUSH_SECRET=old\nAPP_DEBUG=true\n");

    $result = (new EnvFileWriter)->write($path, 'ERECHT24_PUSH_SECRET', 'new-secret');

    expect($result)->toBeTrue();
    $contents = file_get_contents($path);
    expect($contents)->toContain('ERECHT24_PUSH_SECRET=new-secret')
        ->not->toContain('ERECHT24_PUSH_SECRET=old')
        ->toContain('APP_NAME=Test')
        ->toContain('APP_DEBUG=true');
});

it('appends the key on a new line when absent', function () {
    $path = makeEnvFixture("APP_NAME=Test\n");

    $result = (new EnvFileWriter)->write($path, 'ERECHT24_PUSH_SECRET', 'fresh-secret');

    expect($result)->toBeTrue();
    $contents = file_get_contents($path);
    expect($contents)->toContain('APP_NAME=Test')
        ->toContain('ERECHT24_PUSH_SECRET=fresh-secret');
});

it('returns false without modifying the file when it is not writable', function () {
    $path = makeEnvFixture("ERECHT24_PUSH_SECRET=old\n");
    chmod($path, 0444);

    if (posix_getuid() === 0) {
        test()->markTestSkipped('Cannot test unwritable files as root.');
    }

    $result = (new EnvFileWriter)->write($path, 'ERECHT24_PUSH_SECRET', 'new-secret');

    expect($result)->toBeFalse();
    expect(file_get_contents($path))->toBe("ERECHT24_PUSH_SECRET=old\n");

    chmod($path, 0644);
});

it('returns false when the file does not exist', function () {
    $path = sys_get_temp_dir().'/erecht24-env-writer-tests/does-not-exist-'.uniqid();

    $result = (new EnvFileWriter)->write($path, 'ERECHT24_PUSH_SECRET', 'new-secret');

    expect($result)->toBeFalse();
});

it('writes secrets containing regex backreference characters literally', function () {
    $path = makeEnvFixture("ERECHT24_PUSH_SECRET=old\n");

    expect((new EnvFileWriter)->write($path, 'ERECHT24_PUSH_SECRET', 'a$1b\\1c'))->toBeTrue();
    expect(file_get_contents($path))->toBe("ERECHT24_PUSH_SECRET=\"a\\\$1b\\\\1c\"\n");
});

it('rejects invalid keys and values containing newlines', function () {
    $path = makeEnvFixture("A=1\n");
    $writer = new EnvFileWriter;

    expect($writer->write($path, 'bad key', 'x'))->toBeFalse()
        ->and($writer->write($path, 'KEY', "x\nINJECT=1"))->toBeFalse()
        ->and($writer->write($path, 'KEY', "x\r"))->toBeFalse();
    expect(file_get_contents($path))->toBe("A=1\n");
});

it('quotes values containing whitespace', function () {
    $path = makeEnvFixture("A=1\n");

    (new EnvFileWriter)->write($path, 'KEY', 'a b');

    expect(file_get_contents($path))->toBe("A=1\nKEY=\"a b\"\n");
});

it('preserves file mode and CRLF line endings', function () {
    $path = makeEnvFixture("A=1\r\nKEY=old\r\nB=2\r\n");
    chmod($path, 0640);

    (new EnvFileWriter)->write($path, 'KEY', 'new');

    clearstatcache();
    expect(file_get_contents($path))->toBe("A=1\r\nKEY=new\r\nB=2\r\n")
        ->and(fileperms($path) & 0777)->toBe(0640);
});

it('writes through a symlink without replacing it', function () {
    $target = makeEnvFixture("KEY=old\n");
    $link = $target.'-link';
    symlink($target, $link);

    expect((new EnvFileWriter)->write($link, 'KEY', 'new'))->toBeTrue();

    expect(is_link($link))->toBeTrue()
        ->and(file_get_contents($target))->toBe("KEY=new\n");
    unlink($link);
});

it('leaves no temp files behind', function () {
    $path = makeEnvFixture("A=1\n");
    (new EnvFileWriter)->write($path, 'KEY', 'v');

    expect(glob(dirname($path).'/.env-tmp*'))->toBe([]);
});
