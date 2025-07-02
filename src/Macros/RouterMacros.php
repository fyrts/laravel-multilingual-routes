<?php

namespace ChinLeung\MultilingualRoutes\Macros;

use ChinLeung\MultilingualRoutes\MultilingualRegistrar;
use ChinLeung\MultilingualRoutes\MultilingualResourcePendingRegistration;
use ChinLeung\MultilingualRoutes\MultilingualRoutePendingRegistration;
use Closure;

class RouterMacros
{
    /**
     * Register a multilingual GET route.
     *
     * @param  string  $key
     * @param  mixed  $handle
     * @param  array  $locales
     * @return \Closure
     */
    public function multilingual(): Closure
    {
        return function ($key, $handle = null, $locales = []) {
            return new MultilingualRoutePendingRegistration(
                $this->container && $this->container->bound(MultilingualRegistrar::class)
                    ? $this->container->make(MultilingualRegistrar::class)
                    : new MultilingualRegistrar($this),
                $key === '/' ? $key : ltrim($key, '/'),
                $handle,
                $locales ?: locales()
            );
        };
    }

    /**
     * Register multilingual resource routes.
     *
     * @param  string  $key
     * @param  string  $controller
     * @param  array  $options
     * @return \Closure
     */
    public function multilingualResource(): Closure
    {
        return function ($key, $controller, array $options = []) {
            $registrar = $this->container && $this->container->bound(MultilingualRegistrar::class)
                ? $this->container->make(MultilingualRegistrar::class)
                : new MultilingualRegistrar($this);

            return new MultilingualResourcePendingRegistration(
                $registrar,
                $key,
                $controller,
                $options['locales'] ?? locales()
            )->options($options);
        };
    }

    /**
     * Check if a route with the given name exists for the current locale.
     *
     * @param  string|array  $name
     * @return \Closure
     */
    public function hasLocalized(): Closure
    {
        return function ($name) {
            $names = array_map(
                static fn ($pattern) => locale().".{$pattern}",
                is_array($name) ? $name : func_get_args(),
            );

            return $this->has($names);
        };
    }
}
