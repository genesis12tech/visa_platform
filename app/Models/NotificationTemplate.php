<?php

namespace App\Models;

use App\Notifications\Exceptions\NotificationTemplateNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Backend_schema.md §4.10. Lets notification copy be edited without a code
 * deploy — App\Notifications\TemplatedNotification resolves one of these
 * rows for every notification it sends, per (event_key, channel, locale).
 */
class NotificationTemplate extends Model
{
    protected $attributes = [
        'locale' => 'en',
        'channel' => 'mail',
        'is_active' => true,
    ];

    protected $fillable = [
        'event_key',
        'locale',
        'channel',
        'subject',
        'body',
        'is_active',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function resolve(string $eventKey, string $channel, string $locale = 'en'): self
    {
        $template = static::query()
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            throw NotificationTemplateNotFoundException::for($eventKey, $channel, $locale);
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{subject: ?string, body: string}
     */
    public function render(array $data): array
    {
        $replacements = collect($data)
            ->mapWithKeys(fn (mixed $value, string $key): array => [":{$key}" => (string) $value])
            ->all();

        return [
            'subject' => $this->subject !== null ? strtr($this->subject, $replacements) : null,
            'body' => strtr($this->body, $replacements),
        ];
    }
}
