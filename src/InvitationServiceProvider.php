<?php

namespace Increment\Plan;

use Illuminate\Support\ServiceProvider;

class InvitationServiceProvider extends ServiceProvider{

  public function boot(){
    $this->loadMigrationsFrom(__DIR__.'/migrations/invitation');
    $this->loadRoutesFrom(__DIR__.'/routes/invitation.php');
  }

  public function register(){
  }
}