<?php

namespace App\Enums;

enum MessageKind: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case File = 'file';
}
