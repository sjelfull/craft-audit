<?php

namespace superbig\audit\console\controllers;

use craft\helpers\Console as ConsoleHelper;
use superbig\audit\Audit;

use yii\console\Controller;
use yii\console\ExitCode;

class DefaultController extends Controller
{
    /**
     * Update Geolocation database
     *
     * @return mixed
     */
    public function actionUpdateDatabase()
    {
        ConsoleHelper::output('Updating Geolocation database');
        ConsoleHelper::startProgress(1, 6);

        $response = Audit::$plugin->geo->checkLicenseKey();

        if (isset($response['error'])) {
            ConsoleHelper::error($response['error']);
            ConsoleHelper::endProgress();

            return ExitCode::DATAERR;
        }

        $response = Audit::$plugin->geo->downloadDatabase();

        ConsoleHelper::startProgress(2, 6);

        if (isset($response['error'])) {
            ConsoleHelper::error($response['error']);
            ConsoleHelper::endProgress();

            return ExitCode::DATAERR;
        }

        ConsoleHelper::startProgress(3, 6);

        $response = Audit::$plugin->geo->unpackDatabase();

        ConsoleHelper::startProgress(4, 6);

        if (isset($response['error'])) {
            ConsoleHelper::error($response['error']);
            ConsoleHelper::endProgress();

            return ExitCode::DATAERR;
        }

        ConsoleHelper::startProgress(6, 6);
        ConsoleHelper::endProgress();
        ConsoleHelper::output('Finished updating database');

        return ExitCode::OK;
    }

    /**
     * Clean old log entries
     */
    public function actionPruneLogs()
    {
        $count = Audit::$plugin->auditService->pruneLogs();

        ConsoleHelper::output('Deleted ' . $count . ' entries');

        return ExitCode::OK;
    }
}
