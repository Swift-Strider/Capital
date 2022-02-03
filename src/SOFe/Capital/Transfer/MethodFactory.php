<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use SOFe\Capital\Config\Parser;
use SOFe\Capital\ParameterizedLabelSet;
use SOFe\Capital\Schema\Schema;

use function array_filter;
use function count;
use function strpos;
use function substr;

/**
 * Utilities for building methods that implement Method
 */
class MethodFactory {
    public static function buildCommand(Parser $parser, Schema $schema, ?DefaultCommand $default = null) : CommandMethod {
        $command = $parser->expectString("command", $default?->command ?? "transfer-command", <<<'EOT'
            This is the name of the command that will be run.
            EOT);
        if ($command === "") {
            $command = $parser->setValue("command", "transfer-command", "The command's name (key \"command\") must not be empty.");
        } elseif (($i = strpos($command, " ")) !== false) {
            $command = substr($command, 0, $i);
            $command = $parser->setValue("command", $command === "" ? "transfer-command" : $command, "The command's name (key \"command\") must not have spaces.");
        }

        $permission = $parser->expectString("permission", $default?->permission ?? "capital.transfer.unspecified", <<<'EOT'
            This is the permission players must have.
            It will be created for you.
            EOT);
        if ($permission === "") {
            $permission = $parser->setValue("permission", "capital.transfer.unspecified", "The command's permission (key \"permission\") must not be empty.");
        } elseif (($i = strpos($permission, " ")) !== false) {
            $permission = substr($permission, 0, $i);
            $permission = $parser->setValue("permission", $permission === "" ? "capital.transfer.unspecified" : $permission, "The command's permission (key \"permission\") must not have spaces.");
        }

        $defaultOpOnly = $parser->expectBool("default-op", $default?->defaultOpOnly ?? true, <<<'EOT'
            This requires the user of the command to have op permissions.
            EOT);

        $src = AccountTarget::parse($parser->enter("src", <<<'EOT'
            The "source" to take money from.
            EOT), $schema, $default?->src ?? AccountTarget::TARGET_SENDER);

        $dest = AccountTarget::parse($parser->enter("dest", <<<'EOT'
            The "destination" to give money to.
            EOT), $schema, $default?->dest ?? AccountTarget::TARGET_RECIPIENT);

        $rate = $parser->expectNumber("rate", $default?->rate ?? 1.0, <<<'EOT'
            The exchange rate, or how much of the original money is sent.
            When using "currency" schema, this allows transferring between
            accounts of different currencies.
            EOT);

        $minimumAmount = $parser->expectInt("minimum-amount", $default?->minimumAmount ?? 0, <<<'EOT'
            The minimum amount of money that can be transferred each time.
            EOT);

        $maximumAmount = $parser->expectInt("maximum-amount", $default?->maximumAmount ?? 0, <<<'EOT'
            The maximum amount of money that can be transferred each time.
            EOT);

        $transactionLabels = self::parseLabelSet($parser->enter("transaction-labels", <<<'EOT'
            These are labels to add to the transaction.
            You can match by these labels to identify
            how players earn and lose money.
            EOT));

        $messages = Messages::parse($parser->enter("messages", ""));

        return new CommandMethod($command, $permission, $defaultOpOnly, $src, $dest, $rate, $minimumAmount, $maximumAmount, $transactionLabels, $messages);
    }

    /**
     * @param array<string, string> $defaultEntries
     * @return ParameterizedLabelSet<ContextInfo>
     */
    private static function parseLabelSet(Parser $parser, $defaultEntries = []) : ParameterizedLabelSet
    {
        $names = array_filter($parser->getKeys(), fn($currency) => $currency[0] !== "#");
        if (count($names) === 0) {
            $entries = $defaultEntries;
            foreach ($defaultEntries as $name => $value) {
                $parser->expectString($name, $value, "");
            }
        } else {
            $entries = [];
            foreach ($parser->getKeys() as $name) {
                $entries[$name] = $parser->expectString($name, "", "");
            }
        }

        /** @var ParameterizedLabelSet<ContextInfo> $labelSet */
        $labelSet = new ParameterizedLabelSet($entries);
        return $labelSet;
    }
}
