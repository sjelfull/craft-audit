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
use yii\widgets\LinkPager;

use JasonGrimes\Paginator;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class GeoController extends Controller
{

    // Protected Properties
    // =========================================================================

    public function actionDownloadDatabase ()
    {
        $response = Audit::$plugin->geo->downloadDatabase();

        if ( isset($response['error']) ) {
            return $this->renderJSON($response['error']);
        }

        return $this->renderJSON($response);
    }

    public function actionUnpackDatabase ()
    {
        $response = Audit::$plugin->geo->unpackDatabase();

        if ( isset($response['error']) ) {
            return $this->renderJSON($response['error']);
        }

        return $this->renderJSON($response);
    }

    public function actionUpdateDatabase ()
    {
        $response = Audit::$plugin->geo->downloadDatabase();

        if ( isset($response['error']) ) {
            return $this->renderJSON($response['error']);
        }

        $response = Audit::$plugin->geo->unpackDatabase();

        if ( isset($response['error']) ) {
            return $this->renderJSON($response['error']);
        }

        return $this->renderJSON($response);
    }

    /**
     * Return data to browser as JSON and end application.
     *
     * @param array $data
     */
    protected function renderJSON ($data)
    {
        header('Content-type: application/json');
        echo json_encode($data);

        return Craft::$app->end();
    }
}
