<?php

namespace Hanafalah\ModuleCardIdentity\Concerns;

trait HasCardIdentity
{

  public function setCardIdentity($flag, $value){
    if (isset($flag)) {
      return $this->cardIdentities()->updateOrCreate([
        'reference_id'   => $this->{$this->getKeyName()},
        'reference_type' => $this->getMorphClass(),
        'flag' => $flag
      ], [
        'value' => (string) $value
      ]);
    } else {
      return null;
    }
  }

  public function cardIdentity(){return $this->morphOneModel('CardIdentity', 'reference');}
  public function cardIdentities(){return $this->morphManyModel('CardIdentity', 'reference');}
}
