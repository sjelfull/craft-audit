<?php

namespace superbig\audit\helpers;

use Craft;
use craft\base\ElementInterface;
use craft\events\DefineMenuItemsEvent;
use superbig\audit\Audit;

/**
 * Adds a “History” item to element action menus that opens the element's audit events in a slideout.
 */
class ElementHistory
{
    public static function addMenuItem(DefineMenuItemsEvent $event): void
    {
        /** @var ElementInterface $element */
        $element = $event->sender;
        $item = self::menuItem($element);

        if ($item !== null) {
            $event->items[] = $item;
        }
    }

    public static function menuItem(ElementInterface $element): ?array
    {
        $elementId = $element->getCanonicalId();

        if (
            !$elementId ||
            !Craft::$app->getRequest()->getIsCpRequest() ||
            !Craft::$app->getUser()->checkPermission(Audit::PERMISSION_VIEW_LOGS)
        ) {
            return null;
        }

        $view = Craft::$app->getView();
        $id = sprintf('action-audit-history-%s', mt_rand());

        $view->registerJsWithVars(fn($id, $params) => <<<JS
$('#' + $id).on('activate', () => {
  new Craft.CpScreenSlideout('audit/default/history', {params: $params, containerElement: 'div'});
});
JS, [
            $view->namespaceInputId($id),
            ['elementId' => $elementId, 'siteId' => $element->siteId],
        ]);

        return [
            'id' => $id,
            'icon' => 'clock-rotate-left',
            'label' => Craft::t('audit', 'History'),
            'showInChips' => false,
        ];
    }
}
