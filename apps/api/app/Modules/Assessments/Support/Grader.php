<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Support;

use App\Modules\Assessments\Models\Question;
use App\Support\Text\SearchText;

/** Closed questions grade themselves. */
final class Grader
{
    public static function correct(Question $question, mixed $answer): bool
    {
        return match ($question->kind) {
            'mcq' => is_string($answer) && $answer === $question->answer,
            'true_false' => is_bool($answer) && $answer === $question->answer,
            'fill' => is_string($answer) && in_array(self::fold($answer), array_map(self::fold(...), (array) $question->answer), true),
            default => false,
        };
    }

    /** Is this a well-formed answer to the question (an option it has, a bool, a short text)? */
    public static function wellFormed(Question $question, mixed $answer): bool
    {
        return match ($question->kind) {
            'mcq' => is_string($answer) && in_array($answer, array_column($question->options ?? [], 'id'), true),
            'true_false' => is_bool($answer),
            'fill' => is_string($answer) && mb_strlen($answer) <= 200,
            default => false,
        };
    }

    /** «  المِيتوكوندريا » = «الميتوكوندريا»: Arabic folding, case, spaces. */
    public static function fold(string $text): string
    {
        return SearchText::normalize($text);
    }
}
