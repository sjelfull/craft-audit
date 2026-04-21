<?php

declare(strict_types=1);

namespace superbig\audit\services;

use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\HtmlDiffConfig;
use Craft;
use craft\base\Component;
use Jfcherng\Diff\DiffHelper;

/**
 * DiffRenderer — wraps jfcherng/php-diff and caxy/php-htmldiff
 * with a consistent interface for all Craft Audit field handlers.
 *
 * All public methods MUST return a string (never throw) — on failure they
 * fall back to a simple text diff or an error badge.
 */
class DiffRenderer extends Component
{
    // ── Constants ────────────────────────────────────────────────────

    /** Default maximum bytes to diff before truncating. */
    public const MAX_BYTES = 50_000;

    /** Maximum lines to diff before truncating (line-level diffs). */
    public const MAX_LINES = 500;

    // ── Shared config (built once, reused) ───────────────────────────

    private ?HtmlDiffConfig $htmlDiffConfig = null;

    // ── Public API ───────────────────────────────────────────────────

    /**
     * Render a plain-text diff using jfcherng/php-diff.
     *
     * @param string $from   Old value
     * @param string $to     New value
     * @param string $mode   'inline' | 'side-by-side'
     * @param string $detail 'word' | 'line' | 'char' | 'none'
     */
    public function renderTextDiff(
        string $from,
        string $to,
        string $mode = 'inline',
        string $detail = 'word',
    ): string {
        // Short-circuit: identical content (including both empty)
        if ($from === $to) {
            return $this->identicalResult();
        }

        // Truncate large inputs
        $from = $this->maybeTruncate($from);
        $to = $this->maybeTruncate($to);

        $renderer = match ($mode) {
            'side-by-side' => 'SideBySide',
            default => 'Inline',
        };

        $differOptions = [
            'context' => 2,
            'ignoreCase' => false,
            'ignoreLineEnding' => true,
            'ignoreWhitespace' => false,
            'lengthLimit' => 2000,
            'fullContextIfIdentical' => false,
        ];

        $rendererOptions = [
            'detailLevel' => $detail,
            'language' => 'eng',
            'lineNumbers' => false,
            'separateBlock' => true,
            'showHeader' => false,
            'wrapperClasses' => ['audit-diff', 'diff-wrapper'],
        ];

        try {
            $result = DiffHelper::calculate($from, $to, $renderer, $differOptions, $rendererOptions);
        } catch (\Throwable $e) {
            Craft::warning('DiffRenderer::renderTextDiff failed: ' . $e->getMessage(), 'audit');
            return $this->renderSimpleDiff($from, $to);
        }

        // Empty result = no changes detected
        if ($result === '') {
            return $this->identicalResult();
        }

        return $result;
    }

    /**
     * Render an HTML-aware diff using caxy/php-htmldiff.
     * Produces output with <ins> and <del> tags.
     */
    public function renderHtmlDiff(string $from, string $to): string
    {
        // Short-circuit: identical content
        if ($from === $to) {
            return $this->identicalResult();
        }

        // Truncate large inputs
        $from = $this->maybeTruncate($from);
        $to = $this->maybeTruncate($to);

        try {
            $diff = HtmlDiff::create($from, $to, $this->getHtmlDiffConfig());
            $result = $diff->build();
        } catch (\Throwable $e) {
            Craft::warning('DiffRenderer::renderHtmlDiff failed: ' . $e->getMessage(), 'audit');
            return $this->renderSimpleDiff($from, $to);
        }

        if (!is_string($result) || $result === '') {
            return $this->identicalResult();
        }

        // If no <ins>/<del> markers appeared, the libraries consider this "no change"
        if (!str_contains($result, '<ins') && !str_contains($result, '<del')) {
            return $this->identicalResult();
        }

        return '<div class="audit-diff-html">' . $result . '</div>';
    }

