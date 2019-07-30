<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\services;

use craft\base\Element;
use craft\base\ElementInterface;
use craft\helpers\FileHelper;
use craft\models\EntryDraft;
use GeoIp2\Database\Reader;
use GuzzleHttp\Client;
use superbig\audit\Audit;

use Craft;
use craft\base\Component;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;
use yii\base\Exception;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class Audit_GeoService extends Component
{
    protected $unpackedCountryDatabasePath;
    protected $unpackedCityDatabasePath;

    // Public Methods
    // =========================================================================

    protected $databases;

    public function init()
    {
        parent::init();

        $path = Craft::parseEnv(Audit::$plugin->getSettings()->dbPath);
        $path = rtrim($path,
                \DIRECTORY_SEPARATOR
            ) . \DIRECTORY_SEPARATOR;

        // Ensure path is writeable
        FileHelper::createDirectory($path);

        $this->databases = [
            'city'    => [
                'url'                 => 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.mmdb.gz',
                'checksum'            => 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.md5',
                'filename'            => 'GeoLite2-City.mmdb.gz',
                'path'                => $path . 'GeoLite2-City.mmdb.gz',
                'pathWithoutFilename' => $path,
                'unpackedPath'        => $path . 'GeoLite2-City.mmdb',
            ],
            'country' => [
                'url'                 => 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.mmdb.gz',
                'checksum'            => 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.md5',
                'filename'            => 'GeoLite2-Country.mmdb.gz',
                'path'                => $path . 'GeoLite2-Country.mmdb.gz',
                'pathWithoutFilename' => $path,
                'unpackedPath'        => $path . DIRECTORY_SEPARATOR . 'GeoLite2-Country.mmdb',
            ],
        ];
    }

    /**
     * @param string $ip
     *
     * @return mixed|null
     */
    public function getLocationInfoForIp($ip = '84.215.212.44')
    {
        $cache = Craft::$app->getCache();

        if ($ip) {
            /*if ( $ip == '::1' || $ip == '127.0.0.1' ) {
                return null;
            }*/

            $cacheKey = 'audit-ip-' . $ip;

            // Check cache first
            if ($cacheRecord = $cache->get($cacheKey)) {
                return $cacheRecord;
            }

            try {
                // This creates the Reader object, which should be reused across lookups.
                $reader = new Reader($this->databases['city']['unpackedPath']);
                $record = $reader->city($ip);

                $cache->set($cacheKey, $record);

                return $record;
            } catch (\Exception $e) {
                Craft::error(
                    Craft::t(
                        'audit',
                        'There was an error getting the ip info: {error}',
                        ['error' => $e->getMessage()]
                    ),
                    __METHOD__
                );

                return null;
            }
        }
    }

    /**
     * @return array
     */
    public function downloadDatabase()
    {
        foreach ($this->databases as $key => $database) {
            $pathWithoutFilename = $database['pathWithoutFilename'];
            $databasePath        = $database['path'];

            if (!FileHelper::isWritable($pathWithoutFilename)) {
                Craft::error('Database folder is not writeable: ' . $pathWithoutFilename, __METHOD__);

                return [
                    'error' => 'Database folder is not writeable: ' . $pathWithoutFilename,
                ];
            }
            $tempPath = Craft::$app->path->getTempPath() . DIRECTORY_SEPARATOR . 'audit' . DIRECTORY_SEPARATOR;

            FileHelper::createDirectory($tempPath);

            $tempFile = $tempPath . $database['filename'];
            Craft::info('Downloading database to: ' . $database['path'], __METHOD__);

            try {
                $guzzle = new Client();

                $response = $guzzle
                    ->get($database['url'], [
                        'sink' => $tempFile,
                    ]);

                @unlink($databasePath);
                FileHelper::createDirectory($pathWithoutFilename);
                copy($tempFile, $databasePath);
                @unlink($tempFile);
            } catch (\Exception $e) {
                Craft::error('Failed to write downloaded database to: ' . $databasePath . ' ' . $e->getMessage(), __METHOD__);

                return [
                    'error' => 'Failed to write downloaded database to file',
                ];
            }
        }

        return [
            'success' => true,
        ];
    }

    /**
     * @return array
     */
    public function unpackDatabase()
    {
        foreach ($this->databases as $key => $database) {
            $databasePath         = $database['path'];
            $databaseUnpackedPath = $database['unpackedPath'];

            try {
                $guzzle   = new Client();
                $response = $guzzle
                    ->get($database['checksum']);

                $remoteChecksum = (string)$response->getBody();
            } catch (\Exception $e) {
                Craft::error('Was not able to get checksum from GeoLite url: ' . $database['checksum'], __METHOD__);

                return [
                    'error' => 'Failed to get remote checksum for Country database',
                ];
            }
            $result = gzdecode(file_get_contents($databasePath));
            if (md5($result) !== $remoteChecksum) {
                Craft::error('Remote checksum for Country database doesn\'t match downloaded database. Please try again or contact support.', __METHOD__);

                return [
                    'error' => 'Remote checksum for Country database doesn\'t match downloaded database. Please try again or contact support.',
                ];
            }
            Craft::debug('Unpacking database to: ' . $databaseUnpackedPath, __METHOD__);
            $write = file_put_contents($databaseUnpackedPath, $result);
            if (!$write) {
                Craft::error('Was not able to write unpacked database to: ' . $databaseUnpackedPath, __METHOD__);

                return [
                    'error' => 'Was not able to write unpacked database to: ' . $databaseUnpackedPath,
                ];
            }
            @unlink($databasePath);
        }

        return [
            'success' => true,
        ];
    }

    /**
     * @return bool
     */
    public function checkValidDb()
    {
        return @file_exists($this->databases['city']['unpackedPath']);
    }
}
