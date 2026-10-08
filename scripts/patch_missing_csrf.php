<?php

/**
 * CSRF Automated Patcher for Blade Views
 * Part of Option B: Portfolio Security Sweep
 */

if ($argc < 2) {
    echo "Usage: php patch_missing_csrf.php <directory>\n";
    exit(1);
}

$dir = $argv[1];
if (!is_dir($dir)) {
    echo "Directory not found: $dir\n";
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$patched = 0;

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $filePath = $file->getPathname();
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    if (!in_array($ext, ['php', 'blade', 'html']) && !str_ends_with($filePath, '.blade.php')) {
        continue;
    }

    $content = file_get_contents($filePath);
    if ($content === false) continue;

    // Check for form tags
    if (!preg_match_all('/<form\b[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    $modified = false;
    $offsetShift = 0;

    foreach ($matches[0] as $match) {
        $formTag = $match[0];
        $origOffset = $match[1];
        $currentOffset = $origOffset + $offsetShift;

        // Skip GET forms
        if (preg_match('/method\s*=\s*["\']get["\']/i', $formTag)) {
            continue;
        }

        // Find closing form
        $closeFormPos = stripos($content, '</form>', $currentOffset);
        if ($closeFormPos === false) {
            $formBody = substr($content, $currentOffset, 2000);
        } else {
            $formBody = substr($content, $currentOffset, $closeFormPos - $currentOffset);
        }

        // Check if CSRF exists
        $hasCsrf = preg_match(
            '/(?:@csrf|csrf_token|csrfToken|csrf_field|csrfField|laravelhelper::csrffield|name=["\']_token["\']|name=["\']token["\']|@include\([\'"]csrf[\'"]\))/i',
            $formBody
        );

        if (!$hasCsrf) {
            // Insert @include('csrf') right after <form...>
            $insertPos = $currentOffset + strlen($formTag);
            $insertion = "\n    @include('csrf')";
            $content = substr_replace($content, $insertion, $insertPos, 0);
            $offsetShift += strlen($insertion);
            $modified = true;
            echo "  [PATCHED] $filePath\n";
            $patched++;
        }
    }

    if ($modified) {
        file_put_contents($filePath, $content);
    }
}

echo "Total forms patched: $patched\n";
