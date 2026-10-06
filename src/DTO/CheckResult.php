<?php

namespace Anivesh\LaravelProductionGuard\DTO;

use Anivesh\LaravelProductionGuard\Enums\CheckStatus;

final class CheckResult
{
    /** @var string */
    private $name;

    /** @var string */
    private $category;

    /** @var string */
    private $status;

    /** @var float */
    private $score;

    /** @var float */
    private $maxScore;

    /** @var string */
    private $message;

    /** @var array */
    private $details;

    /** @var array */
    private $recommendations;

    /**
     * @param string $name
     * @param string $category
     * @param string $status one of the Anivesh\LaravelProductionGuard\Enums\CheckStatus constants
     * @param float $score
     * @param float $maxScore
     * @param string $message
     * @param array $details
     * @param array $recommendations
     */
    public function __construct($name, $category, $status, $score, $maxScore, $message, array $details = [], array $recommendations = [])
    {
        $this->name = $name;
        $this->category = $category;
        $this->status = $status;
        $this->score = $score;
        $this->maxScore = $maxScore;
        $this->message = $message;
        $this->details = $details;
        $this->recommendations = $recommendations;
    }

    /**
     * Build an UNKNOWN result for a checker that threw an exception, so one
     * failing checker never aborts the whole run.
     *
     * @param string $name
     * @param string $category
     * @param \Throwable|\Exception $e
     * @return self
     */
    public static function fromException($name, $category, $e)
    {
        return new self(
            $name,
            $category,
            CheckStatus::UNKNOWN,
            0,
            0,
            'Check could not complete: ' . $e->getMessage(),
            [],
            []
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return float
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * @return float
     */
    public function getMaxScore()
    {
        return $this->maxScore;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return array
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * @return array
     */
    public function getRecommendations()
    {
        return $this->recommendations;
    }

    /**
     * @return bool
     */
    public function isScorable()
    {
        return in_array($this->status, CheckStatus::scorable(), true);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'category' => $this->category,
            'name' => $this->name,
            'status' => $this->status,
            'score' => $this->score,
            'max_score' => $this->maxScore,
            'message' => $this->message,
            'details' => $this->details,
            'recommendations' => $this->recommendations,
        ];
    }
}