    /**
     * Render a diff for structured data (arrays, objects).
     * Serializes to pretty-printed JSON and runs a line-level text diff.
     *
     * @param mixed $from Old value (array, object, scalar)
     * @param mixed $to   New value
     */
    public function renderJsonDiff(mixed $from, mixed $to): string
    {
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        try {
            $fromJson = json_encode($from, $flags | JSON_THROW_ON_ERROR);
            $toJson = json_encode($to,   $flags | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Craft::warning('DiffRenderer::renderJsonDiff: json_encode failed: ' . $e->getMessage(), 'audit');
            return $this->errorResult();
        }

        if ($fromJson === $toJson) {
            return $this->identicalResult();
        }

        // Use line-level diff for JSON (word-level is too noisy for structured data)
        return $this->renderTextDiff($fromJson, $toJson, 'inline', 'line');
    }

    /**
     * Render a simple "old → new" inline comparison (no external library).
     * Used by handlers that have their own visual representation, and as
     * a last-resort fallback when the diff libraries fail.
     *
     * @param mixed $from Formatted old value (will be cast to string)
     * @param mixed $to   Formatted new value (will be cast to string)
     */
    public function renderSimpleDiff(mixed $from, mixed $to): string
    {
        $fromStr = $this->toScalarString($from);
        $toStr = $this->toScalarString($to);

        if ($fromStr === $toStr) {
            return $this->identicalResult();
        }

        return sprintf(
            '<span class="audit-diff-simple">'
            . '<span class="audit-diff-old">%s</span>'
            . '<span class="audit-diff-arrow">→</span>'
            . '<span class="audit-diff-new">%s</span>'
            . '</span>',
            htmlspecialchars($fromStr, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($toStr,   ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Truncate content to maxBytes to prevent memory/timeout issues.
     * Respects UTF-8 character boundaries.
     *
     * Public so tests / handlers can use it directly.
     */
    public function maybeTruncate(string $content, int $maxBytes = self::MAX_BYTES): string
    {
        if (strlen($content) <= $maxBytes) {
            return $content;
        }

        Craft::warning(
            sprintf('DiffRenderer: truncating content from %d to %d bytes', strlen($content), $maxBytes),
            'audit'
        );

        $truncated = substr($content, 0, $maxBytes);
        // Fix any UTF-8 multi-byte sequence we may have cut in half.
        // mb_convert_encoding with 'UTF-8'/'UTF-8' normalizes the string.
        $truncated = mb_convert_encoding($truncated, 'UTF-8', 'UTF-8');

        $kb = (int)round($maxBytes / 1000);
        return $truncated . "\n[… content truncated at {$kb}KB]";
    }

    /**
     * Truncate to maxLines lines (for line-level diffs on large text).
     */
    public function maybeTruncateLines(string $content, int $maxLines = self::MAX_LINES): string
    {
        $lines = explode("\n", $content);
        if (count($lines) <= $maxLines) {
            return $content;
        }

        $omitted = count($lines) - $maxLines;
        $truncated = array_slice($lines, 0, $maxLines);
        $truncated[] = "[… {$omitted} more lines truncated]";

        return implode("\n", $truncated);
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Build (and cache) the shared HtmlDiffConfig.
     */
    private function getHtmlDiffConfig(): HtmlDiffConfig
    {
        if ($this->htmlDiffConfig !== null) {
            return $this->htmlDiffConfig;
        }

        $runtimePath = Craft::$app->path->getRuntimePath() . '/htmlpurifier';

        // Note: chain is broken into individual calls because caxy/php-htmldiff's
        // setMatchThreshold has a wrong @return docblock (says AbstractDiff instead
        // of HtmlDiffConfig), which confuses PHPStan when chaining further setters.
        $config = HtmlDiffConfig::create();
        $config->setMatchThreshold(80);
        $config->setInsertSpaceInReplace(true);
        $config->setGroupDiffs(true);
        $config->setUseTableDiffing(true);
        $config->setEncoding('UTF-8');
        $config->setPurifierEnabled(true);
        $config->setPurifierCacheLocation($runtimePath);
        $config->setKeepNewLines(false);

        return $this->htmlDiffConfig = $config;
    }

    /**
     * Standard "no changes" result.
     */
    private function identicalResult(): string
    {
        return '<span class="audit-diff-badge audit-diff-badge--identical">No changes</span>';
    }

    /**
     * Standard error result (diff failed AND simple diff unavailable).
     */
    private function errorResult(): string
    {
        return '<span class="audit-diff-badge audit-diff-badge--error">Diff unavailable</span>';
    }

    /**
     * Coerce any value into a display string (best-effort).
     */
    private function toScalarString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string)$value;
        }
        // Arrays / objects → compact JSON
        $encoded = @json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '[unserializable]' : $encoded;
    }
}
