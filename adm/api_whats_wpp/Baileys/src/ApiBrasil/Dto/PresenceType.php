<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Dto;

/**
 * Estados de presença suportados pelo campo "options.presence"
 * do endpoint sendText (Evolution API / APIBrasil).
 */
enum PresenceType: string
{
    case Composing = 'composing';
    case Recording = 'recording';
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Paused = 'paused';
}
