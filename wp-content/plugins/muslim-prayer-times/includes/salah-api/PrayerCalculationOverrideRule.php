<?php

namespace SalahAPI;

/**
 * PrayerCalculationOverrideRule Object
 * 
 * Specifies a date-specific override for a prayer time calculation rule.
 */
class PrayerCalculationOverrideRule
{
    /**
     * @var string The type of override rule. One of: "daylightSavingsTime", "ramadan".
     */
    public string $condition;

    /**
     * @var PrayerCalculationRule The prayer calculation rule to apply for the override.
     */
    public PrayerCalculationRule $time;

    /**
     * Constructor
     * 
     * @param string $condition The type of override rule
     * @param PrayerCalculationRule $time The prayer calculation rule
     * @param string|null $fromDate Start date (required if condition is "dateRange")
     * @param string|null $toDate End date (required if condition is "dateRange")
     */
    public function __construct(
        string $condition,
        PrayerCalculationRule $time
    ) {
        $this->condition = $condition;
        $this->time = $time;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'condition' => $this->condition,
            'time' => $this->time->toArray(),
        ];
        
        return $data;
    }

    /**
     * Create from array
     * 
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['condition'],
            PrayerCalculationRule::fromArray($data['time'])
        );
    }

    /**
     * Convert to JSON
     * 
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
