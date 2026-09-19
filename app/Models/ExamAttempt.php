<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    protected $fillable = [
        'user_id', 'exam_id', 'attempt_number', 'started_at', 'completed_at', 
        'score', 'raw_score', 'total_raw_score', 
        'total_questions', 'is_passed', 'status', 'grading_status', 'focus_escape_count'
    ];
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'score' => 'float',
            'raw_score' => 'float',
            'total_raw_score' => 'float',
            'total_questions' => 'integer',
            'is_passed' => 'boolean',
        ];
    }

    public function getAttemptNumberAttribute($value): int
    {
        if (!empty($value)) {
            return (int)$value;
        }

        if ($this->exists && $this->user_id && $this->exam_id) {
            return (int)static::where('user_id', $this->user_id)
                ->where('exam_id', $this->exam_id)
                ->where('id', '<=', $this->id)
                ->count() ?: 1;
        }

        return 1;
    }

    public function isScaled(): bool
    {
        if ($this->total_raw_score === null || $this->total_raw_score <= 0) {
            return false;
        }
        $examTotal = (float)($this->exam->total_score ?? 0);
        if (abs((float)$this->total_raw_score - $examTotal) > 0.001) {
            return true;
        }
        if ($this->exam && $this->exam->sections()->whereNotNull('total_score')->exists()) {
            return true;
        }
        return false;
    }

    /**
     * Calculate final score, raw score, total raw score, and pass status.
     * Supports both section-level proportional weighting (when sections have total_score set)
     * and global proportional scaling (backward compatible).
     *
     * @return array{score: float, raw_score: float, total_raw_score: float, is_passed: ?bool}
     */
    public function calculateFinalScore(): array
    {
        $this->loadMissing(['exam.questions', 'exam.sections', 'studentAnswers']);
        $exam = $this->exam;
        $questions = $exam->questions;
        $sections = $exam->sections;
        $answers = $this->studentAnswers->keyBy('question_id');

        $rawScore = 0.0;
        foreach ($questions as $q) {
            $ans = $answers->get($q->id);
            $rawScore += (float)($ans->score_awarded ?? 0.0);
        }

        $totalRawScore = (float)$questions->sum('score');
        $examTargetScore = (float)($exam->total_score ?? $totalRawScore);

        $hasSectionScores = $sections->isNotEmpty() && $sections->contains(function ($s) {
            return $s->total_score !== null && (float)$s->total_score > 0;
        });

        if ($hasSectionScores) {
            $finalScore = 0.0;
            $groupedQuestions = $questions->groupBy('exam_section_id');

            foreach ($groupedQuestions as $secId => $secQuestions) {
                $secRawTotal = (float)$secQuestions->sum('score');
                $secRawEarned = 0.0;
                foreach ($secQuestions as $sq) {
                    $ans = $answers->get($sq->id);
                    $secRawEarned += (float)($ans->score_awarded ?? 0.0);
                }

                $section = $secId ? $sections->firstWhere('id', $secId) : null;
                if ($section && $section->total_score !== null && (float)$section->total_score > 0) {
                    $secTarget = (float)$section->total_score;
                    $secFinal = $secRawTotal > 0 ? ($secRawEarned / $secRawTotal) * $secTarget : 0.0;
                    $finalScore += $secFinal;
                } else {
                    $finalScore += $secRawEarned;
                }
            }

            $finalScore = round($finalScore, 2);
        } else {
            if ($totalRawScore > 0 && $examTargetScore > 0) {
                $finalScore = round(($rawScore / $totalRawScore) * $examTargetScore, 2);
            } else {
                $finalScore = round($rawScore, 2);
            }
        }

        // Check if there are ungraded essay questions without answer keys
        $hasEssayWithoutKey = $questions->where('type', 'essay')->contains(function ($q) {
            return empty(trim($q->essay_answer ?? ''));
        });
        $isPendingGrading = $this->isPendingGrading() || ($this->grading_status === null && $hasEssayWithoutKey);

        $isPassed = null;
        if (!$isPendingGrading) {
            $passingPercentage = (float)($exam->passing_percentage ?? 0);
            $percentageObtained = $examTargetScore > 0 ? ($finalScore / $examTargetScore) * 100 : 0;
            $isPassed = $percentageObtained >= $passingPercentage;
        }

        return [
            'score' => $finalScore,
            'raw_score' => round($rawScore, 2),
            'total_raw_score' => round($totalRawScore, 2),
            'is_passed' => $isPassed,
        ];
    }

    public function isPendingGrading(): bool
    {
        if ($this->grading_status === 'graded') {
            return false;
        }

        if ($this->grading_status === 'pending_grading') {
            if ($this->relationLoaded('exam') && $this->exam && $this->exam->relationLoaded('questions')) {
                $essayQuestions = $this->exam->questions->where('type', 'essay');
                if ($essayQuestions->isNotEmpty()) {
                    $hasEssayWithoutKey = $essayQuestions->contains(function ($q) {
                        return empty(trim($q->essay_answer ?? ''));
                    });
                    if (!$hasEssayWithoutKey) {
                        return false;
                    }
                }
            }
            return true;
        }

        return false;
    }

    public function isGraded(): bool
    {
        return $this->grading_status === 'graded' || !$this->isPendingGrading();
    }

    /**
     * Get a breakdown of scores by question type (choice vs essay).
     *
     * @return array{
     *   has_choice: bool,
     *   has_essay: bool,
     *   all_essays_have_key: bool,
     *   has_essay_without_key: bool,
     *   is_pending_essay: bool,
     *   choice_score: float,
     *   choice_total: float,
     *   choice_raw_score: float,
     *   choice_raw_total: float,
     *   essay_score: float,
     *   essay_total: float,
     *   essay_raw_score: float,
     *   essay_raw_total: float,
     *   total_score: float,
     *   total_exam_score: float,
     *   raw_score: float,
     *   total_raw_score: float,
     *   percentage: float
     * }
     */
    public function getScoreBreakdown(): array
    {
        $this->loadMissing(['exam.questions', 'exam.sections', 'studentAnswers']);
        $exam = $this->exam;
        $questions = $exam->questions;
        $sections = $exam->sections;
        $answers = $this->studentAnswers->keyBy('question_id');

        $choiceQuestions = $questions->where('type', '!=', 'essay');
        $essayQuestions = $questions->where('type', 'essay');

        $hasChoice = $choiceQuestions->isNotEmpty();
        $hasEssay = $essayQuestions->isNotEmpty();

        // Check if teacher provided answer keys for essay questions
        $hasEssayWithoutKey = $hasEssay && $essayQuestions->contains(function ($q) {
            return empty(trim($q->essay_answer ?? ''));
        });
        $allEssaysHaveKey = $hasEssay && !$hasEssayWithoutKey;

        // Is pending essay grading?
        // It is pending if there are essay questions without an answer key AND grading_status is not 'graded'
        $isPendingEssay = $hasEssayWithoutKey && ($this->grading_status !== 'graded');

        // Calculate choice raw score
        $choiceRawTotal = (float)$choiceQuestions->sum('score');
        $choiceRawEarned = 0.0;
        foreach ($choiceQuestions as $q) {
            $ans = $answers->get($q->id);
            if ($ans) {
                $choiceRawEarned += (float)($ans->score_awarded ?? ($ans->is_correct ? $q->score : 0.0));
            }
        }

        // Calculate essay raw score
        $essayRawTotal = (float)$essayQuestions->sum('score');
        $essayRawEarned = 0.0;
        foreach ($essayQuestions as $q) {
            $ans = $answers->get($q->id);
            if ($ans && $ans->score_awarded !== null) {
                $essayRawEarned += (float)$ans->score_awarded;
            } elseif (!empty(trim($q->essay_answer ?? ''))) {
                $studentText = trim($ans->answer_text ?? '');
                $expectedText = trim($q->essay_answer);
                if (!empty($studentText) && mb_strtolower($studentText) === mb_strtolower($expectedText)) {
                    $essayRawEarned += (float)$q->score;
                }
            }
        }

        $totalRawScore = (float)$questions->sum('score');
        $examTargetScore = (float)($exam->total_score ?? $totalRawScore);

        $hasSectionScores = $sections->isNotEmpty() && $sections->contains(function ($s) {
            return $s->total_score !== null && (float)$s->total_score > 0;
        });

        if ($hasSectionScores) {
            $choiceFinal = 0.0;
            $essayFinal = 0.0;
            $choiceTarget = 0.0;
            $essayTarget = 0.0;

            $grouped = $questions->groupBy('exam_section_id');
            foreach ($grouped as $secId => $secQuestions) {
                $sec = $secId ? $sections->firstWhere('id', $secId) : null;
                $secRawTotal = (float)$secQuestions->sum('score');
                $secTarget = ($sec && $sec->total_score !== null && (float)$sec->total_score > 0)
                    ? (float)$sec->total_score
                    : $secRawTotal;

                $secChoiceRawTotal = (float)$secQuestions->where('type', '!=', 'essay')->sum('score');
                $secChoiceRawEarned = 0.0;
                foreach ($secQuestions->where('type', '!=', 'essay') as $q) {
                    $ans = $answers->get($q->id);
                    if ($ans) {
                        $secChoiceRawEarned += (float)($ans->score_awarded ?? ($ans->is_correct ? $q->score : 0.0));
                    }
                }

                $secEssayRawTotal = (float)$secQuestions->where('type', 'essay')->sum('score');
                $secEssayRawEarned = 0.0;
                foreach ($secQuestions->where('type', 'essay') as $q) {
                    $ans = $answers->get($q->id);
                    if ($ans && $ans->score_awarded !== null) {
                        $secEssayRawEarned += (float)$ans->score_awarded;
                    } elseif (!empty(trim($q->essay_answer ?? ''))) {
                        $studentText = trim($ans->answer_text ?? '');
                        $expectedText = trim($q->essay_answer);
                        if (!empty($studentText) && mb_strtolower($studentText) === mb_strtolower($expectedText)) {
                            $secEssayRawEarned += (float)$q->score;
                        }
                    }
                }

                if ($secRawTotal > 0) {
                    $choiceFinal += ($secChoiceRawEarned / $secRawTotal) * $secTarget;
                    $essayFinal += ($secEssayRawEarned / $secRawTotal) * $secTarget;
                    $choiceTarget += ($secChoiceRawTotal / $secRawTotal) * $secTarget;
                    $essayTarget += ($secEssayRawTotal / $secRawTotal) * $secTarget;
                } else {
                    $choiceFinal += $secChoiceRawEarned;
                    $essayFinal += $secEssayRawEarned;
                    $choiceTarget += $secChoiceRawTotal;
                    $essayTarget += $secEssayRawTotal;
                }
            }

            $choiceFinal = round($choiceFinal, 2);
            $essayFinal = round($essayFinal, 2);
            $choiceTarget = round($choiceTarget, 2);
            $essayTarget = round($essayTarget, 2);
        } else {
            if ($totalRawScore > 0 && $examTargetScore > 0) {
                $choiceFinal = round(($choiceRawEarned / $totalRawScore) * $examTargetScore, 2);
                $essayFinal = round(($essayRawEarned / $totalRawScore) * $examTargetScore, 2);
                $choiceTarget = round(($choiceRawTotal / $totalRawScore) * $examTargetScore, 2);
                $essayTarget = round(($essayRawTotal / $totalRawScore) * $examTargetScore, 2);
            } else {
                $choiceFinal = round($choiceRawEarned, 2);
                $essayFinal = round($essayRawEarned, 2);
                $choiceTarget = round($choiceRawTotal, 2);
                $essayTarget = round($essayRawTotal, 2);
            }
        }

        $totalScore = round($choiceFinal + $essayFinal, 2);
        $percentage = $examTargetScore > 0 ? round(($totalScore / $examTargetScore) * 100, 2) : 0.0;

        return [
            'has_choice' => $hasChoice,
            'has_essay' => $hasEssay,
            'all_essays_have_key' => $allEssaysHaveKey,
            'has_essay_without_key' => $hasEssayWithoutKey,
            'is_pending_essay' => $isPendingEssay,
            'choice_score' => $choiceFinal,
            'choice_total' => $choiceTarget,
            'choice_raw_score' => round($choiceRawEarned, 2),
            'choice_raw_total' => round($choiceRawTotal, 2),
            'essay_score' => $essayFinal,
            'essay_total' => $essayTarget,
            'essay_raw_score' => round($essayRawEarned, 2),
            'essay_raw_total' => round($essayRawTotal, 2),
            'total_score' => $totalScore,
            'total_exam_score' => round($examTargetScore, 2),
            'raw_score' => round($choiceRawEarned + $essayRawEarned, 2),
            'total_raw_score' => round($totalRawScore, 2),
            'percentage' => $percentage,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }
}
