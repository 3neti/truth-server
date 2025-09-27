<?php

namespace TruthRenderer\Validation;

use JsonSchema\Constraints\Factory as ConstraintsFactory;
use JsonSchema\Validator as JsonSchemaValidator;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\SchemaStorage;

class Validator
{
    /**
     * Validate input data against a JSON Schema.
     *
     * @param array|object      $data   The input payload (assoc array or object)
     * @param array|object|null $schema The JSON schema (assoc array or object). If null, no-op.
     *
     * @throws \RuntimeException When validation fails
     */
    public function validate(array|object $data, array|object|null $schema): void
    {
        if ($schema === null) {
            // Nothing to validate
            return;
        }

        // Coerce arrays → objects (recursively), which the validator expects.
        $dataObj   = $this->toObject($data);
        $schemaObj = $this->toObject($schema);

        // Set up validator
        $schemaStorage = new SchemaStorage(new UriRetriever());
        $factory       = new ConstraintsFactory($schemaStorage);
        $validator     = new JsonSchemaValidator($factory);

        // Validate
        $validator->validate($dataObj, $schemaObj);

        if (!$validator->isValid()) {
            $messages = array_map(
                static fn(array $e) => sprintf('%s: %s', $e['property'] ?: '(root)', $e['message']),
                $validator->getErrors()
            );
            $msg = "Schema validation failed:\n - " . implode("\n - ", $messages);
            throw new \RuntimeException($msg);
        }
    }

    private function toObject(mixed $value): mixed
    {
        if (is_array($value)) {
            // Keep list arrays as arrays (JSON Schema expects arrays for keywords like "required", "enum", etc.)
            if (array_is_list($value)) {
                // Recurse into elements but keep array shape
                foreach ($value as $i => $v) {
                    $value[$i] = $this->toObject($v);
                }
                return $value;
            }

            // Associative array → stdClass, recurse into values
            $obj = new \stdClass();
            foreach ($value as $k => $v) {
                $obj->{$k} = $this->toObject($v);
            }
            return $obj;
        }

        if (is_object($value)) {
            foreach (get_object_vars($value) as $k => $v) {
                $value->{$k} = $this->toObject($v);
            }
        }

        return $value;
    }
    /**
     * Recursively convert arrays to stdClass objects; leave scalars/objects as is.
     */
//    private function toObject(mixed $value): mixed
//    {
//        if (is_array($value)) {
//            // Distinguish list vs assoc; both become objects for the validator
//            // but we preserve nested values recursively.
//            $obj = new \stdClass();
//            foreach ($value as $k => $v) {
//                // numeric keys become string keys on stdClass — OK for schema validator
//                $obj->{$k} = $this->toObject($v);
//            }
//            return $obj;
//        }
//
//        if (is_object($value)) {
//            foreach (get_object_vars($value) as $k => $v) {
//                $value->{$k} = $this->toObject($v);
//            }
//        }
//
//        return $value;
//    }
}
