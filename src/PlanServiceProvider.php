<?php

namespace Increment\Plan;

use Illuminate\Support\ServiceProvider;

class PlanServiceProvider extends ServiceProvider{

  public function boot(){
    $this->loadMigrationsFrom(__DIR__.'/migrations/plan');
    $this->loadRoutesFrom(__DIR__.'/routes/plan.php');
  }

  public function register(){
  }
}