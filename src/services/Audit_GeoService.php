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
use ErrorException;
use GeoIp2\Database\Reader;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use superbig\audit\Audit;

use Craft;
use craft\base\Component;
use superbig\audit\models\AuditModel;
use superbig\audit\models\Settings;
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

    /** @var Settings */
    private $settings;

    public function init(): void
    {
        parent::init();

        $this->settings = Audit::$plugin->getSettings();
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
                $reader = new Reader($this->settings->getCityDbPath());
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

    public function checkLicenseKey()
    {
        if (!$this->settings->hasValidLicenseKey()) {
            $error = $this->formatErrorMessage('Invalid MaxMind license key. Generate one at {url}', [
                'url' => $this->settings->accountAreaUrl,
            ]);

            $this->logError($error);

            return [
                'error' => $error,
            ];
        }
    }

    /**
     * @return array
     * @throws \yii\base\ErrorException
     */
    public function downloadDatabase()
    {
        $settings = $this->settings;
        $dbPath = $settings->getDbPath(null, true);
        $tempPath = $settings->getTempPath();
        $countryDbPath = $settings->getCountryDbPath();

        if (!FileHelper::isWritable($dbPath)) {
            $error = $this
                ->formatErrorMessage('Database folder is not writeable: {path}', [
                    'path' => $dbPath,
                ]);

            return $this->logError($error, __METHOD__);
        }

        $types = [
            'Country' => [
                'url' => $settings->getCountryDownloadUrl(),
                'tempPath' => $settings->getCountryDbPath($isTemp = true),
                'path' => $settings->getCountryDbPath(),
            ],
            'City' => [
                'url' => $settings->getCityDownloadUrl(),
                'tempPath' => $settings->getCityDbPath($isTemp = true),
                'path' => $settings->getCityDbPath(),
            ],
        ];
        $success = true;

        foreach ($types as $key => $type) {
            try {
                $this->logInfo("Downloading {$key} database to: {$type['tempPath']}", __METHOD__);
                $client = (new Client())
                    ->get($type['url'], [
                        'sink' => $type['tempPath'],
                    ]);
            } catch (ConnectException $e) {
                $error = $this->formatErrorMessage('Failed to connect to {url}: {error}', [
                    'url' => $type['url'],
                    'error' => $e->getMessage(),
                ]);
                $this->logError($error);
                $success = false;
                continue;
            } catch (ClientException $e) {
                $error = $this->formatErrorMessage('Failed to download {url}: {error}', [
                    'url' => $type['url'],
                    'error' => $e->getMessage(),
                ]);

                $this->logError($error);
                $success = false;
                continue;
            } catch (\Exception $e) {
                $error = $this->formatErrorMessage('Failed to get database {url}: {error}', [
                    'url' => $type['url'],
                    'error' => $e->getMessage(),
                ]);

                $this->logError($error);
                $success = false;
                continue;
            }
        }

        return [
            'success' => $success,
        ];
    }

    /**
     * @return array
     */
    public function unpackDatabase()
    {
        $settings = $this->settings;

        $countryChecksumUrl = $settings->getCountryChecksumDownloadUrl();
        $countryDbPath = $settings->getCountryDbPath($temp = true);
        $cityDbPath = $settings->getCityDbPath($temp = true);
        $cityChecksumUrl = $settings->getCityChecksumDownloadUrl();
        $remoteChecksum = null;

        $urls = [
            'Country' => [
                'url' => $countryChecksumUrl,
                'path' => $countryDbPath,
            ],
            'City' => [
                'url' => $cityChecksumUrl,
                'path' => $cityDbPath,
            ],
        ];

        foreach ($urls as $key => $info) {
            try {
                $guzzle = new Client();
                $url = $info['url'];
                $path = $info['path'];
                $response = $guzzle
                    ->get($url);

                $remoteChecksum = (string)$response->getBody();

                // Verify checksum
                if (md5(file_get_contents($path)) !== $remoteChecksum) {
                    $error = $this->formatErrorMessage('Remote checksum for {type} database doesn\'t match downloaded database. Please try again or contact support.', ['type' => $key]);

                    return $this->logError($error, __METHOD__);
                }
            } catch (\Exception $e) {
                $error = $this
                    ->formatErrorMessage('Was not able to get checksum from GeoLite {url}: {error}', [
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);

                return $this->logError($error, __METHOD__);
            }

            try {
                $this->findAndWriteDatabase(strtolower($key));
            } catch (\Exception $e) {
                return $this->logError($e->getMessage(), __METHOD__);
            }
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
        return @file_exists(Audit::$plugin->getSettings()->getCountryDbPath());
    }

    public function getLastUpdateTime()
    {
        if (!$this->checkValidDb()) {
            return null;
        }

        $time = FileHelper::lastModifiedTime(Audit::$plugin->getSettings()->getCountryDbPath());

        return new \DateTime("@{$time}");
    }

    private function logError(string $error, $category = 'audit')
    {
        Craft::error($error, $category);

        return [
            'error' => $error,
        ];
    }

    private function logInfo(string $message, $category = 'audit')
    {
        Craft::info($message, $category);
    }

    private function formatErrorMessage($error, $vars = [])
    {
        return Craft::t('audit', $error, $vars);
    }

    private function findAndWriteDatabase($type = 'country')
    {
        $settings = $this->settings;
        $outputPath = $type === 'country' ? $settings->getCountryDbPath() : $settings->getCityDbPath();
        $tempFile = $type === 'country' ? $settings->getCountryDbPath($isTemp = true) : $settings->getCityDbPath($isTemp = true);
        $found = false;
        $archive = new \PharData($tempFile);

        foreach (new \RecursiveIteratorIterator($archive) as $file) {
            $fileInfo = pathinfo($file);

            if (!empty($fileInfo['extension']) && 'mmdb' === $fileInfo['extension']) {
                $found = true;
                $result = $file->getContent();

                try {
                    FileHelper::writeToFile($outputPath, $result);
                    @unlink($tempFile);
                } catch (ErrorException $e) {
                    $error = $this->formatErrorMessage('Failed to write {type} database to {path}', [
                        'path' => $outputPath,
                        'type' => $type,
                    ]);

                    throw new \Exception($error);
                }
            }
        }

        if (!$found) {
            $error = $this->formatErrorMessage('Did not find database in archive', [
            ]);

            throw new \Exception($error);
        }
    }
}
