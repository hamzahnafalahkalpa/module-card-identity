<?php

namespace Hanafalah\ModuleCardIdentity\Models;

use Hanafalah\LaravelSupport\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Hanafalah\ModuleCardIdentity\Resources\CardIdentity\{
    ViewCardIdentity,
    ShowCardIdentity
};
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class CardIdentity extends BaseModel
{
    use HasUlids, SoftDeletes;
    
    protected $list = ['id', 'reference_type', 'reference_id', 'flag', 'value'];

    protected $casts = [
        'flag'  => 'string',
        'value' => 'string'
    ];

    protected static function booted(): void{
        parent::booted();
        static::creating(function($query){
            $query->value = (string) $query->value;
        });
    }

    public function viewUsingRelation(): array{
        return [];
    }

    public function showUsingRelation(): array{
        return [];
    }

    public function getViewResource(){
        return ViewCardIdentity::class;
    }

    public function getShowResource(){
        return ShowCardIdentity::class;
    }

    public function reference(){return $this->morphTo();}
}
