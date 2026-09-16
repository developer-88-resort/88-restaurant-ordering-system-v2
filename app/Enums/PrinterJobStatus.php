<?php

namespace App\Enums;

enum PrinterJobStatus: string
{
    case Pending = 'pending';
    case Printed = 'printed';
    case Failed = 'failed';
}
