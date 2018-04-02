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

use craft\helpers\Template;
use craft\web\UrlManager;
use superbig\audit\Audit;

use Craft;
use craft\web\Controller;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;
use yii\data\Pagination;
use yii\web\HttpException;
use yii\widgets\LinkPager;

use JasonGrimes\Paginator;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class GeoController extends Controller
{

    protected $allowAnonymous = ['update-database'];

    // Protected Properties
    // =========================================================================

    public function actionDownloadDatabase()
    {
        $response = Audit::$plugin->geo->downloadDatabase();

        if (isset($response['error'])) {
            return $this->renderJSON($response['error']);
        }

        return $this->renderJSON($response);
    }

    public function actionUnpackDatabase()
    {
        $response = Audit::$plugin->geo->unpackDatabase();

        if (isset($response['error'])) {
            return $this->renderJSON($response['error']);
        }

        return $this->renderJSON($response);
    }

    /**
     * Update Geolocation database
     *
     * @return void
     * @throws HttpException
     * @throws \yii\base\ExitException
     */
    public function actionUpdateDatabase()
    {
        $validKey = Audit::$plugin->getSettings()->updateAuthKey;
        $key      = Craft::$app->getRequest()->getParam('key');

        if (!Craft::$app->getUser()->getIsAdmin() && $key !== $validKey) {
            throw new HttpException('Not authorized to run this action');
        }

        $response = Audit::$plugin->geo->downloadDatabase();

        if (isset($response['error'])) {
            return $this->renderJSON($response['error']);
        }

        $response = Audit::$plugin->geo->unpackDatabase();

        if (isset($response['error'])) {
            return $this->renderJSON($response['error']);
        }

        return $this->renderJSON($response);
    }

    /**
     * Return data to browser as JSON and end application.
     *
     * @param array $data
     *
     * @throws \yii\base\ExitException
     */
    protected function renderJSON($data)
    {
        header('Content-type: application/json');
        echo json_encode($data);

        return Craft::$app->end();
    }
}
