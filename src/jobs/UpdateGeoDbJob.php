<?php

namespace superbig\audit\jobs;

use Craft;
use craft\queue\BaseJob;
use superbig\audit\Audit;

class UpdateGeoDbJob extends BaseJob
{
    public function execute($queue): void
    {
        $geo = Audit::$plugin->geo;

        // Step 1: Check license key
        $this->setProgress($queue, 0, 'Checking credentials...');
        $response = $geo->checkLicenseKey();

        if (isset($response['error'])) {
            throw new \Exception($response['error']);
        }

        // Step 2: Download database
        $this->setProgress($queue, 0.25, 'Downloading GeoLite database...');
        $response = $geo->downloadDatabase();

        if (isset($response['error'])) {
            throw new \Exception($response['error']);
        }

        // Step 3: Unpack and verify
        $this->setProgress($queue, 0.75, 'Unpacking and verifying database...');
        $response = $geo->unpackDatabase();

        if (isset($response['error'])) {
            throw new \Exception($response['error']);
        }

        $this->setProgress($queue, 1, 'Database updated successfully');
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('audit', 'Updating MaxMind GeoLite database');
    }
}
