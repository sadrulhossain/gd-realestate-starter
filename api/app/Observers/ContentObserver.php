<?php

namespace App\Observers;

use App\Jobs\SendRevalidateWebhook;
use Illuminate\Database\Eloquent\Model;

/** Public content changed: queue a signed revalidate webhook tagged with the table name. */
class ContentObserver
{
    /** Child tables whose rows are shown inside another resource: table => tags to revalidate (besides its own). */
    private const ALSO = [
        'property_media' => ['properties'],
        'project_units' => ['projects'],
        'developers' => ['projects'],
    ];

    /** Dispatch only once the surrounding transaction has committed. */
    public bool $afterCommit = true;

    public function saved(Model $model): void
    {
        SendRevalidateWebhook::dispatch($this->tags($model));
    }

    public function deleted(Model $model): void
    {
        SendRevalidateWebhook::dispatch($this->tags($model));
    }

    /** @return list<string> */
    private function tags(Model $model): array
    {
        $table = $model->getTable();

        return [$table, ...(self::ALSO[$table] ?? [])];
    }
}
