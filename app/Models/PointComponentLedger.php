<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointComponentLedger extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Morphs to Mission/Task that credited/debited this ledget
     *
     * @return MorphTo
     */
    public function pointable()
    {
        return $this->morphTo();
    }

    /**
     * Morphs to Reward Component that Pointable rewarded
     *
     * @return MorphTo
     */
    public function component()
    {
        return $this->morphTo('component');
    }
}
