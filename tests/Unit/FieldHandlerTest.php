<?php

use craft\elements\Entry;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Checkboxes;
use craft\fields\Color;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Email;
use craft\fields\Entries;
use craft\fields\Lightswitch;
use craft\fields\Matrix;
use craft\fields\Money;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fields\Table;
use craft\fields\Tags;
use craft\fields\Time;
use craft\fields\Url;
use craft\fields\Users;
use superbig\audit\Audit;
use superbig\audit\fieldHandlers\handlers\BooleanHandler;
use superbig\audit\fieldHandlers\handlers\ColorHandler;
use superbig\audit\fieldHandlers\handlers\DateHandler;
use superbig\audit\fieldHandlers\handlers\MatrixHandler;
use superbig\audit\fieldHandlers\handlers\MoneyHandler;
use superbig\audit\fieldHandlers\handlers\MultiOptionHandler;
use superbig\audit\fieldHandlers\handlers\NeoHandler;
use superbig\audit\fieldHandlers\handlers\OptionHandler;
use superbig\audit\fieldHandlers\handlers\PlainHandler;
use superbig\audit\fieldHandlers\handlers\RelationHandler;
use superbig\audit\fieldHandlers\handlers\RichTextHandler;
use superbig\audit\fieldHandlers\handlers\SeoHandler;
use superbig\audit\fieldHandlers\handlers\SuperTableHandler;
use superbig\audit\fieldHandlers\handlers\TableHandler;
use superbig\audit\fieldHandlers\handlers\VizyHandler;

// --- typeKey() & supportedFields() sanity checks --------------------------

it('PlainHandler exposes type key and supported fields', function () {
    expect(PlainHandler::typeKey())->toBe('plain');
    expect(PlainHandler::supportedFields())->toEqual([
        PlainText::class,
        Number::class,
        Email::class,
        Url::class,
    ]);
});

it('ColorHandler exposes type key and supported fields', function () {
    expect(ColorHandler::typeKey())->toBe('color');
    expect(ColorHandler::supportedFields())->toEqual([Color::class]);
});

it('DateHandler exposes type key and supported fields', function () {
    expect(DateHandler::typeKey())->toBe('date');
    expect(DateHandler::supportedFields())->toEqual([Date::class, Time::class]);
});

it('BooleanHandler exposes type key and supported fields', function () {
    expect(BooleanHandler::typeKey())->toBe('boolean');
    expect(BooleanHandler::supportedFields())->toEqual([Lightswitch::class]);
});

it('OptionHandler exposes type key and supported fields', function () {
    expect(OptionHandler::typeKey())->toBe('option');
    expect(OptionHandler::supportedFields())->toEqual([Dropdown::class, RadioButtons::class]);
});

it('MultiOptionHandler exposes type key and supported fields', function () {
    expect(MultiOptionHandler::typeKey())->toBe('multi-option');
    expect(MultiOptionHandler::supportedFields())->toEqual([Checkboxes::class, MultiSelect::class]);
});

it('RelationHandler exposes type key and supported fields', function () {
    expect(RelationHandler::typeKey())->toBe('relation');
    expect(RelationHandler::supportedFields())->toContain(Entries::class);
    expect(RelationHandler::supportedFields())->toContain(Assets::class);
    expect(RelationHandler::supportedFields())->toContain(Categories::class);
    expect(RelationHandler::supportedFields())->toContain(Tags::class);
    expect(RelationHandler::supportedFields())->toContain(Users::class);
});

it('MoneyHandler exposes type key and supported fields', function () {
    expect(MoneyHandler::typeKey())->toBe('money');
    expect(MoneyHandler::supportedFields())->toEqual([Money::class]);
});

it('TableHandler exposes type key and supported fields', function () {
    expect(TableHandler::typeKey())->toBe('table');
    expect(TableHandler::supportedFields())->toEqual([Table::class]);
});

it('MatrixHandler exposes type key and supported fields', function () {
    expect(MatrixHandler::typeKey())->toBe('matrix');
    expect(MatrixHandler::supportedFields())->toEqual([Matrix::class]);
});

// --- RichText: class_exists gating ---------------------------------------

it('RichTextHandler gates supported fields behind class_exists', function () {
    expect(RichTextHandler::typeKey())->toBe('richtext');
    // In test env none of CKEditor/Redactor/TinyMCE are installed.
    $supported = RichTextHandler::supportedFields();
    expect($supported)->toBeArray();
    foreach ($supported as $cls) {
        expect(class_exists($cls))->toBeTrue();
    }
});

// --- Third-party: return [] when plugin absent ---------------------------

