<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SentinelEncryptionService
{
    protected string $vaultPath;

    public function __construct()
    {
        $this->vaultPath = storage_path('app/sentinel-vault');

        if (!is_dir($this->vaultPath)) {
            mkdir($this->vaultPath, 0750, true);
        }
    }

    public function encryptText(string $plainText): string
    {
        return Crypt::encryptString($plainText);
    }

    public function decryptText(string $encryptedText): string
    {
        return Crypt::decryptString($encryptedText);
    }

    public function encryptArray(array $data): string
    {
        return Crypt::encryptString(json_encode($data));
    }

    public function decryptArray(string $payload): array
    {
        $json = Crypt::decryptString($payload);

        return json_decode($json, true) ?: [];
    }

    public function encryptUploadedFile(UploadedFile $file): string
    {
        $originalName = $file->getClientOriginalName();
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = $file->getClientOriginalExtension();

        $content = file_get_contents($file->getRealPath());

        if ($content === false) {
            throw new \Exception('Unable to read uploaded file.');
        }

        $encrypted = Crypt::encryptString(base64_encode($content));

        $fileName = now()->format('Ymd_His') . '_' . $safeName . '.' . $extension . '.encrypted';

        $path = $this->vaultPath . '/' . $fileName;

        file_put_contents($path, $encrypted);

        chmod($path, 0640);

        return 'sentinel-vault/' . $fileName;
    }

    public function decryptVaultFile(string $relativePath): string
    {
        $path = storage_path('app/' . ltrim($relativePath, '/'));

        if (!file_exists($path)) {
            throw new \Exception('Encrypted file not found.');
        }

        $encrypted = file_get_contents($path);

        if ($encrypted === false) {
            throw new \Exception('Unable to read encrypted file.');
        }

        return base64_decode(Crypt::decryptString($encrypted));
    }
}