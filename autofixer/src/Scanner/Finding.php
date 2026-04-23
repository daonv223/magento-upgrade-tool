<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Scanner;

final class Finding
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $severity,
        public readonly string $file,
        public readonly int $line,
        public readonly string $description,
        public readonly array $context = [],
        public readonly ?string $className = null,
        public readonly ?string $phpVersion = null,
    ) {
    }

    public function toArray(): array
    {
        $result = [
            'rule_id' => $this->ruleId,
            'severity' => $this->severity,
            'file' => $this->file,
            'line' => $this->line,
            'description' => $this->description,
            'context' => $this->context,
        ];

        if ($this->className !== null) {
            $result['class'] = $this->className;
        }

        if ($this->phpVersion !== null) {
            $result['php_version'] = $this->phpVersion;
        }

        return $result;
    }
}
