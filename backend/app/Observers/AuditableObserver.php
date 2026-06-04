<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->log($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->log($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->log($model, $model->isForceDeleting() ? 'force_deleted' : 'deleted');
    }

    protected function log(Model $model, string $event): void
    {
        $old = [];
        $new = [];
        if ($event === 'updated') {
            $old = $model->getOriginal();
            $new = $model->getAttributes();
        } elseif ($event === 'created') {
            $new = $model->getAttributes();
        } elseif (in_array($event, ['deleted', 'force_deleted'])) {
            $old = $model->getAttributes();
        }

        activity()
            ->performedOn($model)
            ->causedBy(auth()->user())
            ->withProperties([
                'old' => $old,
                'new' => $new,
                'attributes' => $model->getAttributes(),
            ])
            ->event($event)
            ->tap(function ($activity) {
                $activity->ip_address = Request::ip();
                $activity->user_agent = Request::userAgent();
            })
            ->log($event);
    }
}
