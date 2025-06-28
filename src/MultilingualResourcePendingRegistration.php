<?php

namespace ChinLeung\MultilingualRoutes;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Arr;

class MultilingualResourcePendingRegistration
{
    /**
     * The resource name.
     *
     * @var string
     */
    protected string $resourceName;

    /**
     * The controller class name.
     *
     * @var string
     */
    protected string $controller;

    /**
     * The list of locales for the routes.
     *
     * @var array
     */
    protected array $locales = [];

    /**
     * The options of the routes.
     *
     * @var array
     */
    protected array $options = [];

    /**
     * The resource's registration status.
     *
     * @var bool
     */
    protected bool $registered = false;

    /**
     * The resource registrar.
     *
     * @var \ChinLeung\MultilingualRoutes\MultilingualRegistrar
     */
    protected MultilingualRegistrar $registrar;

    /**
     * If the registered route is a redirect.
     *
     * @var bool
     */
    protected bool $isRedirect = false;

    /**
     * Redirect destination.
     *
     * @var string|null
     */
    protected ?string $destination = null;

    /**
     * Redirect status.
     *
     * @var int|null
     */
    protected ?int $status = null;

    /**
     * Constructor of the class.
     *
     * @param  \ChinLeung\MultilingualRoutes\MultilingualRegistrar  $registrar
     * @param  string  $resourceName
     * @param  string  $controller
     * @param  array  $locales
     */
    public function __construct(MultilingualRegistrar $registrar, string $resourceName, string $controller, array $locales = [])
    {
        $this->resourceName = $resourceName;
        $this->controller = $controller;
        $this->registrar = $registrar;
        $this->locales = $locales;
    }

    /**
     * Register the resource routes.
     *
     * @return \Illuminate\Routing\RouteCollection
     */
    public function register(): RouteCollection
    {
        $this->registered = true;

        if ($this->isRedirect) {
            return $this->registerRedirectRoutes();
        }

        return $this->registerResourceRoutes();
    }

    /**
     * Register redirect routes for all resource actions.
     *
     * @return \Illuminate\Routing\RouteCollection
     */
    protected function registerRedirectRoutes(): RouteCollection
    {
        $resourceActions = $this->getResourceActions();
        $lastRoutes = null;

        foreach ($resourceActions as $action => $config) {
            [$method, $suffix] = $config;

            $paramName = $this->options['parameters'][$this->resourceName] ?? $this->resourceName;
            $routeKey = $this->resourceName.str_replace('{id}', '{'.$paramName.'}', $suffix);

            $actionNames = $this->getActionNames($action);
            $options = array_merge($this->options, [
                'name' => $this->getActionName($action),
            ]);

            if (! empty($actionNames)) {
                $options['names'] = $actionNames;
            }

            $lastRoutes = $this->registrar->redirect(
                $routeKey,
                $this->destination,
                $this->status,
                $this->options['locales'] ?? $this->locales,
                $options
            );
        }

        return $lastRoutes ?? new RouteCollection();
    }

    /**
     * Register resource routes.
     *
     * @return \Illuminate\Routing\RouteCollection
     */
    protected function registerResourceRoutes(): RouteCollection
    {
        $resourceActions = $this->getResourceActions();
        $lastRoutes = null;

        foreach ($resourceActions as $action => $config) {
            [$method, $suffix] = $config;

            $paramName = $this->options['parameters'][$this->resourceName] ?? $this->resourceName;
            $routeKey = $this->resourceName.str_replace('{id}', '{'.$paramName.'}', $suffix);

            $actionNames = $this->getActionNames($action);
            $options = array_merge($this->options, [
                'method' => strtolower($method),
                'name' => $this->getActionName($action),
            ]);

            if (! empty($actionNames)) {
                $options['names'] = $actionNames;
            }

            // Apply whereParam constraints
            if (isset($this->options['whereParams'])) {
                $options['constraints'] = array_merge(
                    $options['constraints'] ?? [],
                    $this->options['whereParams']
                );
            }

            // Apply withTrashed if applicable for this action
            if (isset($this->options['withTrashed']) && in_array($action, $this->options['withTrashed'])) {
                $options['withTrashed'] = true;
            }

            // Apply missing callback
            if (isset($this->options['missing'])) {
                $options['missing'] = $this->options['missing'];
            }

            $lastRoutes = $this->registrar->register(
                $routeKey,
                $this->controller.'@'.$action,
                $this->options['locales'] ?? $this->locales,
                $options
            );
        }

        return $lastRoutes ?? new RouteCollection();
    }

    /**
     * Get the resource actions to register.
     *
     * @return array
     */
    protected function getResourceActions(): array
    {
        $resourceActions = [
            'index' => ['GET', ''],
            'create' => ['GET', '/create'],
            'store' => ['POST', ''],
            'show' => ['GET', '/{id}'],
            'edit' => ['GET', '/{id}/edit'],
            'update' => ['PUT', '/{id}'],
            'destroy' => ['DELETE', '/{id}'],
        ];

        $only = $this->options['only'] ?? [];
        $except = $this->options['except'] ?? [];

        // Filter actions based on only/except options
        if (! empty($only)) {
            $resourceActions = array_intersect_key($resourceActions, array_flip($only));
        }
        if (! empty($except)) {
            $resourceActions = array_diff_key($resourceActions, array_flip($except));
        }

        return $resourceActions;
    }

