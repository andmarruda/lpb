<?php
namespace Andmarruda\Lpb\Validation;

use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use stdClass;

/**
 * Validates widget configs against their JSON Schema 2020-12 with
 * opis/json-schema in standard-compliant mode (the same rules Ajv applies
 * in the editor).
 */
final class ConfigValidator
{
    private const MAX_ERRORS = 20;

    private CompliantValidator $validator;

    private ErrorFormatter $formatter;

    public function __construct()
    {
        $this->validator = new CompliantValidator(null, self::MAX_ERRORS, false);
        $this->formatter = new ErrorFormatter();
    }

    /**
     * @param array $config
     * @param array $schema JSON Schema document
     * @param string $path JSON pointer of the config, prefixed to error paths
     * @return array<int, array{path: string, message: string}>
     */
    public function validate(array $config, array $schema, string $path = ''): array
    {
        $errors = $this->reservedReferences($config, $path);

        $schemaObject = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $result = $this->validator->validate(self::toJsonValue($config, $schema, true), $schemaObject);
        if (!$result->isValid()) {
            array_push($errors, ...$this->collect($result->error(), $path));
        }

        return $errors;
    }

    /**
     * PHP arrays as JSON values: lists stay arrays, maps become objects, and
     * an empty array becomes an object where the schema expects one (PHP
     * decodes {} and [] alike).
     *
     * @param mixed $value
     * @param mixed $schema The (sub)schema describing the value
     * @param bool $object Treat an empty array as an object
     * @return mixed
     */
    public static function toJsonValue(mixed $value, mixed $schema, bool $object = false): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $schema = is_array($schema) ? $schema : [];
        $types = (array) ($schema['type'] ?? []);
        $expectsObject = $object || in_array('object', $types, true) || isset($schema['properties']);

        if ($value === [] ? !$expectsObject : array_is_list($value)) {
            return array_map(fn ($item) => self::toJsonValue($item, $schema['items'] ?? []), $value);
        }

        $result = new stdClass();
        foreach ($value as $key => $item) {
            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            $propertySchema = $properties[$key] ?? (is_array($schema['additionalProperties'] ?? null) ? $schema['additionalProperties'] : []);
            $result->{$key} = self::toJsonValue($item, $propertySchema);
        }
        return $result;
    }

    /**
     * Leaf errors with their JSON pointers.
     *
     * opis reports properties that failed their own subschema as
     * "additional" too; those are dropped from additionalProperties errors.
     *
     * @param ValidationError $error
     * @param string $path
     * @return array
     */
    private function collect(ValidationError $error, string $path): array
    {
        if ($error->subErrors() !== []) {
            $errors = [];
            foreach ($error->subErrors() as $sub) {
                array_push($errors, ...$this->collect($sub, $path));
            }
            return $errors;
        }

        $at = $path . $this->formatter->formatErrorKey($error);
        $at = $at === '/' ? '' : rtrim($at, '/');

        if ($error->keyword() === 'additionalProperties') {
            $declared = array_keys((array) ($error->schema()->info()->data()->properties ?? []));
            $unknown = array_values(array_diff($error->args()['properties'] ?? [], $declared));
            return $unknown === [] ? [] : [['path' => $at, 'message' => 'Unknown properties: ' . implode(', ', $unknown) . '.']];
        }

        return [['path' => $at, 'message' => $this->formatter->formatErrorMessage($error) . '.']];
    }

    /**
     * $dynamic references are reserved until dynamic data is specified.
     *
     * @param mixed $value
     * @param string $path
     * @return array
     */
    private function reservedReferences(mixed $value, string $path): array
    {
        if (!is_array($value)) {
            return [];
        }

        $errors = [];
        foreach ($value as $key => $item) {
            if ($key === '$dynamic') {
                $errors[] = ['path' => JsonPointer::append($path, $key), 'message' => '$dynamic references are not supported yet.'];
                continue;
            }
            array_push($errors, ...$this->reservedReferences($item, JsonPointer::append($path, $key)));
        }
        return $errors;
    }
}
