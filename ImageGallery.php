<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ImageGallery;

use ImageGallery\DependencyInjection\PreviewComponentPass;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Thelia\Core\Install\Database;
use Thelia\Model\ConfigQuery;
use Thelia\Module\BaseModule;

/**
 * Class ImageGallery.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ImageGallery extends BaseModule
{
    public const DOMAIN_NAME = 'imagegallery';

    /** Back-office translation domain (I18n/backOffice/default-twig/). */
    public const BO_DOMAIN = 'imagegallery.bo.default-twig';

    /**
     * Allowed group name pattern, shared by the create/edit forms and the preview
     * route requirement so both ends agree: a group must be URL-safe (it is used
     * as a route parameter to build the back-office preview URL).
     */
    public const GROUP_PATTERN = '[\w-]{1,64}';

    /**
     * Config key: name of the theme Twig component the back-office preview
     * renders (e.g. "Flexy:ImageGallery"). Empty = module default rendering.
     */
    public const CONFIG_PREVIEW_COMPONENT = 'preview_component';

    /**
     * Config key: comma-separated Webpack Encore entries loaded in the preview
     * iframe so the theme component gets its CSS/JS (e.g. "app").
     */
    public const CONFIG_PREVIEW_ASSETS_ENTRIES = 'preview_assets_entries';

    /**
     * Image cache subdirectory, shared by variant generation (presenter, loop)
     * and cache purge (slide service): a divergence would leave stale images
     * served after an image replacement.
     */
    public const IMAGE_CACHE_SUBDIRECTORY = 'imagegallery';

    /** Image MIME types accepted by every upload form of the module. */
    public const ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * Core route of the back-office module configuration page (it takes a
     * `module_code` parameter). Shared by the configuration controller and the
     * tools menu hook, which both build the module page URL with the router
     * instead of a hard-coded path: the back-office prefix may be customized
     * (BackOfficePath module).
     */
    public const CONFIG_ROUTE = 'admin.module.configure';

    /** Locale used when the session carries none, shared by every presenter consumer. */
    public const DEFAULT_LOCALE = 'en_US';

    /**
     * @return bool true to continue module activation, false to prevent it
     */
    public function preActivation(?ConnectionInterface $con = null): bool
    {
        if (!self::getConfigValue('is_initialized', '0')) {
            $database = new Database($con);

            $database->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);

            self::setConfigValue('is_initialized', '1');
        }

        return true;
    }

    /**
     * The core calls destroy($con, $event->getDeleteData()): the tables (and the
     * uploaded images) are only dropped when the administrator asked for the
     * module data to be deleted, otherwise a plain module removal would destroy
     * every slide.
     *
     * @param bool $deleteModuleData
     */
    public function destroy(?ConnectionInterface $con = null, $deleteModuleData = false): void
    {
        if (!$deleteModuleData) {
            return;
        }

        $database = new Database($con);

        $database->insertSql(null, [__DIR__.'/Config/sql/destroy.sql']);

        $uploadDir = self::getUploadDirPath();

        if (is_dir($uploadDir)) {
            (new Filesystem())->remove($uploadDir);
        }
    }

    /**
     * @return string the path to the directory holding the slide images, without final slash
     */
    public static function getUploadDirPath(): string
    {
        $uploadDir = ConfigQuery::read('images_library_path');

        if ($uploadDir === null) {
            $uploadDir = THELIA_LOCAL_DIR.'media'.DS.'images';
        } else {
            $uploadDir = THELIA_ROOT.$uploadDir;
        }

        return $uploadDir.DS.self::DOMAIN_NAME;
    }

    /** Kept for callers holding a module instance: the path depends on no instance state. */
    public function getUploadDir(): string
    {
        return self::getUploadDirPath();
    }

    /**
     * Applies Config/update/<version>.sql files newer than the current version.
     *
     * @param string $currentVersion
     * @param string $newVersion
     */
    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $updateDir = __DIR__.DS.'Config'.DS.'update';

        if (!is_dir($updateDir)) {
            return;
        }

        $finder = (new Finder())->files()->name('#.*?\.sql#')->sortByName()->in($updateDir);

        if (0 === $finder->count()) {
            return;
        }

        $database = new Database($con);

        // apply updates only if the table exists
        if ($database->execute("SHOW TABLES LIKE 'image_gallery'")->rowCount() === 0) {
            return;
        }

        /** @var SplFileInfo $updateSQLFile */
        foreach ($finder as $updateSQLFile) {
            if (version_compare($currentVersion, str_replace('.sql', '', $updateSQLFile->getFilename()), '<')) {
                $database->insertSql(null, [$updateSQLFile->getPathname()]);
            }
        }
    }

    /**
     * Defines how services are loaded in your modules.
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/DependencyInjection',
                __DIR__.'/Exception',
                __DIR__.'/I18n',
                __DIR__.'/Config',
                __DIR__.'/Tests',
                __FILE__,
            ])
            ->autowire(true)
            ->autoconfigure(true);
    }

    public static function getCompilers(): array
    {
        return [new PreviewComponentPass()];
    }
}
