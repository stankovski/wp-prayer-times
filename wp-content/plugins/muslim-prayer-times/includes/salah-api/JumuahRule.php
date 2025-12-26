<?php

namespace SalahAPI;

/**
 * JumuahRule Object
 * 
 * Specifies a single Jumuah (Friday prayer) rule.
 */
class JumuahRule
{
    /**
     * @var string|null The name of the Jumuah prayer (e.g., "Jumuah 1", "Youth Jumuah").
     */
    public ?string $name = null;

    /**
     * @var PrayerCalculationRule|null Calculation rule for Jumuah time.
     */
    public ?PrayerCalculationRule $time = null;

    /**
     * @var JumuahLocation|null Location information for the Jumuah prayer.
     */
    public ?JumuahLocation $location = null;

    /**
     * Constructor
     * 
     * @param string|null $name Name of the Jumuah prayer
     * @param PrayerCalculationRule|null $time Calculation rule for Jumuah time
     * @param JumuahLocation|null $location Location information
     */
    public function __construct(
        ?string $name = null,
        ?PrayerCalculationRule $time = null,
        ?JumuahLocation $location = null
    ) {
        $this->name = $name;
        $this->time = $time;
        $this->location = $location;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        
        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        
        if ($this->time !== null) {
            $data['time'] = $this->time->toArray();
        }
        
        if ($this->location !== null) {
            $data['location'] = $this->location->toArray();
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
        $time = null;
        if (isset($data['time'])) {
            $time = PrayerCalculationRule::fromArray($data['time']);
        }

        $location = null;
        if (isset($data['location'])) {
            $location = JumuahLocation::fromArray($data['location']);
        }

        return new self(
            $data['name'] ?? null,
            $time,
            $location
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
