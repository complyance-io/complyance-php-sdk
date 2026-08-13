<?php

namespace ComplyanceSDK;

use ComplyanceSDK\Enums\Environment;

/**
 * Converts the SDK's backwards-compatible request model to the public v3 Unify contract.
 */
final class UnifyV3RequestSerializer
{
    public static function serialize(
        UnifyRequest $request,
        Environment $environment,
        bool $debug = false
    ): array {
        $source = self::serializeSource($request->source ?? []);
        $documentType = self::serializeDocumentType($request);
        $purpose = self::enumCode($request->purpose ?? '');

        $body = [
            'debug' => $debug,
            'country' => strtoupper(trim((string)($request->country ?? ''))),
            'environment' => $environment->getCode() === Environment::PRODUCTION
                ? Environment::PRODUCTION
                : Environment::SANDBOX,
            'purpose' => strtolower($purpose),
            'ingestionMethod' => 'sdk',
        ];

        // Source is optional for mapping, but required by the API for invoicing.
        if ($source !== '') {
            $body['source'] = $source;
        }

        $body['documentType'] = $documentType;
        $payload = isset($request->payload) && is_array($request->payload)
            ? $request->payload
            : [];
        if (self::isList($payload) && $payload !== []) {
            throw new \InvalidArgumentException('The v3 Unify payload must be a JSON object.');
        }
        $body['payload'] = $payload === [] ? new \stdClass() : $payload;

        return $body;
    }

    private static function serializeSource($source): string
    {
        if (is_string($source)) {
            return trim($source);
        }

        if (!is_array($source)) {
            return '';
        }

        foreach (['identity', 'id'] as $key) {
            if (!isset($source[$key])) {
                continue;
            }

            $candidate = trim((string)$source[$key]);
            if ($candidate !== '' && trim($candidate, ':') !== '') {
                return $candidate;
            }
        }

        $name = trim((string)($source['name'] ?? ''));
        $version = trim((string)($source['version'] ?? ''));
        if ($name === '' && $version === '') {
            return '';
        }

        return $name . ':' . $version;
    }

    private static function serializeDocumentType(UnifyRequest $request): array
    {
        $documentType = isset($request->documentTypeV2) && is_array($request->documentTypeV2)
            ? $request->documentTypeV2
            : [];

        $base = trim((string)($documentType['base'] ?? ''));
        if ($base === '') {
            $base = trim((string)($request->documentTypeString ?? ''));
        }
        if ($base === '' && isset($request->documentType)) {
            $base = self::enumCode($request->documentType);
        }

        $result = [
            'base' => strtolower($base),
            'modifiers' => self::normalizeStringList($documentType['modifiers'] ?? []),
        ];

        $variant = trim((string)($documentType['variant'] ?? ''));
        if ($variant !== '') {
            $result['variant'] = strtolower($variant);
        }

        return $result;
    }

    private static function normalizeStringList($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];
        foreach ($values as $value) {
            $value = strtolower(trim((string)$value));
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * PHP 7.4-compatible equivalent of array_is_list().
     */
    private static function isList(array $value): bool
    {
        $expectedKey = 0;
        foreach ($value as $key => $unused) {
            if ($key !== $expectedKey) {
                return false;
            }
            $expectedKey++;
        }

        return true;
    }

    private static function enumCode($value): string
    {
        if (is_object($value) && method_exists($value, 'getCode')) {
            return trim((string)$value->getCode());
        }

        return trim((string)$value);
    }
}
