<?php declare(strict_types=1);

namespace Qnix\RESTful\Infrastructure\Transformer;

use Qnix\RESTful\Attribute\Field;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class RequestTransformer
{
    private const string XML = '@attributes';

    /**
     * @throws Exception\ApiMissingFieldException
     * @throws Exception\ApiWrongDataException
     * @throws \ReflectionException
     */
    public function transform(string $className, array $args, bool $isList = false): mixed
    {
        if ($className === '') {
            return null;
        }

        $refClass = new \ReflectionClass($className);

        if ($isList) {
            foreach ($args as $data) {
                $response[] = $this->generateObject($refClass, $data);
            }

            return $response ?? [];
        }

        return $this->generateObject($refClass, $args);
    }

    /**
     * @throws Exception\ApiMissingFieldException
     * @throws Exception\ApiWrongDataException
     * @throws \ReflectionException
     */
    private function generateObject(\ReflectionClass $refClass, array $args): ?object
    {
        $requestObject = $refClass->newInstanceWithoutConstructor();

        foreach ($refClass->getProperties() as $prop) {
            $propAttribute = $prop->getAttributes(Field::class)[0] ?? null;
            /** @var Field|null  $instance */
            $instance = $propAttribute?->newInstance();

            if (null === $instance) {
                continue;
            }

            $fieldName = $instance->getName() ?? $prop->getName();

            if (!isset($args[$fieldName]) && !isset($args[self::XML][$fieldName])) {
                if (!$instance->isOptional()) {
                    throw new Exception\ApiMissingFieldException($fieldName);
                }

                continue;
            }

            $prop->setValue($requestObject, match ($instance->getType()) {
                'object' => $this->mapArrayToObject(
                    $instance,
                    $args[$fieldName] ?? $args[self::XML][$fieldName],
                ),
                'array_object' => $this->mapArrayObjectList(
                    $instance,
                    $args[$fieldName] ?? $args[self::XML][$fieldName],
                ),
                'array_object_xml' => $this->mapXmlObjectList(
                    $instance,
                    $args[$fieldName] ?? $args[self::XML][$fieldName],
                    $instance->getXmlArrayField(),
                ),
                'array_object_i18n' => $this->mapAssociativeArrayObjectList(
                    $instance,
                    $args[$fieldName] ?? $args[self::XML][$fieldName],
                ),
                default => $this->mapValue(
                    $instance,
                    $args[$fieldName] ?? $args[self::XML][$fieldName],
                )
            });
        }

        return $requestObject;
    }

    /**
     * @throws Exception\ApiMissingFieldException
     * @throws Exception\ApiWrongDataException
     * @throws \ReflectionException
     */
    private function mapArrayToObject(Field $instance, mixed $data)
    {
        if (null === $instance->getItemType()) {
            throw new Exception\ApiWrongDataException(
                "An 'itemType' parameter is expected for field with type: 'object'.",
            );
        }
        if (!is_array($data)) {
            throw new Exception\ApiWrongDataException(
                sprintf("%s must be an array.", $instance->getName() ?? ''),
            );
        }

        return $this->transform($instance->getItemType(), $data);
    }

    /**
     * @throws Exception\ApiMissingFieldException
     * @throws Exception\ApiWrongDataException
     * @throws \ReflectionException
     */
    private function mapArrayObjectList(Field $instance, mixed $data): array
    {
        if (null === $instance->getItemType()) {
            throw new Exception\ApiWrongDataException(
                "An 'itemType' parameter is expected for field with type: 'array_object'.",
            );
        }

        $response = [];

        foreach ($data as $value) {
            if (!is_array($value)) {
                throw new Exception\ApiWrongDataException(
                    "$value must be an array.",
                );
            }

            $response[] = $this->transform($instance->getItemType(), $value);
        }

        return $response;
    }

    /**
     * @throws Exception\ApiMissingFieldException
     * @throws Exception\ApiWrongDataException
     * @throws \ReflectionException
     */
    private function mapXmlObjectList(Field $instance, mixed $data, ?string $fieldName): array
    {
        if (null === $instance->getItemType()) {
            throw new Exception\ApiWrongDataException(
                "An 'itemType' parameter is expected for field with type: 'array_object_xml'.",
            );
        }
        if (null === $fieldName) {
            throw new Exception\ApiWrongDataException(
                "An 'xmlArrayField' parameter is expected for field with type: 'array_object_xml'.",
            );
        }

        $args = $data[$fieldName] ?? [];
        $response = [];

        foreach ($args as $key => $value) {
            if (!is_array($value)) {
                throw new Exception\ApiWrongDataException(
                    "$value must be an array.",
                );
            }
            if ($key === self::XML) {
                $response[] = $this->transform($instance->getItemType(), $args);

                break;
            }

            $response[] = $this->transform($instance->getItemType(), $value);
        }

        return $response;
    }

    /**
     * @throws Exception\ApiMissingFieldException
     * @throws Exception\ApiWrongDataException
     * @throws \ReflectionException
     */
    private function mapAssociativeArrayObjectList(Field $instance, array $data): array
    {
        if (null === $instance->getItemType()) {
            throw new Exception\ApiWrongDataException(
                "An 'itemType' parameter is expected for field with type: 'array_object_i18n'.",
            );
        }

        // remove war language
        unset($data['ru']);
        $response = [];

        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                throw new Exception\ApiWrongDataException(
                    "$value must be an array.",
                );
            }

            $response[$key] = $this->transform($instance->getItemType(), $value);
        }

        return $response;
    }

    /**
     * @throws Exception\ApiWrongDataException
     */
    private function mapValue(Field $instance, mixed $value): mixed
    {
        $type = $instance->getType();

        switch ($type) {
            case 'string':
                if (is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                $value = trim((string) $value);

                break;
            case 'string_i18n':
                if (!is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("'%s' should be array with languages key, '%s' given.", $instance->getName() ?? '', gettype($value)),
                    );
                }

                // remove war language
                unset($value['ru']);
                $response = [];

                foreach ($value as $langKey => $item) {
                    if (!is_string($item)) {
                        throw new Exception\ApiWrongDataException(
                            sprintf("Invalid type value. Expected type: 'string', '%s' given.", gettype($value)),
                        );
                    }
                    if (!is_string($langKey)) {
                        throw new Exception\ApiWrongDataException(
                            sprintf("Invalid type value. Expected key: 'string', '%s' given.", gettype($value)),
                        );
                    }

                    $response[strtolower($langKey)] = trim($item);
                }

                $value = $response;

                break;
            case 'date':
                if (empty($value)) {
                    $value = null;

                    break;
                }
                if (!is_string($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                $dateFormat = $instance->getDateFormat() ?? 'Y-m-d';
                $datetime = \DateTime::createFromFormat($dateFormat, preg_replace('/\s+/', '', $value));

                if (!($datetime instanceof \DateTime)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid date format. Expected format: '%s', '%s' given.", $dateFormat, $value),
                    );
                }

                $value = $datetime->setTime(0, 0);

                break;
            case 'datetime':
                if (empty($value)) {
                    $value = null;

                    break;
                }
                if (!is_string($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                $dateFormat = $instance->getDateFormat() ?? 'Y-m-d H:i:s';
                $datetime = \DateTime::createFromFormat($dateFormat, preg_replace('/\s+/', ' ', $value));

                if (!($datetime instanceof \DateTime)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid datetime format. Expected format: '%s', '%s' given.", $dateFormat, $value),
                    );
                }

                $value = $datetime;

                break;
            case 'time':
                if (empty($value)) {
                    $value = null;

                    break;
                }
                if (!is_string($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                $timestamp = strtotime(preg_replace('/\s+/', '', $value));

                if (!$timestamp) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid time format. Expected format: 'H:i', '%s' given.", $value),
                    );
                }

                $value = $timestamp;

                break;
            case 'int':
                if (is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                $value = (int) $value;

                break;
            case 'float':
                if (is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                $value = (float) $value;

                break;
            case 'bool':
                if (is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }
                if (is_bool($value)) {
                    return $value;
                }

                $value = !preg_match('/^false$/i', $value) && (bool) $value;

                break;
            case 'file':
                if (!$value instanceof UploadedFile) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                break;
            case 'enum':
                if (is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                $enum = $instance->getItemType() ?? '';

                if (!is_subclass_of($enum,\BackedEnum::class)) {
                    throw new Exception\ApiWrongDataException(
                        "Some of 'BackedEnum::class' itemType is expected for field with type: 'enum'.",
                    );
                }

                $value = $enum::from($value);

                break;
            case 'array':
                if (!is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                break;
            case 'array_string':
                if (!is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                /** @var string[]  $value */
                $value = array_reduce($value, static function (array $res, mixed $item) {
                    if (is_array($item)) {
                        throw new Exception\ApiWrongDataException(
                            sprintf("Invalid type of value. Expected type: 'string', '%s' given.", gettype($item)),
                        );
                    }

                    $res[] = trim((string) $item);

                    return $res;
                }, []);

                break;
            case 'array_int':
                if (!is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                /** @var int[]  $value */
                $value = array_reduce($value, static function (array $res, mixed $item) {
                    if (is_array($item)) {
                        throw new Exception\ApiWrongDataException(
                            sprintf("Invalid type of value. Expected type: 'int', '%s' given.", gettype($item)),
                        );
                    }

                    $res[] = (int) $item;

                    return $res;
                }, []);

                break;
            case 'array_file':
                if (!is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                /** @var UploadedFile[]  $value */
                $value = array_reduce($value, static function (array $res, mixed $item) {
                    if (!$item instanceof UploadedFile) {
                        throw new Exception\ApiWrongDataException(
                            sprintf("Invalid type of value. Expected type: 'file', '%s' given.", gettype($item)),
                        );
                    }

                    $res[] = $item;

                    return $res;
                }, []);

                break;
            case 'array_enum':
                if (!is_array($value)) {
                    throw new Exception\ApiWrongDataException(
                        sprintf("Invalid type of value. Expected type: '%s', '%s' given.", $type, gettype($value)),
                    );
                }

                $enum = $instance->getItemType() ?? '';

                if (!is_subclass_of($enum, \BackedEnum::class)) {
                    throw new Exception\ApiWrongDataException(
                        "Some of 'BackedEnum::class' itemType is expected for field with type: 'array_enum'.",
                    );
                }

                /** @var \BackedEnum[]  $value */
                $value = array_reduce($value, static function (array $res, mixed $item) use ($enum) {
                    if (is_array($item)) {
                        throw new Exception\ApiWrongDataException(
                            sprintf("Invalid type of value. Expected type: 'enum', '%s' given.", gettype($item)),
                        );
                    }

                    $res[] = $enum::from($item);

                    return $res;
                }, []);

                break;
            default:
                throw new Exception\ApiWrongDataException(
                    "Type: '$type' not supported.",
                );
        }

        return $value;
    }
}