<?php

namespace SalahAPI;

/**
 * PrayerCalculationRule Object
 * 
 * Specifies a single prayer time calculation rule.
 */
class PrayerCalculationRule
{
    /**
     * @var string|null A static time value in 24-hour format (e.g., "12:30"). When specified, all other fields are ignored.
     */
    public ?string $static = null;

    /**
     * @var string|null The frequency of change. Either "daily" or "weekly".
     */
    public ?string $change = null;

    /**
     * @var int|null The rounding interval in minutes (e.g., 15 for quarter-hour rounding).
     */
    public ?int $roundMinutes = null;

    /**
     * @var string|null The earliest allowed time in 24-hour format (e.g., "04:00").
     */
    public ?string $earliest = null;

    /**
     * @var string|null The latest allowed time in 24-hour format (e.g., "23:45").
     */
    public ?string $latest = null;

    /**
     * @var int|null The delay in minutes after the Athan (call to prayer).
     */
    public ?int $afterAthanMinutes = null;

    /**
     * @var int|null The number of minutes before the end of the prayer timeframe.
     */
    public ?int $beforeEndMinutes = null;

    /**
     * @var array<PrayerCalculationOverrideRule>|null An array of date-specific overrides.
     */
    public ?array $overrides = null;

    /**
     * Constructor
     * 
     * @param string|null $static Static time value
     * @param string|null $change Frequency of change
     * @param int|null $roundMinutes Rounding interval in minutes
     * @param string|null $earliest Earliest allowed time
     * @param string|null $latest Latest allowed time
     * @param int|null $afterAthanMinutes Delay after Athan in minutes
     * @param int|null $beforeEndMinutes Minutes before end of prayer timeframe
     * @param array<PrayerCalculationOverrideRule>|null $overrides Date-specific overrides
     */
    public function __construct(
        ?string $static = null,
        ?string $change = null,
        ?int $roundMinutes = null,
        ?string $earliest = null,
        ?string $latest = null,
        ?int $afterAthanMinutes = null,
        ?int $beforeEndMinutes = null,
        ?array $overrides = null
    ) {
        $this->static = $static;
        $this->change = $change;
        $this->roundMinutes = $roundMinutes;
        $this->earliest = $earliest;
        $this->latest = $latest;
        $this->afterAthanMinutes = $afterAthanMinutes;
        $this->beforeEndMinutes = $beforeEndMinutes;
        $this->overrides = $overrides;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        
        if ($this->static !== null) {
            $data['static'] = $this->static;
        }
        
        if ($this->change !== null) {
            $data['change'] = $this->change;
        }
        
        if ($this->roundMinutes !== null) {
            $data['roundMinutes'] = (string) $this->roundMinutes;
        }
        
        if ($this->earliest !== null) {
            $data['earliest'] = $this->earliest;
        }
        
        if ($this->latest !== null) {
            $data['latest'] = $this->latest;
        }
        
        if ($this->afterAthanMinutes !== null) {
            $data['afterAthanMinutes'] = (string) $this->afterAthanMinutes;
        }
        
        if ($this->beforeEndMinutes !== null) {
            $data['beforeEndMinutes'] = (string) $this->beforeEndMinutes;
        }
        
        if ($this->overrides !== null && count($this->overrides) > 0) {
            $data['overrides'] = array_map(function ($override) {
                return $override->toArray();
            }, $this->overrides);
        }
        
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
        $overrides = null;
        if (isset($data['overrides']) && is_array($data['overrides'])) {
            $overrides = array_map(function ($overrideData) {
                return PrayerCalculationOverrideRule::fromArray($overrideData);
            }, $data['overrides']);
        }

        return new self(
            $data['static'] ?? null,
            $data['change'] ?? null,
            isset($data['roundMinutes']) ? (int) $data['roundMinutes'] : null,
            $data['earliest'] ?? null,
            $data['latest'] ?? null,
            isset($data['afterAthanMinutes']) ? (int) $data['afterAthanMinutes'] : null,
            isset($data['beforeEndMinutes']) ? (int) $data['beforeEndMinutes'] : null,
            $overrides
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
