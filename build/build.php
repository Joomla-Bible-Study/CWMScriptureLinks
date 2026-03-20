#!/usr/bin/env php
<?php

/**
 * CWM Scripture Library - Package Build Script
 *
 * Creates pkg_cwmscripture-{version}.zip containing:
 *   - lib_cwmscripture.zip  (library extension)
 *   - plg_content_scripturelinks.zip  (content plugin)
 *   - pkg_cwmscripture.xml  (package manifest)
 */

const BASE_DIR = __DIR__ . '/..';

$verbose = \in_array('--verbose', $argv ?? [], true) || \in_array('-v', $argv ?? [], true);

try {
    build($verbose);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

function build(bool $verbose = false): void
{
    // Read version from library manifest
    $manifestXml = simplexml_load_file(BASE_DIR . '/lib_cwmscripture/cwmscripture.xml');

    if (!$manifestXml) {
        throw new \RuntimeException('Could not parse lib_cwmscripture/cwmscripture.xml');
    }

    $version = (string) $manifestXml->version;
    echo "Building CWM Scripture Library v$version\n\n";

    $buildDir = BASE_DIR . '/build/dist';

    if (is_dir($buildDir)) {
        exec('rm -rf ' . escapeshellarg($buildDir));
    }

    mkdir($buildDir, 0777, true);

    // Build library ZIP
    echo "Creating lib_cwmscripture.zip...\n";
    $libZip = new ZipArchive();
    $libZipPath = $buildDir . '/lib_cwmscripture.zip';

    if ($libZip->open($libZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('Could not create lib_cwmscripture.zip');
    }

    addDirectoryToZip($libZip, BASE_DIR . '/lib_cwmscripture', 'lib_cwmscripture', $verbose);
    addDirectoryToZip($libZip, BASE_DIR . '/media/lib_cwmscripture', 'media/lib_cwmscripture', $verbose);
    $libZip->close();
    echo "  Done.\n";

    // Build plugin ZIP
    echo "Creating plg_content_scripturelinks.zip...\n";
    $plgZip = new ZipArchive();
    $plgZipPath = $buildDir . '/plg_content_scripturelinks.zip';

    if ($plgZip->open($plgZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('Could not create plg_content_scripturelinks.zip');
    }

    addDirectoryToZip($plgZip, BASE_DIR . '/plg_content_scripturelinks', '', $verbose);
    $plgZip->close();
    echo "  Done.\n";

    // Build package ZIP
    $pkgZipPath = $buildDir . '/pkg_cwmscripture-' . $version . '.zip';
    echo "Creating pkg_cwmscripture-$version.zip...\n";
    $pkgZip = new ZipArchive();

    if ($pkgZip->open($pkgZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('Could not create package zip');
    }

    $pkgZip->addFile($buildDir . '/lib_cwmscripture.zip', 'lib_cwmscripture.zip');
    $pkgZip->addFile($buildDir . '/plg_content_scripturelinks.zip', 'plg_content_scripturelinks.zip');
    $pkgZip->addFile(BASE_DIR . '/pkg_cwmscripture.xml', 'pkg_cwmscripture.xml');
    $pkgZip->close();
    echo "  Done.\n";

    echo "\nPackage built: $pkgZipPath\n";
}

/**
 * Recursively add a directory's contents to a ZipArchive.
 */
function addDirectoryToZip(ZipArchive $zip, string $sourcePath, string $zipPrefix, bool $verbose): void
{
    $excludes = ['.git', '.DS_Store', '.idea', 'node_modules'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            continue;
        }

        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, \strlen(realpath($sourcePath)) + 1);

        // Check excludes
        $skip = false;

        foreach ($excludes as $exclude) {
            if (str_contains($relativePath, $exclude)) {
                $skip = true;
                break;
            }
        }

        if ($skip) {
            continue;
        }

        $zipPath = $zipPrefix !== '' ? $zipPrefix . '/' . $relativePath : $relativePath;

        if ($verbose) {
            echo "    + $zipPath\n";
        }

        $zip->addFile($filePath, $zipPath);
    }
}
