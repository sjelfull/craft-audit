<?php

use craft\base\Field;
use craft\fields\PlainText;
use superbig\audit\Audit;
use superbig\audit\events\RegisterFieldHandlersEvent;
use superbig\audit\fieldHandlers\FieldHandler;
use superbig\audit\services\FieldHandlerRegistry;

// Dummy handler for PlainText
class PlainHandlerStub implements FieldHandler
{
    public static int $loadCount = 0;

    public static function supportedFields(): array
    {
        self::$loadCount++;
        return [PlainText::class];
    }

    public static function typeKey(): string
    {
        return 'plain';
    }

    public function normalize(\craft\base\FieldInterface $field, mixed $rawValue, \craft\base\ElementInterface $element): mixed
    {
        return (string) $rawValue;
    }
}

// Handler that claims NOT to implement FieldHandler (validates interface check)
class BogusHandlerStub
{
    public static function supportedFields(): array { return [PlainText::class]; }
    public static function typeKey(): string { return 'bogus'; }
}

beforeEach(function () {
    Audit::$plugin->fieldHandlerRegistry->reset();
    \yii\base\Event::off(FieldHandlerRegistry::class, FieldHandlerRegistry::EVENT_REGISTER_HANDLERS);
});

afterEach(function () {
    Audit::$plugin->fieldHandlerRegistry->reset();
    \yii\base\Event::off(FieldHandlerRegistry::class, FieldHandlerRegistry::EVENT_REGISTER_HANDLERS);
});

it('registers handlers via the event', function () {
    \yii\base\Event::on(
        FieldHandlerRegistry::class,
        FieldHandlerRegistry::EVENT_REGISTER_HANDLERS,
        function (RegisterFieldHandlersEvent $event) {
            $event->handlers[] = PlainHandlerStub::class;
        }
    );

    $registry = new FieldHandlerRegistry();
    $field = new PlainText();

    $handler = $registry->getHandler($field);
    expect($handler)->toBeInstanceOf(PlainHandlerStub::class);
    expect($registry->getTypeKey($field))->toBe('plain');
});

it('returns null for unregistered fields', function () {
    $registry = new FieldHandlerRegistry();
    $field = new PlainText();

    expect($registry->getHandler($field))->toBeNull();
    expect($registry->getTypeKey($field))->toBe('generic');
});

it('matches subclasses via is_a', function () {
    \yii\base\Event::on(
        FieldHandlerRegistry::class,
        FieldHandlerRegistry::EVENT_REGISTER_HANDLERS,
        function (RegisterFieldHandlersEvent $event) {
            $event->handlers[] = PlainHandlerStub::class;
        }
    );

    // Anonymous subclass of PlainText
    $subclass = new class extends PlainText {};

    $registry = new FieldHandlerRegistry();
    $handler = $registry->getHandler($subclass);

    expect($handler)->toBeInstanceOf(PlainHandlerStub::class);
});

it('caches registration (only fires event once)', function () {
    $fireCount = 0;
    \yii\base\Event::on(
        FieldHandlerRegistry::class,
        FieldHandlerRegistry::EVENT_REGISTER_HANDLERS,
        function (RegisterFieldHandlersEvent $event) use (&$fireCount) {
            $fireCount++;
            $event->handlers[] = PlainHandlerStub::class;
        }
    );

    $registry = new FieldHandlerRegistry();
    $field = new PlainText();

    $registry->getHandler($field);
    $registry->getHandler($field);
    $registry->getTypeKey($field);

    expect($fireCount)->toBe(1);
});

it('can be reset', function () {
    $fireCount = 0;
    \yii\base\Event::on(
        FieldHandlerRegistry::class,
        FieldHandlerRegistry::EVENT_REGISTER_HANDLERS,
        function (RegisterFieldHandlersEvent $event) use (&$fireCount) {
            $fireCount++;
            $event->handlers[] = PlainHandlerStub::class;
        }
    );

    $registry = new FieldHandlerRegistry();
    $field = new PlainText();

    $registry->getHandler($field);
    expect($fireCount)->toBe(1);

    $registry->reset();
    $registry->getHandler($field);
    expect($fireCount)->toBe(2);
});

it('skips handlers that do not implement the interface', function () {
    \yii\base\Event::on(
        FieldHandlerRegistry::class,
        FieldHandlerRegistry::EVENT_REGISTER_HANDLERS,
        function (RegisterFieldHandlersEvent $event) {
            $event->handlers[] = BogusHandlerStub::class;
        }
    );

    $registry = new FieldHandlerRegistry();
    $field = new PlainText();

    expect($registry->getHandler($field))->toBeNull();
});
