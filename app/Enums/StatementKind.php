<?php

namespace App\Enums;

enum StatementKind: string
{
    case Mission = 'mission';
    case Vision = 'vision';
    case Goal = 'goal';
}
