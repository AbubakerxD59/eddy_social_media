<?php

namespace App\Enums;

enum SignalType: string
{
    case Drop = 'drop';
    case Need = 'need';
    case Opportunity = 'opportunity';
    case Poll = 'poll';

    public function label(): string
    {
        return match ($this) {
            self::Drop => 'Drop',
            self::Need => 'Need',
            self::Opportunity => 'Opportunity',
            self::Poll => 'Poll',
        };
    }

    public function allowsMedia(): bool
    {
        return $this !== self::Poll;
    }

    public function allowsLink(): bool
    {
        return $this === self::Drop;
    }

    public function liveMessage(): string
    {
        return match ($this) {
            self::Drop => 'Your drop is live.',
            self::Need => 'Your need is live.',
            self::Opportunity => 'Your opportunity is live.',
            self::Poll => 'Your poll is live.',
        };
    }
}
