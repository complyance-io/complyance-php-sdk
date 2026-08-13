<?php

namespace ComplyanceSDK;

/**
 * Converts the typed SDK request into the authoritative legacy batch contract.
 */
final class UnifyLegacyRequestSerializer
{
    public static function serialize(UnifyRequest $request): array
    {
        $country = self::requireNonEmptyString($request->country, 'country');
        $purpose = self::requireEnumCode($request->purpose, 'purpose');
        $environment = self::requireNonEmptyString($request->env, 'env');
        if (!in_array(strtolower($environment), ['sandbox', 'simulation', 'production', 'prod'], true)) {
            throw new \InvalidArgumentException(
                'The legacy Unify env must be sandbox, simulation, production, or prod.'
            );
        }

        if (!isset($request->source) || !is_array($request->source)) {
            throw new \InvalidArgumentException(
                'The typed legacy Unify source must contain explicit name and version fields.'
            );
        }
        $source = self::serializeSource($request->source);
        $documentType = self::serializeDocumentType($request);
        if (!isset($request->payload) || !is_array($request->payload)) {
            throw new \InvalidArgumentException('The legacy Unify invoice payload must be a JSON object.');
        }
        $payload = $request->payload;

        if (self::isList($payload) && $payload !== []) {
            throw new \InvalidArgumentException('The legacy Unify invoice payload must be a JSON object.');
        }

        $defaults = [
            'country' => strtoupper($country),
            'logicalDocumentType' => $documentType,
            'source' => $source,
        ];

        if (isset($request->destinations)) {
            if (!is_array($request->destinations)) {
                throw new \InvalidArgumentException('The legacy Unify destinations field must be an array.');
            }
            if ($request->destinations !== []) {
                $defaults['destinations'] = $request->destinations;
            }
        }

        return [
            'action' => 'submit',
            'purpose' => strtolower($purpose),
            'env' => strtolower($environment),
            'defaults' => $defaults,
            'invoices' => [
                ['payload' => $payload === [] ? new \stdClass() : $payload],
            ],
        ];
    }

    private static function serializeSource(array $source): array
    {
        if (!array_key_exists('name', $source) || !array_key_exists('version', $source)) {
            throw new \InvalidArgumentException(
                'The typed legacy Unify source requires explicit name and version fields.'
            );
        }

        return [
            'name' => self::requireNonEmptyString($source['name'], 'source.name'),
            'version' => self::requireNonEmptyString($source['version'], 'source.version'),
        ];
    }

    private static function serializeDocumentType(UnifyRequest $request): array
    {
        if (isset($request->documentTypeV2)) {
            if (!is_array($request->documentTypeV2)) {
                throw new \InvalidArgumentException('The legacy logical document type must be an object.');
            }

            $documentType = $request->documentTypeV2;
            if (!array_key_exists('base', $documentType)) {
                throw new \InvalidArgumentException('The legacy logical document type requires base.');
            }
            $result = [
                'base' => strtolower(self::requireNonEmptyString($documentType['base'], 'documentType.base')),
            ];
            if (array_key_exists('modifiers', $documentType)) {
                $result['modifiers'] = self::normalizeStringList($documentType['modifiers']);
            }
            if (array_key_exists('variant', $documentType)) {
                if (!is_string($documentType['variant'])) {
                    throw new \InvalidArgumentException(
                        'The legacy documentType.variant field must be a string.'
                    );
                }
                $variant = trim($documentType['variant']);
                if ($variant !== '') {
                    $result['variant'] = strtolower($variant);
                }
            }

            return $result;
        }

        if (isset($request->documentTypeString)) {
            return [
                'base' => strtolower(
                    self::requireNonEmptyString($request->documentTypeString, 'documentTypeString')
                ),
            ];
        }

        if (isset($request->documentType)) {
            return [
                'base' => strtolower(self::requireEnumCode($request->documentType, 'documentType')),
            ];
        }

        throw new \InvalidArgumentException('The typed legacy Unify request requires a document type.');
    }

    private static function normalizeStringList($values): array
    {
        if (!is_array($values)) {
            throw new \InvalidArgumentException('The legacy documentType.modifiers field must be an array.');
        }

        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException(
                    'Each legacy documentType modifier must be a string.'
                );
            }
            $candidate = strtolower(trim($value));
            if ($candidate !== '') {
                $normalized[] = $candidate;
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function requireEnumCode($value, string $field): string
    {
        if (is_object($value) && method_exists($value, 'getCode')) {
            return self::requireNonEmptyString($value->getCode(), $field);
        }

        return self::requireNonEmptyString($value, $field);
    }

    private static function requireNonEmptyString($value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("The typed legacy Unify {$field} field is required.");
        }

        return trim($value);
    }

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
}
