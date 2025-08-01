<?php

$baseDir = __DIR__ . '/src';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));

$errors = [];

foreach ($rii as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;

    $realPath = $file->getRealPath();
    $relativePath = substr($realPath, strlen($baseDir) + 1);
    $expectedPath = str_replace('\\', DIRECTORY_SEPARATOR, $relativePath);

    $contents = file_get_contents($realPath);

    if (preg_match('/namespace\s+([^;]+);/i', $contents, $nsMatch) &&
        preg_match('/class\s+([^\s{]+)/i', $contents, $classMatch)) {

        $namespace = trim($nsMatch[1]);
        $className = trim($classMatch[1]);

        $expectedNamespacePath = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
        $expectedFullPath = $expectedNamespacePath . DIRECTORY_SEPARATOR . $className . '.php';

        $actualPath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $realPath);

        if (strcasecmp($expectedFullPath, $actualPath) === 0 && $expectedFullPath !== $actualPath) {
            $errors[] = [
                'expected' => $expectedFullPath,
                'actual' => $actualPath,
                'file' => $realPath
            ];
        }
    }
}

if (empty($errors)) {
    echo "✅ No casing mismatches found.\n";
} else {
    echo "❌ Casing mismatches detected:\n";
    foreach ($errors as $error) {
        echo "- Expected: {$error['expected']}\n";
        echo "  Actual:   {$error['actual']}\n";
        echo "  File:     {$error['file']}\n\n";
    }
    exit(1);
}
