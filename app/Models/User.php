<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Mattiverse\Userstamps\Traits\Userstamps;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Comments\Models\Concerns\InteractsWithComments;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

final class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasPermissions;
    use HasRoles;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use InteractsWithComments;
    use LogsActivity;
    use SoftDeletes;
    use Userstamps;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'workos_id',
        'avatar',
        'slug',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'workos_id',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // 'password' => 'hashed',  // omitted for WorkOS
        ];
    }

    /**
     * Get the password for the user (overridden for WorkOS authentication).
     * Since we use WorkOS for authentication, we don't store passwords locally.
     */
    public function getAuthPassword(): ?string
    {
        return null;
    }

    /**
     * Get the secondary key type for this model
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::ULID;
    }

    /**
     * Get the user's initials.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn(string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // ->logOnly(['name', 'email', 'slug'])
            ->dontSubmitEmptyLogs()
            ->useLogName('user')
            ->setDescriptionForEvent(fn(string $eventName) => match ($eventName) {
                'created' => 'User created',
                'updated' => 'User updated',
                'deleted' => 'User deleted',
                default => $eventName,
            });
    }

    /**
     * Get the total number of comments made by this user
     */
    public function getTotalCommentsCount(): int
    {
        return $this->commentatorComments()->count();
    }

    /**
     * Get the user's recent comments (last 10)
     */
    public function getRecentComments()
    {
        return $this->commentatorComments()
            ->latest()
            ->limit(10)
            ->get();
    }

    /**
     * Subscribe to all comment notifications for a model
     */
    public function subscribeToAllComments(Model $model): self
    {
        return $this->subscribeToCommentNotifications($model, NotificationSubscriptionType::All);
    }

    /**
     * Subscribe only to replies on the user's own comments
     */
    public function subscribeToRepliesOnly(Model $model): self
    {
        return $this->subscribeToCommentNotifications($model, NotificationSubscriptionType::Replies);
    }

    /**
     * Check if user is subscribed to notifications for a model
     */
    public function isSubscribedToComments(Model $model): bool
    {
        $subscriptionType = $this->notificationSubscriptionType($model);
        return $subscriptionType && $subscriptionType !== NotificationSubscriptionType::None;
    }

    /**
     * Get all models this user is subscribed to for comment notifications
     */
    public function getCommentSubscriptions()
    {
        return $this->subscriberNotificationSubscriptions()
            ->where('type', '!=', NotificationSubscriptionType::None->value)
            ->get();
    }

    /**
     * Get total number of reactions made by this user
     */
    public function getTotalReactionsCount(): int
    {
        return $this->reactions()->count();
    }

    /**
     * Get user's favorite reaction emoji
     */
    public function getFavoriteReaction(): ?string
    {
        return $this->reactions()
            ->selectRaw('reaction, COUNT(*) as count')
            ->groupBy('reaction')
            ->orderByDesc('count')
            ->first()?->reaction;
    }

    /**
     * Check if user has reacted to a specific comment
     */
    public function hasReactedToComment($commentId, string $reaction = null): bool
    {
        $query = $this->reactions()->where('reactable_id', $commentId);

        if ($reaction) {
            $query->where('reaction', $reaction);
        }

        return $query->exists();
    }
}
