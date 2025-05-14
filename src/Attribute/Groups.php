<?php declare(strict_types=1);

namespace Qnix\RESTful\Attribute;

use Attribute;
use RuntimeException;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Groups
{
    /**
     * @param string[]  $groupsSerialize
     * @param string[]  $groupsReplacement
     * @param bool      $serializeNull
     */
    public function __construct(
        private array $groupsSerialize,
        private array $groupsReplacement = [],
        private bool $serializeNull = false,
    ) {
        if (empty($this->groupsSerialize)) {
            throw new RuntimeException(
                sprintf("Serialization groups in attribute: '%s' can't be empty.", static::class),
            );
        }

        foreach ($this->groupsSerialize as $group) {
            if (!is_string($group) || '' === $group) {
                throw new RuntimeException(
                    sprintf("Serialization groups in attribute: '%s' must be an array of non-empty strings.", static::class),
                );
            }
        }
        foreach ($this->groupsReplacement as $group) {
            if (!is_string($group) || '' === $group) {
                throw new RuntimeException(
                    sprintf("Replacement groups in attribute: '%s' must be an array of non-empty strings.", static::class),
                );
            }
        }
    }

    /**
     * @return string[]
     */
    public function getGroupsSerialize(): array
    {
        return $this->groupsSerialize;
    }

    /**
     * @return string[]
     */
    public function getGroupsReplacement(): array
    {
        return $this->groupsReplacement;
    }

    public function isSerializeNull(): bool
    {
        return $this->serializeNull;
    }
}