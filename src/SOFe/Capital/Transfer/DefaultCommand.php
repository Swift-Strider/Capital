<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

class DefaultCommand {
    /**
     * @phpstan-param AccountTarget::TARGET_* $src
     * @phpstan-param AccountTarget::TARGET_* $dest
     * @param array<string, string> $transactionLabels
     */
    public function __construct(
        public string $command,
        public string $permission,
        public bool $defaultOpOnly,
        public string $src,
        public string $dest,
        public float $rate,
        public int $minimumAmount,
        public int $maximumAmount,
        public array $transactionLabels,
        public Messages $messages,
    ) {}
}
