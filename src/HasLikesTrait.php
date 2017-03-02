<?php

/*
 * This file is part of Laravel Likeable.
 *
 * (c) Brian Faust <hello@brianfaust.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

/*
 * This file is part of Laravel Likeable.
 *
 * (c) Brian Faust <hello@brianfaust.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BrianFaust\Likeable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasLikesTrait
{
    /**
     * @return mixed
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * @return mixed
     */
    public function likeCounter(): MorphOne
    {
        return $this->morphOne(Counter::class, 'likeable');
    }

    /**
     * @return mixed
     */
    public function getLikeCount(): int
    {
        return $this->likeCount;
    }

    /**
     * @param $from
     * @param null $to
     *
     * @return mixed
     */
    public function getLikeCountByDate($from, $to = null): int
    {
        return Like::countByDate($this, $from, $to);
    }

    /**
     * @return int
     */
    public function getLikeCountAttribute(): int
    {
        return $this->likeCounter ? $this->likeCounter->count : 0;
    }

    /**
     * @param Model $likedBy
     *
     * @return bool
     */
    public function like(Model $likedBy): void
    {
        if ($this->getLikedRecord($likedBy)) {
            return;
        }

        $like = new Like();
        $like->liked_by_id = $likedBy->id;
        $like->liked_by_type = get_class($likedBy);
        $this->likes()->save($like);

        $this->incrementCounter();
    }

    /**
     * @param Model $likedBy
     *
     * @return bool
     */
    public function dislike(Model $likedBy): void
    {
        if (!$like = $this->getLikedRecord($likedBy)) {
            return;
        }

        $like->delete();

        $this->decrementCounter();
    }

    /**
     * @param $query
     * @param Model $model
     *
     * @return mixed
     */
    public function scopeWhereLiked($query, Model $model)
    {
        return $query->whereHas('likes', function ($query) use ($model) {
            $query->where('liked_by_id', $model->id);
            $query->where('liked_by_type', get_class($model));
        });
    }

    /**
     * @return Counter
     */
    private function incrementCounter(): int
    {
        if ($counter = $this->likeCounter()->first()) {
            $counter->increment('count', 1);
            $counter->save();
        } else {
            $counter = new Counter();
            $counter->fill(['count' => 1]);

            $this->likeCounter()->save($counter);
        }

        return $counter;
    }

    /**
     * @return mixed
     */
    private function decrementCounter(): int
    {
        if ($counter = $this->likeCounter()->first()) {
            $counter->decrement('count', 1);
            $counter->count ? $counter->save() : $counter->delete();
        }

        return $counter;
    }

    /**
     * @param Model $model
     *
     * @return mixed
     */
    public function getLikedRecord(Model $model): Model
    {
        return $this->likes()
                    ->where('liked_by_id', $model->id)
                    ->where('liked_by_type', get_class($model))
                    ->first();
    }

    /**
     * @param Model $model
     *
     * @return bool
     */
    public function liked(Model $model): bool
    {
        return (bool) $this->likes()
                           ->where('liked_by_id', $model->id)
                           ->where('liked_by_type', get_class($model))
                           ->count();
    }
}
