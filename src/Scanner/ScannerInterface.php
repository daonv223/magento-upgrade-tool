<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Scanner;

interface ScannerInterface
{
    /**
     * @return Finding[]
     */
    public function scan(string $filePath): array;
}
