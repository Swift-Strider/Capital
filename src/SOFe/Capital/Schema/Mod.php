<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use Generator;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\ModInterface;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(Context $context) : Generator {
        false && yield;

        $context->call(function(TypeRegistry $registry) {
            $registry->register("basic", Basic::class);
            $registry->register("currency", Currency::class);
        });
    }

    public static function shutdown(Context $context) : void {
    }
}