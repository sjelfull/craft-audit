<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\controllers;

use Craft;

use craft\web\Controller;
use superbig\audit\Audit;
use superbig\audit\jobs\UpdateGeoDbJob;
use yii\web\HttpException;
use yii\web\Response;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class GeoController extends Controller
{
    protected array|int|bool $allowAnonymous = ['update-database'];

    // Protected Properties
    // =========================================================================

    public function actionStartUpdate(): Response
    {
        $this->requireAcceptsJson();
        $this->requireAdmin();

        $job = new UpdateGeoDbJob();
        $jobId = Craft::$app->getQueue()->push($job);

        return $this->asJson([
            'success' => true,
            'jobId' => $jobId,
        ]);
    }

    /**
     * Update Geolocation database
     *
     * @throws HttpException
     * @throws \yii\base\ExitException
     */
    public function actionUpdateDatabase(): void
    {
        $validKey = Audit::$plugin->getSettings()->updateAuthKey;
        $key = Craft::$app->getRequest()->getParam('key');

        if (!Craft::$app->getUser()->getIsAdmin() && $key !== $validKey) {
            throw new HttpException(403, 'Not authorized to run this action');
        }

        $response = Audit::$plugin->geo->downloadDatabase();

        if (isset($response['error'])) {
            $this->renderJSON($response['error']);
            return;
        }

        $response = Audit::$plugin->geo->unpackDatabase();

        if (isset($response['error'])) {
            $this->renderJSON($response['error']);
            return;
        }

        $this->renderJSON($response);
    }

    /**
     * Return data to browser as JSON and end application.
     *
     * @param mixed $data
     *
     * @throws \yii\base\ExitException
     */
    protected function renderJSON(mixed $data): void
    {
        header('Content-type: application/json');
        echo json_encode($data);

        Craft::$app->end();
    }
}
