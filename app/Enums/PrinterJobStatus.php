<?php

namespace App\Enums;

enum PrinterJobStatus: string
{
    case Pending = 'pending';
    // Handed to a bridge, not yet acknowledged.
    case Printing = 'printing';
    case Printed = 'printed';
    case Failed = 'failed';
}
