<?php

namespace App\Enums;

enum ElementKind: string
{
    case Domain = 'domain';
    case Cluster = 'cluster';
    case Activity = 'activity';
}
