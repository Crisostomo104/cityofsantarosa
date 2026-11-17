<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  S3Uploader — pure cURL + manual SigV4, walang aws-sdk-php
 * ─────────────────────────────────────────────────────────────
 */
class S3Uploader
{
    private $key;
    private $secret;
    private $region;
    private $bucket;
    private $endpoint;

    public function __construct($key, $secret, $region, $bucket)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->region = $region;
        $this->bucket = $bucket;
        $this->endpoint = "s3.$region.amazonaws.com";
    }

    public function uploadFile($localFilePath, $s3Key, $contentType = 'application/octet-stream')
    {
        $fileContent = file_get_contents($localFilePath);
        return $this->putObject($s3Key, $fileContent, $contentType);
    }

    public function putObject($s3Key, $fileContent, $contentType = 'application/octet-stream')
    {
        $method = 'PUT';
        $service = 's3';
        $host = "{$this->bucket}.{$this->endpoint}";
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $fileContent);

        $canonicalUri = $this->uriEncode($s3Key);
        $canonicalHeaders = "host:$host\n" .
                            "x-amz-content-sha256:$payloadHash\n" .
                            "x-amz-date:$amzDate\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = "$method\n$canonicalUri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "$dateStamp/{$this->region}/$service/aws4_request";
        $stringToSign = "$algorithm\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $signingKey = $this->getSignatureKey($dateStamp, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorizationHeader = "$algorithm Credential={$this->key}/$credentialScope, " .
                                "SignedHeaders=$signedHeaders, Signature=$signature";

        $url = "https://$host$canonicalUri";

        $headers = [
            "Host: $host",
            "X-Amz-Content-Sha256: $payloadHash",
            "X-Amz-Date: $amzDate",
            "Authorization: $authorizationHeader",
            "Content-Type: $contentType",
            "Content-Length: " . strlen($fileContent),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'url' => $url,
                'http_code' => $httpCode,
            ];
        }

        return ['success' => false, 'http_code' => $httpCode, 'response' => $response];
    }

    public function getPresignedUrl($s3Key, $expiresInSeconds = 3600)
    {
        $method = 'GET';
        $service = 's3';
        $host = "{$this->bucket}.{$this->endpoint}";
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $credentialScope = "$dateStamp/{$this->region}/$service/aws4_request";

        $canonicalUri = $this->uriEncode($s3Key);

        $queryParams = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => "{$this->key}/$credentialScope",
            'X-Amz-Date'          => $amzDate,
            'X-Amz-Expires'       => (string) $expiresInSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];

        ksort($queryParams);

        $canonicalQueryStringParts = [];
        foreach ($queryParams as $k => $v) {
            $canonicalQueryStringParts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $canonicalQueryString = implode('&', $canonicalQueryStringParts);

        $canonicalHeaders = "host:$host\n";
        $signedHeaders = 'host';
        $payloadHash = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = "$method\n$canonicalUri\n$canonicalQueryString\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        $algorithm = 'AWS4-HMAC-SHA256';
        $stringToSign = "$algorithm\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $signingKey = $this->getSignatureKey($dateStamp, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $finalUrl = "https://$host$canonicalUri?$canonicalQueryString&X-Amz-Signature=$signature";

        return $finalUrl;
    }

    private function uriEncode($key)
    {
        $key = ltrim($key, '/');
        $parts = explode('/', $key);
        $encodedParts = array_map('rawurlencode', $parts);
        return '/' . implode('/', $encodedParts);
    }

    private function getSignatureKey($dateStamp, $service)
    {
        $kDate = hash_hmac('sha256', $dateStamp, "AWS4{$this->secret}", true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return $kSigning;
    }
}