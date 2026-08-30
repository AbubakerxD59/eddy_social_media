<?php

namespace App\Enums;

enum SignalType: string
{
    case Quote = 'quote';
    case Images = 'images';
    case Video = 'video';
    case Link = 'link';
}