    /**
     * Get the action name for a specific action.
     *
     * @param  string  $action
     * @return string
     */
    protected function getActionName(string $action): string
    {
        $baseName = $this->options['name'] ?? $this->resourceName;

        return $baseName.'.'.$action;
    }

    /**
     * Get the action names per locale for a specific action.
     *
     * @param  string  $action
     * @return array
     */
    protected function getActionNames(string $action): array
    {
        if (! isset($this->options['names'])) {
            return [];
        }

        $actionNames = [];
        foreach ($this->options['names'] as $locale => $name) {
            $actionNames[$locale] = $name.'.'.$action;
        }

        return $actionNames;
    }

    /**
     * Add one or many locale to the exception.
     *
     * @param  string|array  $locales
     * @return self
     */
    public function exceptLocales($locales): self
    {
        $this->options['locales'] = array_diff($this->locales, Arr::wrap($locales));

        return $this;
    }

    /**
     * Set the route for a list of locales only.
     *
     * @param  string|array  $locales
     * @return self
     */
    public function onlyLocales($locales): self
    {
        $this->options['locales'] = array_intersect($this->locales, Arr::wrap($locales));

        return $this;
    }

    /**
     * Exclude resource actions from the registration.
     *
     * @param  string|array  $actions
     * @return self
     */
    public function except($actions): self
    {
        $this->options['except'] = array_merge(
            $this->options['except'] ?? [],
            Arr::wrap($actions)
        );

        return $this;
    }

    /**
     * Limit the resource to only the specified actions.
     *
     * @param  string|array  $actions
     * @return self
     */
    public function only($actions): self
    {
        $this->options['only'] = Arr::wrap($actions);

        return $this;
    }

    /**
     * Set the name of the routes.
     *
     * @param  string  $name
     * @return self
     */
    public function name(string $name): self
    {
        $this->options['name'] = $name;

        return $this;
    }

    /**
     * Set the name of each locale for the routes.
     *
     * @param  array  $names
     * @return self
     */
    public function names(array $names): self
    {
        $this->options['names'] = $names;

        return $this;
    }

    /**
     * Set the middleware of the routes.
     *
     * @param  string|array  $middleware
     * @return self
     */
    public function middleware($middleware): self
    {
        $this->options['middleware'] = $middleware;

        return $this;
    }

    /**
     * Set a regular expression requirement on the route.
     *
     * @param  array|string  $name
     * @param  string|null  $expression
     * @param  string|null  $locale
     * @return $this
     */
    public function where($name, ?string $expression = null, ?string $locale = null): self
    {
        $key = rtrim("constraints-$locale", '-');

        if (! is_array(Arr::get($this->options, $key))) {
            Arr::set($this->options, $key, []);
        }

        Arr::set($this->options, "$key.$name", $expression);

        return $this;
    }

    /**
     * Set default parameters values of the routes.
     *
     * @param  array  $defaults
     * @return self
     */
    public function defaults(array $defaults): self
    {
        $this->options['defaults'] = $defaults;

        return $this;
    }

    /**
     * Create a redirect from one URI to another.
     *
     * @param  mixed  $destination
     * @param  int  $status
     * @return $this
     */
    public function redirect($destination, int $status = 302): self
    {
        $this->isRedirect = true;
        $this->destination = $destination;
        $this->status = $status;

        return $this;
    }

    /**
     * Set custom parameter names for the resource routes.
     *
     * @param  array  $parameters
     * @return self
     */
    public function parameters(array $parameters): self
    {
        $this->options['parameters'] = $parameters;

        return $this;
    }

    /**
     * Specify a callback that should be invoked when an implicitly bound model can not be found.
     *
     * @param  \Closure  $callback
     * @return self
     */
    public function missing(\Closure $callback): self
    {
        $this->options['missing'] = $callback;

        return $this;
    }

    /**
     * Allow soft deleted models to be retrieved when resolving implicit model bindings.
     *
     * @param  array|null  $actions
     * @return self
     */
    public function withTrashed(?array $actions = null): self
    {
        $this->options['withTrashed'] = $actions ?? ['show', 'edit', 'update'];

        return $this;
    }

    /**
     * Set route constraints for specific parameters.
     *
     * @param  string  $param
     * @param  string  $constraint
     * @return self
     */
    public function whereParam(string $param, string $constraint): self
    {
        if (! isset($this->options['whereParams'])) {
            $this->options['whereParams'] = [];
        }

        $this->options['whereParams'][$param] = $constraint;

        return $this;
    }

    /**
     * Set resource-specific options.
     *
     * @param  array  $options
     * @return self
     */
    public function options(array $options): self
    {
        // Handle resource-specific 'only' and 'except' options
        if (isset($options['only'])) {
            $this->options['only'] = $options['only'];
            unset($options['only']);
        }

        if (isset($options['except'])) {
            $this->options['except'] = $options['except'];
            unset($options['except']);
        }

        if (isset($options['parameters'])) {
            $this->options['parameters'] = $options['parameters'];
            unset($options['parameters']);
        }

        // Merge remaining options
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Handle the object's destruction.
     *
     * @return void
     */
    public function __destruct()
    {
        if (! $this->registered) {
            $this->register();
        }
    }
}