<?php

require_once __DIR__ . '/S3Uploader.php';

function get_s3_client(): S3Uploader
{
    $config = require __DIR__ . '/../config/aws_config.php';

    return new S3Uploader(
        $config['key'],
        $config['secret'],
        $config['region'],
        $config['bucket']
    );
}

function get_backup_tmp_dir(): string
{
    $tmpDir = __DIR__ . '/../tmp';

    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0755, true);
    }

    if (!is_dir($tmpDir) || !is_writable($tmpDir)) {
        $fallback = sys_get_temp_dir();
        if (is_writable($fallback)) {
            return $fallback;
        }
        throw new RuntimeException(
            "Walang writable temp directory. Ginawa/chinek: '$tmpDir' at fallback na '$fallback'. " .
            "I-check ang folder permissions (dapat 755 o 777) o gumawa ng 'tmp' folder manually sa root ng project."
        );
    }

    return $tmpDir;
}

function upload_zip_csv_to_s3(array $data, string $baseName): array
{
    $csvHandle = fopen('php://temp', 'r+');
    if (!empty($data)) {
        fputcsv($csvHandle, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($csvHandle, $row);
        }
    }
    rewind($csvHandle);
    $csvContent = stream_get_contents($csvHandle);
    fclose($csvHandle);

    $tmpDir = get_backup_tmp_dir();
    $csvTmpPath = null;
    $zipTmpPath = null;

    $useZip = false;
    if (class_exists('ZipArchive')) {
        $csvTmpPath = $tmpDir . '/' . $baseName . '.csv';
        file_put_contents($csvTmpPath, $csvContent);

        $zipTmpPath = $tmpDir . '/' . $baseName . '.zip';
        $zip = new ZipArchive();
        $openResult = $zip->open($zipTmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($openResult === true) {
            $addResult = $zip->addFile($csvTmpPath, $baseName . '.csv');
            $zip->close();

            if ($addResult && file_exists($zipTmpPath)) {
                $useZip = true;
            } else {
                error_log("upload_zip_csv_to_s3: zip->addFile() failed kahit successful ang open(). Fallback sa CSV.");
                @unlink($zipTmpPath);
            }
        } else {
            error_log("upload_zip_csv_to_s3: ZipArchive::open() failed with code $openResult sa path '$zipTmpPath'. Fallback sa plain CSV.");
        }
    }

    if ($useZip) {
        $uploadPath  = $zipTmpPath;
        $uploadName  = $baseName . '.zip';
        $contentType = 'application/zip';
    } else {
        if ($csvTmpPath === null) {
            $csvTmpPath = $tmpDir . '/' . $baseName . '.csv';
            file_put_contents($csvTmpPath, $csvContent);
        }
        $uploadPath  = $csvTmpPath;
        $uploadName  = $baseName . '.csv';
        $contentType = 'text/csv';
    }

    $s3  = get_s3_client();
    $key = 'backups/' . $uploadName;

    $fileContent = file_get_contents($uploadPath);
    $result = $s3->putObject($key, $fileContent, $contentType);

    if (empty($result['success'])) {
        @unlink($uploadPath);
        if ($useZip && $csvTmpPath && $csvTmpPath !== $uploadPath) {
            @unlink($csvTmpPath);
        }
        throw new RuntimeException(
            'S3 upload failed: ' . ($result['error'] ?? ('HTTP ' . ($result['http_code'] ?? '?') . ' — ' . ($result['response'] ?? '')))
        );
    }

    @unlink($uploadPath);
    if ($useZip && $csvTmpPath && $csvTmpPath !== $uploadPath) {
        @unlink($csvTmpPath);
    }

    $presignedUrl = $s3->getPresignedUrl($key, 3600);

    return [
        'key'  => $key,
        'link' => $presignedUrl,
        'name' => $uploadName,
    ];
}

/**
 * Upload an already-built ZIP file (e.g. multi-table cloud backup) to S3.
 * Unlike upload_zip_csv_to_s3(), this does not build the ZIP itself —
 * the caller is expected to have already created a valid .zip file on
 * disk (e.g. via ZipArchive with multiple CSVs inside) and just wants
 * it uploaded and cleaned up.
 *
 * @param string $localZipPath Absolute path to the existing .zip file
 * @param string $baseName     Desired base filename (without extension)
 *                              used for the S3 object name
 * @return array ['key' => ..., 'link' => ..., 'name' => ...]
 */
function upload_zip_file_to_s3(string $localZipPath, string $baseName): array
{
    if (!file_exists($localZipPath)) {
        throw new RuntimeException("ZIP file not found at path: $localZipPath");
    }

    $uploadName  = $baseName . '.zip';
    $contentType = 'application/zip';

    $s3  = get_s3_client();
    $key = 'backups/' . $uploadName;

    $fileContent = file_get_contents($localZipPath);
    if ($fileContent === false) {
        throw new RuntimeException("Failed to read ZIP file contents at path: $localZipPath");
    }

    $result = $s3->putObject($key, $fileContent, $contentType);

    if (empty($result['success'])) {
        @unlink($localZipPath);
        throw new RuntimeException(
            'S3 upload failed: ' . ($result['error'] ?? ('HTTP ' . ($result['http_code'] ?? '?') . ' — ' . ($result['response'] ?? '')))
        );
    }

    @unlink($localZipPath);

    $presignedUrl = $s3->getPresignedUrl($key, 3600);

    return [
        'key'  => $key,
        'link' => $presignedUrl,
        'name' => $uploadName,
    ];
}