<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\models;

use craft\helpers\FileHelper;
use superbig\audit\Audit;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * How many days to keep log entries around
     */
    public $pruneDays = 30;

    /**
     * Enabled status
     */
    public $enabled = true;

    /**
     * Prune old records when a admin is logged in
     */
    public $pruneRecordsOnAdminRequests = false;

    /**
     * Enabled status
     */
    public $enabledGeolocation = true;

    /**
     * Update authentication key
     */
    public $updateAuthKey = '';

    /**
     * Where to save Maxmind DB files
     */
    public $dbPath;
    public $tempPath;

    public $logPluginEvents = true;
    public $logDraftEvents = false;
    public $logElementEvents = true;
    public $logChildElementEvents = false;
    public $logUserEvents = true;
    public $logRouteEvents = true;

    public $accountAreaUrl = 'https://www.maxmind.com/en/account';
    public $cityDbFilename = 'GeoLite2-City.mmdb';
    public $countryDbFilename = 'GeoLite2-Country.mmdb';
    public $maxmindLicenseKey = '';

    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        if (empty($this->dbPath)) {
            $this->dbPath = Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'audit' . DIRECTORY_SEPARATOR;
        }
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            ['enabled', 'boolean'],
            ['enabledGeolocation', 'boolean'],
            ['pruneDays', 'integer'],
        ]);
    }

    public function getCountryDownloadUrl()
    {
        return "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&suffix=tar.gz&license_key={$this->maxmindLicenseKey}";
    }

    public function getCountryChecksumDownloadUrl()
    {
        return "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&suffix=tar.gz.md5&license_key={$this->maxmindLicenseKey}";
    }

    public function getCityDownloadUrl()
    {
        return "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&suffix=tar.gz&license_key={$this->maxmindLicenseKey}";
    }

    public function getCityChecksumDownloadUrl()
    {
        return "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&suffix=tar.gz.md5&license_key={$this->maxmindLicenseKey}";
    }

    public function getCityDbPath($isTempPath = false)
    {
        if ($isTempPath) {
            return $this->getTempPath($this->cityDbFilename);
        }

        return $this->getDbPath($this->cityDbFilename);
    }

    public function getCountryDbPath($isTempPath = false)
    {
        if ($isTempPath) {
            return $this->getTempPath($this->countryDbFilename);
        }

        return $this->getDbPath($this->countryDbFilename);
    }

    public function getDbPath($filename = null, $createDirectory = false)
    {
        $dbPath = $this->dbPath;

        if (empty($dbPath)) {
            $dbPath = Craft::$app->getPath()->getStoragePath() . \DIRECTORY_SEPARATOR . 'audit';
        }

        if ($createDirectory) {
            FileHelper::createDirectory($dbPath);
        }

        return FileHelper::normalizePath($dbPath . \DIRECTORY_SEPARATOR . $filename);
    }

    public function getTempPath($filename = null, $createDirectory = true)
    {
        $tempPath = $this->tempPath;

        if (empty($tempPath)) {
            $tempPath = Craft::$app->getPath()->getTempPath() . '/audit/';
        }

        if ($createDirectory) {
            FileHelper::createDirectory($tempPath);
        }

        return FileHelper::normalizePath($tempPath . \DIRECTORY_SEPARATOR . $filename);
    }

    public function hasValidLicenseKey()
    {
        return !empty($this->licenseKey);
    }
}
