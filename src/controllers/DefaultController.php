<?php

namespace superbig\audit\controllers;

use Craft;
use craft\helpers\Template;
use craft\helpers\UrlHelper;

use craft\web\Controller;
use superbig\audit\assetbundles\indexcpsection\IndexCPSectionAsset;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;
use superbig\audit\models\LogFilter;
use superbig\audit\records\AuditRecord;
use superbig\audit\web\assets\diff\AuditDiffAsset;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class DefaultController extends Controller
{
    /**
     * @var array<int|string>|bool|int Allows anonymous access to this controller's actions.
     *                                  The actions must be in 'kebab-case'
     */
    protected array|int|bool $allowAnonymous = [];

    private const HISTORY_LIMIT = 50;

    public function actionIndex(): Response
    {
        $this->requirePermission(Audit::PERMISSION_VIEW_LOGS);

        $request = Craft::$app->getRequest();
        $filter = LogFilter::fromRequest($request);

        $query = AuditRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->with('user')
            ->limit(100);
        $filter->apply($query);

        [$pageInfo, $records] = Template::paginateCriteria($query);
        $logs = array_map(fn(AuditRecord $record) => AuditModel::createFromRecord($record), $records);

        $variables = [
            'logs' => $logs,
            'pageInfo' => $pageInfo,
            'filter' => $filter,
        ];

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'html' => $this->getView()->renderTemplate('audit/_results', $variables),
                'url' => $filter->url(),
            ]);
        }

        return $this->renderTemplate('audit/index', $variables + [
            'options' => Audit::$plugin->auditService->getFilterOptions(),
        ]);
    }

    public function actionDetails(int $id): Response
    {
        $this->requirePermission(Audit::PERMISSION_VIEW_LOGS);

        $service = Audit::$plugin->auditService;
        $log = $service->getEventById($id);

        if (!$log) {
            throw new NotFoundHttpException('Log entry not found');
        }

        $view = $this->getView();
        $view->registerAssetBundle(AuditDiffAsset::class);
        $view->registerAssetBundle(IndexCPSectionAsset::class);

        $variables = [
            'settings' => Audit::$plugin->getSettings(),
            'log' => $log,
            'logsInSession' => $log->sessionId ? $service->getEventsBySessionId($log->sessionId) : [],
            'fieldMap' => $log->getFieldMap(),
        ];

        return $this->asCpScreen()
            ->title($log->getEventLabel() . ($log->title ? ': ' . $log->getDisplayTitle() : ''))
            ->docTitle($log->getEventLabel())
            ->editUrl($log->getCpEditUrl())
            ->addCrumb(Craft::t('audit', 'Audit'), UrlHelper::cpUrl('audit'))
            ->contentTemplate('audit/_detail', $variables)
            ->metaSidebarTemplate('audit/_detail-meta', $variables);
    }

    public function actionHistory(int $elementId, ?int $siteId = null): Response
    {
        $this->requirePermission(Audit::PERMISSION_VIEW_LOGS);

        $query = AuditRecord::find()->where(['elementId' => $elementId]);
        $total = (int)$query->count();
        /** @var AuditRecord[] $records */
        $records = $query->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])->with('user')->limit(self::HISTORY_LIMIT)->all();
        $logs = array_map(fn(AuditRecord $record) => AuditModel::createFromRecord($record), $records);

        $element = Craft::$app->getElements()->getElementById($elementId, null, $siteId ?? '*');
        $title = $element ? (string)$element : (($logs[0] ?? null)?->getDisplayTitle() ?? '');
        $logUrl = UrlHelper::cpUrl('audit', ['elementId' => $elementId]);

        $this->getView()->registerAssetBundle(IndexCPSectionAsset::class);

        return $this->asCpScreen()
            ->title($title !== '' ? Craft::t('audit', 'History: {title}', ['title' => $title]) : Craft::t('audit', 'History'))
            ->docTitle(Craft::t('audit', 'History'))
            ->editUrl($logUrl)
            ->addCrumb(Craft::t('audit', 'Audit'), UrlHelper::cpUrl('audit'))
            ->contentTemplate('audit/_history', [
                'element' => $element,
                'title' => $title,
                'logs' => $logs,
                'total' => $total,
                'logUrl' => $logUrl,
            ]);
    }

    public function actionPruneLogs()
    {
        $this->requirePermission(Audit::PERMISSION_CLEAR_LOGS);

        $count = Audit::$plugin->auditService->pruneLogs();

        Craft::$app->getSession()->setNotice('Deleted ' . $count . ' records');

        return $this->goBack(UrlHelper::cpUrl('audit'));
    }
}
