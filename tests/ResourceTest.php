<?php

namespace ChinLeung\MultilingualRoutes\Tests;

use ChinLeung\LaravelLocales\LaravelLocalesServiceProvider;
use ChinLeung\MultilingualRoutes\MultilingualRoutesServiceProvider;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;

class ResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['locales.supported' => [
            'en', 'fr',
        ]]);
    }

    /** @test **/
    public function a_multilingual_resource_route_can_be_registered(): void
    {
        $this->registerTestTranslations();

        Route::multilingualResource('photos', 'PhotoController');

        // Check that all resource actions are created for every language
        $expectedRoutes = [
            'index' => ['GET', ''],
            'create' => ['GET', '/create'],
            'store' => ['POST', ''],
            'show' => ['GET', '/{photos}'],
            'edit' => ['GET', '/{photos}/edit'],
            'update' => ['PUT', '/{photos}'],
            'destroy' => ['DELETE', '/{photos}'],
        ];

        foreach (locales() as $locale) {
            foreach ($expectedRoutes as $action => $config) {
                $routeName = "{$locale}.photos.{$action}";
                $this->assertTrue(Route::has($routeName), "Route {$routeName} should exist");
                
                $route = Route::getRoutes()->getByName($routeName);
                $this->assertNotNull($route, "Route {$routeName} should not be null");
                
                [$method] = $config;
                $this->assertContains(strtoupper($method), $route->methods, "Route {$routeName} should have {$method} method");
            }
        }
    }

    /** @test **/
    public function a_multilingual_resource_can_use_translated_uris(): void
    {
        $this->registerTestTranslations();

        Route::multilingualResource('photos', 'PhotoController');

        // Check English route
        $enIndexRoute = Route::getRoutes()->getByName('en.photos.index');
        $this->assertStringContainsString('photos', $enIndexRoute->uri);

        // Check French route (localized)
        $frIndexRoute = Route::getRoutes()->getByName('fr.photos.index');
        $this->assertStringContainsString('fr/photos', $frIndexRoute->uri);
    }

    /** @test **/
    public function a_multilingual_resource_can_be_limited_to_specific_actions(): void
    {
        Route::multilingualResource('photos', 'PhotoController', [
            'only' => ['index', 'show']
        ]);

        foreach (locales() as $locale) {
            // Check that only index and show exist
            $this->assertTrue(Route::has("{$locale}.photos.index"));
            $this->assertTrue(Route::has("{$locale}.photos.show"));
            
            // Check that the others don't exist
            $this->assertFalse(Route::has("{$locale}.photos.create"));
            $this->assertFalse(Route::has("{$locale}.photos.store"));
            $this->assertFalse(Route::has("{$locale}.photos.edit"));
            $this->assertFalse(Route::has("{$locale}.photos.update"));
            $this->assertFalse(Route::has("{$locale}.photos.destroy"));
        }
    }

    /** @test **/
    public function a_multilingual_resource_can_exclude_specific_actions(): void
    {
        Route::multilingualResource('photos', 'PhotoController', [
            'except' => ['create', 'edit']
        ]);

        foreach (locales() as $locale) {
            // Check that create and edit don't exist
            $this->assertFalse(Route::has("{$locale}.photos.create"));
            $this->assertFalse(Route::has("{$locale}.photos.edit"));
            
            // Check that the others exist
            $this->assertTrue(Route::has("{$locale}.photos.index"));
            $this->assertTrue(Route::has("{$locale}.photos.store"));
            $this->assertTrue(Route::has("{$locale}.photos.show"));
            $this->assertTrue(Route::has("{$locale}.photos.update"));
            $this->assertTrue(Route::has("{$locale}.photos.destroy"));
        }
    }

    /** @test **/
    public function a_multilingual_resource_can_have_custom_parameter_names(): void
    {
        Route::multilingualResource('photos', 'PhotoController', [
            'parameters' => ['photos' => 'photo_id']
        ]);

        foreach (locales() as $locale) {
            $showRoute = Route::getRoutes()->getByName("{$locale}.photos.show");
            $this->assertStringContainsString('{photo_id}', $showRoute->uri);
            
            $editRoute = Route::getRoutes()->getByName("{$locale}.photos.edit");
            $this->assertStringContainsString('{photo_id}', $editRoute->uri);
            
            $updateRoute = Route::getRoutes()->getByName("{$locale}.photos.update");
            $this->assertStringContainsString('{photo_id}', $updateRoute->uri);
            
            $destroyRoute = Route::getRoutes()->getByName("{$locale}.photos.destroy");
            $this->assertStringContainsString('{photo_id}', $destroyRoute->uri);
        }
    }

    /** @test **/
    public function a_multilingual_resource_can_be_limited_to_specific_locales(): void
    {
        Route::multilingualResource('photos', 'PhotoController', [
            'locales' => ['fr']
        ]);

        // Check that only French routes exist
        $this->assertTrue(Route::has('fr.photos.index'));
        $this->assertFalse(Route::has('en.photos.index'));
    }

    /** @test **/
    public function multilingual_resource_generates_correct_route_uris(): void
    {
        $this->registerTestTranslations();

        Route::multilingualResource('photos', 'PhotoController');

        // Check English route URIs
        $this->assertEquals('photos', Route::getRoutes()->getByName('en.photos.index')->uri);
        $this->assertEquals('photos/create', Route::getRoutes()->getByName('en.photos.create')->uri);
        $this->assertEquals('photos', Route::getRoutes()->getByName('en.photos.store')->uri);
        $this->assertEquals('photos/{photos}', Route::getRoutes()->getByName('en.photos.show')->uri);
        $this->assertEquals('photos/{photos}/edit', Route::getRoutes()->getByName('en.photos.edit')->uri);
        $this->assertEquals('photos/{photos}', Route::getRoutes()->getByName('en.photos.update')->uri);
        $this->assertEquals('photos/{photos}', Route::getRoutes()->getByName('en.photos.destroy')->uri);

        // Check French route URIs (with prefix)
        $this->assertEquals('fr/photos', Route::getRoutes()->getByName('fr.photos.index')->uri);
        $this->assertEquals('fr/photos/create', Route::getRoutes()->getByName('fr.photos.create')->uri);
        $this->assertEquals('fr/photos', Route::getRoutes()->getByName('fr.photos.store')->uri);
        $this->assertEquals('fr/photos/{photos}', Route::getRoutes()->getByName('fr.photos.show')->uri);
        $this->assertEquals('fr/photos/{photos}/edit', Route::getRoutes()->getByName('fr.photos.edit')->uri);
        $this->assertEquals('fr/photos/{photos}', Route::getRoutes()->getByName('fr.photos.update')->uri);
        $this->assertEquals('fr/photos/{photos}', Route::getRoutes()->getByName('fr.photos.destroy')->uri);
    }

    protected function registerTestTranslations(): void
    {
        $this->registerTranslations([
            'en' => [
                'routes.photos' => 'photos',
            ],
            'fr' => [
                'routes.photos' => 'photos', // Could be 'photos' or another translation
            ],
        ]);
    }

    protected function registerTranslations(array $translations): self
    {
        $translator = app('translator');

        foreach ($translations as $locale => $translation) {
            $translator->addLines($translation, $locale);
        }

        return $this;
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelLocalesServiceProvider::class,
            MultilingualRoutesServiceProvider::class,
        ];
    }
} 