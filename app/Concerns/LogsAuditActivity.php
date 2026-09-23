<?php

namespace App\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Shared audit-log configuration for models tracked on the Superadmin
 * Audit Logs page. Mirrors the old AuditObserver behaviour (log only
 * changed attributes, skip sensitive fields, produce a human-readable
 * "Action Label: identifier" description) but backed by
 * spatie/laravel-activitylog instead of the custom AuditLog table.
 */
trait LogsAuditActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('audit')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['password', 'remember_token', 'invitation_token', 'pin_hash', 'pin_lookup', 'pin_changed_at', 'updated_at', 'locale']);
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return sprintf('%s %s: %s', ucfirst($eventName), $this->auditLabel(), $this->auditIdentifier());
    }

    protected function auditLabel(): string
    {
        return class_basename($this);
    }

    protected function auditIdentifier(): string
    {
        foreach (['name', 'resort_name'] as $field) {
            if (! empty($this->{$field})) {
                return (string) $this->{$field};
            }
        }

        return '#'.$this->getKey();
    }
}
