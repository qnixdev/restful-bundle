<?php declare(strict_types=1);

namespace Qnix\RESTful\Attribute;

use Attribute;
use RuntimeException;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Field
{
    private const array TYPE_LIST = [
        'string',
        'string_i18n',
        'date',
        'datetime',
        'time',
        'int',
        'float',
        'bool',
        'file',
        'enum',
        'object',
        'array',
        'array_string',
        'array_int',
        'array_file',
        'array_enum',
        'array_object',
        'array_object_xml',
        'array_object_i18n',
    ];

    public function __construct(
        private ?string $name = null,
        private string $type = 'string',
        private ?string $itemType = null,
        private bool $isOptional = false,
        private ?string $dateFormat = null,
        private ?string $xmlArrayField = null,
    ) {
        if (!in_array($type, self::TYPE_LIST)) {
            throw new RuntimeException(
                sprintf("Type '%s' in attribute: '%s' is not supported.", $type, static::class),
            );
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getItemType(): ?string
    {
        return $this->itemType;
    }

    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    public function getXmlArrayField(): ?string
    {
        return $this->xmlArrayField;
    }
}