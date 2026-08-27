<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;
use Throwable;

/**
 * `BROKEN_HTML` (warning) — unbalanced or unparseable markup in the stem.
 *
 * **This check must never stop the batch** (FR-043, FR-046). It is the one
 * of the thirteen that inspects free text of unknown shape, so it is the one
 * that can be surprised. Its answer to being surprised is to *record the
 * finding*, never to throw and never to stay quiet: a parse it cannot
 * complete is itself evidence the markup is broken, which is exactly what
 * this code says. That keeps FR-027's rule intact — a silent `try/catch` is
 * a defect, and this one is not silent.
 *
 * The row is copied verbatim either way. Nothing here rewrites, closes or
 * strips a tag.
 *
 * Balance is counted per tag name rather than parsed into a tree. A tree
 * would answer a question nobody asked — the mirror never renders this text,
 * it stores it — while counting catches the case that actually matters: an
 * opening tag with no closing one, which silently swallows everything after
 * it wherever the text is eventually displayed.
 *
 * Void elements close themselves and are excluded; `<br>` without `</br>` is
 * correct HTML, not a defect.
 *
 * Zero rows on the fixed 2026-08-07 snapshot — query 9 found no markup in
 * any stem at all.
 */
final class BrokenHtmlValidator implements QuestionCheck
{
    /** Elements that never take a closing tag (HTML void elements). */
    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    public function check(QuestionUnderImport $question): ?Finding
    {
        $text = (string) $question->rawText;

        if (! str_contains($text, '<')) {
            return null;
        }

        try {
            $unbalanced = $this->unbalancedTags($text);
        } catch (Throwable $e) {
            // Isolated, logged, run continues — the finding IS the report.
            return new Finding(
                ImportErrorCode::BROKEN_HTML,
                'questions',
                $question->sourceId,
                'Question text could not be parsed for tag balance: '.$e->getMessage(),
                $question->context() + ['unparseable' => true],
            );
        }

        if ($unbalanced === []) {
            return null;
        }

        return new Finding(
            ImportErrorCode::BROKEN_HTML,
            'questions',
            $question->sourceId,
            sprintf('Unbalanced tag(s) in question text: %s.', implode(', ', array_keys($unbalanced))),
            $question->context() + ['unbalanced' => $unbalanced],
        );
    }

    /**
     * Opening minus closing count, per tag name, for every name where the
     * two disagree. A negative number is as broken as a positive one — a
     * stray `</p>` closes something it never opened.
     *
     * @return array<string, int>
     */
    private function unbalancedTags(string $text): array
    {
        preg_match_all('#<\s*(/?)\s*([a-zA-Z][a-zA-Z0-9]*)[^>]*?(/?)\s*>#', $text, $matches, PREG_SET_ORDER);

        $balance = [];

        foreach ($matches as $match) {
            $name = strtolower($match[2]);

            if (in_array($name, self::VOID_ELEMENTS, true) || $match[3] === '/') {
                continue;
            }

            $balance[$name] = ($balance[$name] ?? 0) + ($match[1] === '/' ? -1 : 1);
        }

        return array_filter($balance, static fn (int $n): bool => $n !== 0);
    }
}
