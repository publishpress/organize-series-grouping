<?php
namespace OrganizeSeries\GroupingAddon\domain\services;

use DomainException;
use OrganizeSeries\application\Root;
use OrganizeSeries\domain\exceptions\InvalidEntityException;
use OrganizeSeries\domain\exceptions\InvalidInterfaceException;
use OrganizeSeries\domain\interfaces\AbstractBootstrap;
use OrganizeSeries\domain\model\ClassOrInterfaceFullyQualifiedName;
use OrganizeSeries\domain\model\ExtensionIdentifier;
use OrganizeSeries\GroupingAddon\domain\Meta;

/**
 * Bootstrap
 * Bootstraps the add-on
 *
 * @package OrganizeSeries\GroupingAddon\domain\services
 * @author  Darren Ethier
 * @since   1.0.0
 */
class Bootstrap extends AbstractBootstrap
{
    /**
     * Any special initialization logic should go in this method.
     * Examples of things that might happen here are any requirement checks etc.
     *
     * @return bool Return false if you want the bootstrap process to be halted after initializing.
     * @throws DomainException
     * @throws InvalidEntityException
     * @throws InvalidInterfaceException
     */
    protected function initialized()
    {
        $this->loadLegacy();
        //register as an extension
        $this->getExtensionsRegistry()->registerExtension(
            new ExtensionIdentifier(
                'Organize Series Groups Add-on',
                'organize-series-grouping',
                1279,
                self::meta()->getFile(),
                self::meta()->getVersion()
            )
        );
        return true;
    }


    /**
     * Load all legacy files.
     *
     * @throws DomainException
     * @throws InvalidInterfaceException
     */
    private function loadLegacy()
    {
        if (! defined('OS_GROUPING_LEGACY_LOADED')) {
            require_once self::meta()->getBasePath() . 'legacy-includes.php';
        }
    }

    /**
     * Any registration of dependencies on the container should happen in this method.
     */
    protected function registerDependencies()
    {
        //noop
    }

    /**
     * Classes should register any routes with the router via this method.
     */
    protected function registerRoutes()
    {
        //noop no routes to register (yet).
    }


    /**
     * Helper for getting the registered Meta class
     *
     * @throws InvalidInterfaceException
     * @return Meta
     */
    public static function meta()
    {
        return Root::container()->make(
            new ClassOrInterfaceFullyQualifiedName(
                Meta::class
            )
        );
    }

}