<?php

namespace superbig\audit\console\controllers;

use craft\elements\User;
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

    /**
     * Remove a user's details from the log, by user ID, username or email. Works for deleted users too.
     */
    public function actionAnonymiseUser(string $user): int
    {
        $userId = ctype_digit($user)
            ? (int)$user
            : User::find()->username($user)->status(null)->trashed(null)->ids()[0]
                ?? User::find()->email($user)->status(null)->trashed(null)->ids()[0]
                ?? null;

        if (!$userId) {
            ConsoleHelper::error(sprintf('No user found for “%s”. Deleted users can only be anonymised by ID.', $user));

            return ExitCode::DATAERR;
        }

        $count = Audit::$plugin->privacy->anonymiseUser($userId);
        ConsoleHelper::output(sprintf('Anonymised %d %s for user %d', $count, $count === 1 ? 'row' : 'rows', $userId));

        return ExitCode::OK;
    }

    /**
     * Apply the redact setting to rows already in the log.
     */
    public function actionRedact(): int
    {
        $handles = Audit::$plugin->privacy->redactedHandles();
        if (!$handles) {
            ConsoleHelper::output('No redacted fields are configured.');

            return ExitCode::OK;
        }

        $count = Audit::$plugin->privacy->redactExistingRows();
        ConsoleHelper::output(sprintf('Redacted %s in %d %s', implode(', ', $handles), $count, $count === 1 ? 'row' : 'rows'));

        return ExitCode::OK;
    }
}
