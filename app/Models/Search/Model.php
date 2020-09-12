<?php

namespace ForkBB\Models\Search;

use ForkBB\Models\Model as ParentModel;

class Model extends ParentModel
{
    const CJK_REGEX = '['.
        '\x{1100}-\x{11FF}'.  // Hangul Jamo
        '\x{3130}-\x{318F}'.  // Hangul Compatibility Jamo
        '\x{AC00}-\x{D7AF}'.  // Hangul Syllables

        // Hiragana
        '\x{3040}-\x{309F}'.  // Hiragana

        // Katakana
        '\x{30A0}-\x{30FF}'.   // Katakana
        '\x{31F0}-\x{31FF}'.   // Katakana Phonetic Extensions

        // CJK Unified Ideographs    (http://en.wikipedia.org/wiki/CJK_Unified_Ideographs)
        '\x{2E80}-\x{2EFF}'.   // CJK Radicals Supplement
        '\x{2F00}-\x{2FDF}'.   // Kangxi Radicals
        '\x{2FF0}-\x{2FFF}'.   // Ideographic Description Characters
        '\x{3000}-\x{303F}'.   // CJK Symbols and Punctuation
        '\x{31C0}-\x{31EF}'.   // CJK Strokes
        '\x{3200}-\x{32FF}'.   // Enclosed CJK Letters and Months
        '\x{3400}-\x{4DBF}'.   // CJK Unified Ideographs Extension A
        '\x{4E00}-\x{9FFF}'.   // CJK Unified Ideographs
        '\x{20000}-\x{2A6DF}'. // CJK Unified Ideographs Extension B
        ']';

    /**
     * Ссылка на результат поиска
     */
    protected function getlink(): string
    {
        return $this->c->Router->link(
            $this->linkMarker,
            $this->linkArgs
        );
    }

    /**
     * Массив страниц результата поиска
     */
    protected function getpagination(): array
    {
        return $this->c->Func->paginate($this->numPages, $this->page, $this->linkMarker, $this->linkArgs);
    }

    /**
     * Статус наличия установленной страницы в результате поиска
     */
    public function hasPage(): bool
    {
        return $this->page > 0 && $this->page <= $this->numPages;
    }

    /**
     * Очистка текста для дальнейшего разделения на слова
     */
    public function cleanText(string $text, bool $indexing = false): string
    {
        $text = \str_replace(['`', '’', 'ё'], ['\'', '\'', 'е'], $text);
        // четыре одинаковых буквы в одну
        $text = \preg_replace('%(\p{L})\1{3,}%u', '\1', $text);
        // удаление ' и - вне слов
        $text = \preg_replace('%((?<![\p{L}\p{N}])[\'\-]|[\'\-](?![\p{L}\p{N}]))%u', ' ', $text);

        if (false !== \strpos($text, '-')) {
            // удаление слов c -либо|нибу[дт]ь|нить
            $text = \preg_replace('%\b[\p{L}\p{N}\-\']+\-(?:либо|нибу[дт]ь|нить)(?![\p{L}\p{N}\'\-])%u', '', $text);
            // удаление из слов все хвосты с 1 или 2 русскими буквами или -таки|чуть
            $text = \preg_replace('%(?<=[\p{L}\p{N}])(\-(?:таки|чуть|[а-я]{1,2}))+(?![\p{L}\p{N}\'\-])%u', '', $text);
        }

        // удаление символов отличающихся от букв и цифр
        $text = \preg_replace('%(?![\'\-'.($indexing ? '' : '\?\*').'])[^\p{L}\p{N}]+%u', ' ', $text);
        // сжатие пробелов
        $text = \preg_replace('% {2,}%', ' ', $text);

        return \trim($text);
    }

    /**
     * Проверка слова на:
     *  стоп-слово
     *  слово из языков CJK
     *  длину
     */
    public function word(string $word, bool $indexing = false): ?string
    {
        if (isset($this->c->stopwords->list[$word])) {
            return null;
        }

        if ($this->isCJKWord($word)) {
            return $indexing ? null : $word;
        }

        $len = \mb_strlen(\trim($word, '?*'), 'UTF-8');

        if ($len < 3) {
            return null;
        }

        if ($len > 20) {
            $word = \mb_substr($word, 0, 20, 'UTF-8');
        }

        return $word;
    }

    /**
     * Проверка слова на язык CJK
     */
    public function isCJKWord(string $word): bool
    {
        return \preg_match('%' . self::CJK_REGEX . '%u', $word) ? true : false; //?????
    }
}
