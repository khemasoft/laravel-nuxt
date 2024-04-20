<?php

namespace Khemasoft\LaravelNuxt\Facades;

use Illuminate\Support\Facades\Route;
use Khemasoft\LaravelNuxt\Http\Controllers\NuxtController;

class Nuxt
{
    public static function route(string $path)
    {
        return Route::get(
            '/'.trim(config('nuxt.prefix'), '/').'/'.trim($path, '/'),
            '\\'.NuxtController::class
        );
    }
}
