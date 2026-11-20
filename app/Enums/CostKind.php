<?php

namespace App\Enums;

enum CostKind: string
{
    case SupplierContract = 'supplier_contract';
    case Personnel = 'personnel';
    case Project = 'project';
    case Other = 'other';
}
