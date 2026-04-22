<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryService
{
    public function __construct(
        private readonly string $cloudName,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function uploadImageFile(UploadedFile $file, ?string $publicId = null, ?string $folder = null): array
    {
        $this->ensureConfigured();

        if (!is_readable($file->getPathname())) {
            throw new \RuntimeException('Uploaded file is not readable.');
        }

        $params = [
            'timestamp' => time(),
        ];

        if ($publicId) {
            $params['public_id'] = $publicId;
        }

        if ($folder) {
            $params['folder'] = $folder;
        }

        $params['api_key'] = $this->apiKey;
        $params['signature'] = $this->buildSignature($params);

        $postFields = [
            'file' => curl_file_create($file->getPathname(), $file->getClientMimeType() ?? 'application/octet-stream', $file->getClientOriginalName()),
            'timestamp' => $params['timestamp'],
            'api_key' => $this->apiKey,
            'signature' => $params['signature'],
        ];

        if (isset($params['public_id'])) {
            $postFields['public_id'] = $params['public_id'];
        }
        if (isset($params['folder'])) {
            $postFields['folder'] = $params['folder'];
        }

        $responseBody = $this->sendRequest($postFields);
        $response = json_decode($responseBody, true);

        if (!is_array($response)) {
            throw new \RuntimeException('Cloudinary returned an invalid response.');
        }

        if (isset($response['error'])) {
            throw new \RuntimeException($response['error']['message'] ?? 'Cloudinary upload failed.');
        }

        return $response;
    }

    private function buildSignature(array $params): string
    {
        unset($params['api_key']);
        ksort($params);

        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $key . '=' . $value;
        }

        return sha1(implode('&', $parts) . $this->apiSecret);
    }

    private function sendRequest(array $postFields): string
    {
        $endpoint = sprintf('https://api.cloudinary.com/v1_1/%s/image/upload', $this->cloudName);
        $ch = curl_init($endpoint);

        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize Cloudinary upload request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_SAFE_UPLOAD => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false || $curlError !== '') {
            $this->logger?->error('Cloudinary upload failed', ['error' => $curlError]);
            throw new \RuntimeException('Cloudinary request failed: ' . $curlError);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logger?->error('Cloudinary upload returned HTTP error', ['code' => $httpCode, 'body' => $responseBody]);
            throw new \RuntimeException('Cloudinary returned HTTP status ' . $httpCode);
        }

        return $responseBody;
    }

    private function ensureConfigured(): void
    {
        if (trim($this->cloudName) === '' || trim($this->apiKey) === '' || trim($this->apiSecret) === '') {
            throw new \RuntimeException('Cloudinary credentials are not configured. Please set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET.');
        }
    }
}
