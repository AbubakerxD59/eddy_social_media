<?php

namespace App\Enums;

enum UserType: string
{
    case Business = 'business';
    case Talent = 'talent';
    case Explorer = 'explorer';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Business',
            self::Talent => 'Talent',
            self::Explorer => 'Explorer',
        };
    }

    /**
     * @return list<SignalType>
     */
    public function composeTypes(): array
    {
        return match ($this) {
            self::Business => SignalType::cases(),
            self::Talent => [SignalType::Drop, SignalType::Opportunity, SignalType::Poll],
            self::Explorer => [SignalType::Drop, SignalType::Poll],
        };
    }

    public function canCompose(SignalType $type, bool $isReply = false): bool
    {
        if ($isReply) {
            return true;
        }

        return in_array($type, $this->composeTypes(), true);
    }
}
