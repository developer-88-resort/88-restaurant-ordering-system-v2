<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Maintenance Mode was switched on or off. Every open screen listens on the
 * public "system" channel (resources/js/lib/maintenance-watch.js): switching
 * it on reloads them straight onto the maintenance page, with no click. Public
 * because customers at a table QR and signed-out screens need it too; it
 * carries nothing but on/off.
 */
class MaintenanceModeChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public bool $on) {}

    public function broadcastOn(): array
    {
        return [new Channel('system')];
    }

    public function broadcastAs(): string
    {
        return 'MaintenanceModeChanged';
    }

    public function broadcastWith(): array
    {
        return ['on' => $this->on];
    }
}
