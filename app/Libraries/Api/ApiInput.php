<?php

declare(strict_types=1);

namespace App\Libraries\Api;

use CodeIgniter\HTTP\RequestInterface;
use JsonException;
use stdClass;

/**
 * Reusable, fail-closed JSON input parser for future authenticated modules.
 * No P1 business endpoint accepts mutations.
 */
final class ApiInput
{
    /**
     * @return array{ok: bool, data: array<string, mixed>, errors: array<string, string>}
     */
    public static function jsonObject(RequestInterface $request, int $maxBytes = 65536): array
    {
        $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]));
        if ($contentType !== 'application/json') {
            return self::invalid('content_type', 'Content-Type must be application/json.');
        }

        $body = $request->getBody();
        if (strlen($body) > $maxBytes) {
            return self::invalid('body', 'JSON request body exceeds the allowed size.');
        }

        try {
            $decoded = json_decode($body, false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return self::invalid('body', 'Request body must contain valid JSON.');
        }

        if (! $decoded instanceof stdClass) {
            return self::invalid('body', 'Request body must be a JSON object.');
        }

        return ['ok' => true, 'data' => (array) $decoded, 'errors' => []];
    }

    /**
     * Validates mandatory text fields without accepting undeclared fields.
     *
     * @param array<string, mixed> $payload
     * @param array<string, int> $fields Map of field name to maximum length.
     * @return array<string, string>
     */
    public static function requiredTextFields(array $payload, array $fields): array
    {
        $errors = [];

        foreach ($fields as $field => $maxLength) {
            $value = $payload[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                $errors[$field] = 'This field is required.';
            } elseif (mb_strlen(trim($value), 'UTF-8') > $maxLength) {
                $errors[$field] = 'This field is too long.';
            }
        }

        foreach (array_keys($payload) as $field) {
            if (! array_key_exists($field, $fields)) {
                $errors[$field] = 'This field is not accepted.';
            }
        }

        return $errors;
    }

    /**
     * @return array{ok: false, data: array<string, mixed>, errors: array<string, string>}
     */
    private static function invalid(string $field, string $message): array
    {
        return ['ok' => false, 'data' => [], 'errors' => [$field => $message]];
    }
}
