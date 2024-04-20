<?php

use Khemasoft\LaravelNuxt\Facades\Nuxt;

Nuxt::route('{path?}')->middleware('web')->name('nuxt')->where('path', '.*');