it('third-party handlers return empty supportedFields when plugins absent', function () {
    // None of these plugins should be installed in the test env.
    expect(NeoHandler::typeKey())->toBe('neo');
    expect(NeoHandler::supportedFields())->toBe(class_exists('benf\\neo\\Field') ? ['benf\\neo\\Field'] : []);

    expect(SuperTableHandler::typeKey())->toBe('supertable');
    expect(SuperTableHandler::supportedFields())->toBe(
        class_exists('verbb\\supertable\\fields\\SuperTableField')
            ? ['verbb\\supertable\\fields\\SuperTableField']
            : []
    );

    expect(VizyHandler::typeKey())->toBe('vizy');
    expect(VizyHandler::supportedFields())->toBe(
        class_exists('verbb\\vizy\\fields\\VizyField')
            ? ['verbb\\vizy\\fields\\VizyField']
            : []
    );

    expect(SeoHandler::typeKey())->toBe('seo');
    expect(SeoHandler::supportedFields())->toBe(
        class_exists('ether\\seo\\fields\\SeoField')
            ? ['ether\\seo\\fields\\SeoField']
            : []
    );
});

// --- normalize() coverage ------------------------------------------------

it('PlainHandler normalizes scalars and objects', function () {
    $field = new PlainText();
    $element = new Entry();
    $h = new PlainHandler();

    expect($h->normalize($field, 'hello', $element))->toBe('hello');
    expect($h->normalize($field, 42, $element))->toBe(42);
    expect($h->normalize($field, null, $element))->toBeNull();
    // Stringable object
    $stringable = new class {
        public function __toString(): string { return 'obj'; }
    };
    expect($h->normalize($field, $stringable, $element))->toBe('obj');
});

it('ColorHandler handles ColorData-like objects and strings', function () {
    $field = new Color();
    $element = new Entry();
    $h = new ColorHandler();

    expect($h->normalize($field, null, $element))->toBeNull();
    expect($h->normalize($field, '', $element))->toBeNull();
    expect($h->normalize($field, '#ff0000', $element))->toBe('#ff0000');

    $color = (object) ['hex' => '#00ff00'];
    expect($h->normalize($field, $color, $element))->toBe('#00ff00');
});

it('BooleanHandler coerces all values to bool', function () {
    $field = new Lightswitch();
    $element = new Entry();
    $h = new BooleanHandler();

    expect($h->normalize($field, true, $element))->toBeTrue();
    expect($h->normalize($field, false, $element))->toBeFalse();
    expect($h->normalize($field, 1, $element))->toBeTrue();
    expect($h->normalize($field, 0, $element))->toBeFalse();
    expect($h->normalize($field, '1', $element))->toBeTrue();
    expect($h->normalize($field, '', $element))->toBeFalse();
    expect($h->normalize($field, null, $element))->toBeFalse();
});

it('DateHandler formats Date vs Time fields differently', function () {
    $element = new Entry();
    $h = new DateHandler();

    $dt = new \DateTime('2025-06-15 14:30:45');

    $dateField = new Date();
    $dateField->showTime = false;
    expect($h->normalize($dateField, $dt, $element))->toBe('2025-06-15');

    $dateTimeField = new Date();
    $dateTimeField->showTime = true;
    expect($h->normalize($dateTimeField, $dt, $element))->toBe('2025-06-15 14:30:45');

    $timeField = new Time();
    expect($h->normalize($timeField, $dt, $element))->toBe('14:30:45');

    expect($h->normalize($dateField, null, $element))->toBeNull();
    expect($h->normalize($dateField, 'not-a-date', $element))->toBeNull();
});

it('OptionHandler normalizes SingleOptionFieldData-like objects', function () {
    $field = new Dropdown();
    $element = new Entry();
    $h = new OptionHandler();

    expect($h->normalize($field, null, $element))->toBeNull();

    $opt = (object) ['value' => 'red', 'label' => 'Red'];
    expect($h->normalize($field, $opt, $element))->toBe(['value' => 'red', 'label' => 'Red']);

    // Fallback for scalar
    expect($h->normalize($field, 'blue', $element))->toBe(['value' => 'blue', 'label' => 'blue']);
});

it('MultiOptionHandler returns only selected options', function () {
    $field = new Checkboxes();
    $element = new Entry();
    $h = new MultiOptionHandler();

    $options = [
        (object) ['value' => 'a', 'label' => 'A', 'selected' => true],
        (object) ['value' => 'b', 'label' => 'B', 'selected' => false],
        (object) ['value' => 'c', 'label' => 'C', 'selected' => true],
    ];
    expect($h->normalize($field, $options, $element))->toBe([
        ['value' => 'a', 'label' => 'A'],
        ['value' => 'c', 'label' => 'C'],
    ]);

    expect($h->normalize($field, null, $element))->toBe([]);
    expect($h->normalize($field, [], $element))->toBe([]);
});

