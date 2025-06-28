<?php

namespace ChinLeung\MultilingualRoutes\Macros;

use ChinLeung\MultilingualRoutes\MultilingualRegistrar;
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
            $locales = $options['locales'] ?? locales();
            $except = $options['except'] ?? [];
            $only = $options['only'] ?? [];
            $parameters = $options['parameters'] ?? [];

            $resourceActions = [
                'index' => ['GET', ''],
                'create' => ['GET', '/create'],
                'store' => ['POST', ''],
                'show' => ['GET', '/{id}'],
                'edit' => ['GET', '/{id}/edit'],
                'update' => ['PUT', '/{id}'],
                'destroy' => ['DELETE', '/{id}'],
            ];

            // Filter actions based on only/except options
            if (!empty($only)) {
                $resourceActions = array_intersect_key($resourceActions, array_flip($only));
            }
            if (!empty($except)) {
                $resourceActions = array_diff_key($resourceActions, array_flip($except));
            }

            $allRoutes = [];

            // Register each resource action as separate multilingual routes
            foreach ($resourceActions as $action => $config) {
                [$method, $suffix] = $config;
                
                // Customize parameter name
                $paramName = $parameters[$key] ?? $key;
                $routeKey = $key . str_replace('{id}', '{' . $paramName . '}', $suffix);
                
                // Create multilingual route using existing logic
                $registrar = $this->container && $this->container->bound(MultilingualRegistrar::class)
                    ? $this->container->make(MultilingualRegistrar::class)
                    : new MultilingualRegistrar($this);

                $registration = new MultilingualRoutePendingRegistration(
                    $registrar,
                    $routeKey,
                    $controller . '@' . $action,
                    $locales
                );

                $registration->method(strtolower($method))
                    ->name($key . '.' . $action);

                $routes = $registration->register();
                $allRoutes[] = $routes;
            }

            return $allRoutes;
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
