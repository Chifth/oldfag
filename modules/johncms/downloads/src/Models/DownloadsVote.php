<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

declare(strict_types=1);

namespace Johncms\Downloads\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Johncms\Database\Eloquent\Casts\SpecialChars;
use Johncms\Users\User;

/**
 * Class DownloadsVote
 *
 * @package Downloads\Models
 *
 * @mixin Builder
 * @property int $id
 * @property int $type
 * @property int $time
 * @property int $topic
 * @property string $name
 * @property int $count
 *
 * @method DownloadsVote voteUser()
 * @property int $vote_user
 * @property DownloadsVote $answers
 *
 */
class DownloadsVote extends Model
{
    protected $table = 'downloads_vote';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'time',
        'topic',
        'name',
        'count',
    ];

    protected $casts = [
        'type'  => 'integer',
        'time'  => 'integer',
        'topic' => 'integer',
        'name'  => SpecialChars::class,
        'count' => 'integer',
    ];

    /**
     * Adding verification of the current user's participation in the vote.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeVoteUser(Builder $query): Builder
    {
        /** @var User $user */
        $user = di(User::class);
        if ($user) {
            return $query->selectSub(
                (new DownloadsVoteUser())
                    ->selectRaw('count(*)')
                    ->whereRaw('downloads_vote_users.topic = downloads_vote.topic')
                    ->where('user', '=', $user->id),
                'vote_user'
            )
                ->addSelect('downloads_vote.*');
        }
        return $query;
    }

    /**
     * Answers
     *
     * @return HasMany
     */
    public function answers(): HasMany
    {
        return $this->hasMany(__CLASS__, 'topic', 'topic')
            ->where('type', '=', 2)
            ->orderBy('id');
    }
}