it('RelationHandler normalizes element-like objects', function () {
    $field = new Entries();
    $element = new Entry();
    $h = new RelationHandler();

    expect($h->normalize($field, null, $element))->toBe([]);
    expect($h->normalize($field, [], $element))->toBe([]);

    // Fake element class with displayName + getStatus
    $fakeEl = new class {
        public int $id = 42;
        public string $title = 'Hello';
        public static function displayName(): string { return 'FakeEntry'; }
        public function getStatus(): string { return 'enabled'; }
    };

    $result = $h->normalize($field, [$fakeEl], $element);
    expect($result)->toBe([[
        'id' => 42,
        'title' => 'Hello',
        'type' => 'FakeEntry',
        'status' => 'enabled',
    ]]);
});

it('RelationHandler clones ElementQuery-like objects and collects all()', function () {
    $field = new Entries();
    $element = new Entry();
    $h = new RelationHandler();

    $fakeEl = new class {
        public int $id = 7;
        public string $title = 'Q';
        public static function displayName(): string { return 'Q'; }
        public function getStatus(): string { return 'live'; }
    };

    $query = new class($fakeEl) {
        public bool $statusCalled = false;
        private array $items;
        public function __construct($el) { $this->items = [$el]; }
        public function status($s): self { $this->statusCalled = true; return $this; }
        public function all(): array { return $this->items; }
    };

    $out = $h->normalize($field, $query, $element);
    expect($out)->toBeArray();
    expect($out[0]['id'])->toBe(7);
    expect($out[0]['type'])->toBe('Q');
});

it('MoneyHandler normalizes amount+currency', function () {
    $field = new Money();
    $field->currency = 'EUR';
    $element = new Entry();
    $h = new MoneyHandler();

    expect($h->normalize($field, null, $element))->toBeNull();

    // Integer cents
    expect($h->normalize($field, 1250, $element))->toBe([
        'amount' => 1250,
        'currency' => 'EUR',
        'display' => '12.50 EUR',
    ]);

    // Object with getAmount()
    $money = new class {
        public function getAmount(): int { return 9999; }
    };
    expect($h->normalize($field, $money, $element))->toBe([
        'amount' => 9999,
        'currency' => 'EUR',
        'display' => '99.99 EUR',
    ]);
});

it('TableHandler maps handle-keyed rows to heading-keyed rows', function () {
    $field = new Table();
    $field->columns = [
        'col1' => ['heading' => 'Name', 'handle' => 'col1', 'type' => 'singleline'],
        'col2' => ['heading' => 'Age', 'handle' => 'col2', 'type' => 'number'],
    ];
    $element = new Entry();
    $h = new TableHandler();

    expect($h->normalize($field, null, $element))->toBe([]);
    expect($h->normalize($field, 'not-array', $element))->toBe([]);

    $rows = [
        ['col1' => 'Alice', 'col2' => 30],
        ['col1' => 'Bob', 'col2' => 25],
    ];
    expect($h->normalize($field, $rows, $element))->toBe([
        ['Name' => 'Alice', 'Age' => 30],
        ['Name' => 'Bob', 'Age' => 25],
    ]);
});

it('MatrixHandler returns [] for non-query raw values', function () {
    $field = new Matrix();
    $element = new Entry();
    $h = new MatrixHandler();

    expect($h->normalize($field, null, $element))->toBe([]);
    expect($h->normalize($field, 'nope', $element))->toBe([]);
});

// --- Registry integration -----------------------------------------------

it('registry resolves PlainText → PlainHandler after plugin init', function () {
    $registry = Audit::$plugin->fieldHandlerRegistry;
    $registry->reset();

    // Re-register the built-in handlers for this test run.
    \yii\base\Event::on(
        \superbig\audit\services\FieldHandlerRegistry::class,
        \superbig\audit\services\FieldHandlerRegistry::EVENT_REGISTER_HANDLERS,
        static function (\superbig\audit\events\RegisterFieldHandlersEvent $event) {
            $event->handlers[] = PlainHandler::class;
            $event->handlers[] = BooleanHandler::class;
            $event->handlers[] = ColorHandler::class;
        }
    );

    expect($registry->getHandler(new PlainText()))->toBeInstanceOf(PlainHandler::class);
    expect($registry->getHandler(new Lightswitch()))->toBeInstanceOf(BooleanHandler::class);
    expect($registry->getHandler(new Color()))->toBeInstanceOf(ColorHandler::class);
    expect($registry->getTypeKey(new PlainText()))->toBe('plain');

    \yii\base\Event::off(
        \superbig\audit\services\FieldHandlerRegistry::class,
        \superbig\audit\services\FieldHandlerRegistry::EVENT_REGISTER_HANDLERS
    );
    $registry->reset();
});
