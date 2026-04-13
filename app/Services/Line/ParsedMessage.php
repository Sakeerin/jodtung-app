<?php

namespace App\Services\Line;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Shortcut;

/**
 * Data class for parsed message results.
 */
class ParsedMessage
{
    public function __construct(
        public readonly string          $type,
        public readonly ?string         $command = null,
        public readonly ?string         $commandArgument = null,
        public readonly ?string         $connectionCode = null,
        public readonly ?string         $keyword = null,
        public readonly ?float          $amount = null,
        public readonly ?string         $note = null,
        public readonly ?TransactionType $transactionType = null,
        public readonly ?Category       $category = null,
        public readonly ?Shortcut       $shortcut = null,
        public readonly ?string         $rawMessage = null,
    ) {}

    public function isCommand(): bool
    {
        return $this->type === MessageParser::TYPE_COMMAND;
    }

    public function isConnectionCode(): bool
    {
        return $this->type === MessageParser::TYPE_CONNECTION_CODE;
    }

    public function isTransaction(): bool
    {
        return $this->type === MessageParser::TYPE_TRANSACTION;
    }

    public function isUnknown(): bool
    {
        return $this->type === MessageParser::TYPE_UNKNOWN;
    }

    public function isIncome(): bool
    {
        return $this->isTransaction() && $this->transactionType === TransactionType::INCOME;
    }

    public function isExpense(): bool
    {
        return $this->isTransaction() && $this->transactionType === TransactionType::EXPENSE;
    }
}
