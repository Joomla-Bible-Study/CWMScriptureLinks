#!/usr/bin/env php
<?php

/**
 * CWM Scripture Links - Package Build Script
 *
 * Creates pkg_cwmscripture-{version}.zip containing:
 *   - lib_cwmscripture.zip  (from library submodule build)
 *   - plg_content_scripturelinks.zip  (content plugin)
 *   - pkg_cwmscripture.xml  (package manifest)
 *
 * Prerequisites:
 *   1. git submodule update --init --recursive
 *   2. cd libraries/lib_cwmscripture && npm run build && php build/build-package.php
 *
 * Usage:
 *   php build/build-package.php              # Build full package (library + plugin)
 *   php build/build-package.php --plugin-only # Build only plg_content_scripturelinks.zip
 *   php build/build-package.php --verbose    # Show files being added
 */

const BASE_DIR = __DIR__ . '/..';

$verbose    = \in_array('--verbose', $argv ?? [], true) || \in_array('-v', $argv ?? [], true);
$pluginOnly = \in_array('--plugin-only', $argv ?? [], true);

try {
    if ($pluginOnly) {
        buildPluginOnly($verbose);
    } else {
        build($verbose);
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

function build(bool $verbose = false): void
{
    // Read version from library manifest
    $manifestXml = simplexml_load_file(BASE_DIR . '/libraries/lib_cwmscripture/cwmscripture.xml');

    if (!$manifestXml) {
        throw new \RuntimeException('Could not parse libraries/lib_cwmscripture/cwmscripture.xml — did you init the submodule?');
    }

    $version = (string) $manifestXml->version;
    echo "Building CWM Scripture Links v$version\n\n";

    $buildDir = BASE_DIR . '/build/dist';

    if (is_dir($buildDir)) {
        exec('rm -rf ' . escapeshellarg($buildDir));
    }

    mkdir($buildDir, 0777, true);

    // Locate pre-built library ZIP from submodule
    $libDistDir = BASE_DIR . '/libraries/lib_cwmscripture/build/dist';
    $libZipSource = null;

    if (is_dir($libDistDir)) {
        foreach (glob($libDistDir . '/lib_cwmscripture-*.zip') as $candidate) {
            $libZipSource = $candidate;
            break;
        }
    }

    if (!$libZipSource || !file_exists($libZipSource)) {
        throw new \RuntimeException(
            "lib_cwmscripture ZIP not found in libraries/lib_cwmscripture/build/dist/\n"
            . "Run the library build first: cd lib_cwmscripture && php build/build-package.php"
        );
    }

    echo "Using pre-built " . basename($libZipSource) . " from submodule\n";
    copy($libZipSource, $buildDir . '/lib_cwmscripture.zip');

    // Build plugin ZIP
    echo "Creating plg_content_scripturelinks.zip...\n";
    $plgZip = new ZipArchive();
    $plgZipPath = $buildDir . '/plg_content_scripturelinks.zip';

    if ($plgZip->open($plgZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('Could not create plg_content_scripturelinks.zip');
    }

    // Add plugin directories from repo root
    $plgZip->addFile(BASE_DIR . '/scripturelinks.xml', 'scripturelinks.xml');
    addDirectoryToZip($plgZip, BASE_DIR . '/src', 'src', $verbose);
    addDirectoryToZip($plgZip, BASE_DIR . '/services', 'services', $verbose);
    addDirectoryToZip($plgZip, BASE_DIR . '/language', 'language', $verbose);
    addDirectoryToZip($plgZip, BASE_DIR . '/tmpl', 'tmpl', $verbose);
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
    $pkgZip->addFile(BASE_DIR . '/build/pkg_cwmscripture.xml', 'pkg_cwmscripture.xml');
    $pkgZip->close();
    echo "  Done.\n";

    echo "\nPackage built: $pkgZipPath\n";
}

/**
 * Build only the plugin ZIP (no library, no package wrapper).
 * Used by pkg_proclaim's build process where the library is a separate zip.
 */
function buildPluginOnly(bool $verbose = false): void
{
    echo "Building plg_content_scripturelinks.zip (plugin only)\n\n";

    $buildDir = BASE_DIR . '/build/dist';

    if (is_dir($buildDir)) {
        exec('rm -rf ' . escapeshellarg($buildDir));
    }

    mkdir($buildDir, 0777, true);

    $plgZip     = new ZipArchive();
    $plgZipPath = $buildDir . '/plg_content_scripturelinks.zip';

    if ($plgZip->open($plgZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('Could not create plg_content_scripturelinks.zip');
    }

    $plgZip->addFile(BASE_DIR . '/scripturelinks.xml', 'scripturelinks.xml');
    addDirectoryToZip($plgZip, BASE_DIR . '/src', 'src', $verbose);
    addDirectoryToZip($plgZip, BASE_DIR . '/services', 'services', $verbose);
    addDirectoryToZip($plgZip, BASE_DIR . '/language', 'language', $verbose);
    addDirectoryToZip($plgZip, BASE_DIR . '/tmpl', 'tmpl', $verbose);
    $plgZip->close();

    echo "Plugin built: $plgZipPath\n";
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
