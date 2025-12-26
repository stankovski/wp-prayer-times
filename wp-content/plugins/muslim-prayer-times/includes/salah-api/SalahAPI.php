<?php

namespace SalahAPI;

/**
 * SalahAPI - Prayer Times Document
 * 
 * Represents a SalahAPI document structure as defined in version 1.0 of the specification.
 */
class SalahAPI
{
    /**
     * @var string The version of the SalahAPI Specification that the document conforms to.
     */
    public string $salahapi;

    /**
     * @var Info|null Metadata describing the prayer times data.
     */
    public ?Info $info = null;

    /**
     * @var Location|null Geographic coordinates and timezone information.
     */
    public ?Location $location = null;

    /**
     * @var CalculationMethod|null Parameters used for prayer time calculations.
     */
    public ?CalculationMethod $calculationMethod = null;

    /**
     * @var DailyPrayerTimes|null Reference to the CSV prayer times data.
     */
    public ?DailyPrayerTimes $dailyPrayerTimes = null;

    /**
     * Constructor
     * 
     * @param string $salahapi SalahAPI specification version
     * @param Info|null $info Metadata about the prayer times data
     * @param Location|null $location Geographic location information
     * @param CalculationMethod|null $calculationMethod Calculation method parameters
     * @param DailyPrayerTimes|null $dailyPrayerTimes Daily prayer times CSV reference
     */
    public function __construct(
        string $salahapi = '1.0',
        ?Info $info = null,
        ?Location $location = null,
        ?CalculationMethod $calculationMethod = null,
        ?DailyPrayerTimes $dailyPrayerTimes = null
    ) {
        $this->salahapi = $salahapi;
        $this->info = $info;
        $this->location = $location;
        $this->calculationMethod = $calculationMethod;
        $this->dailyPrayerTimes = $dailyPrayerTimes;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'salahapi' => $this->salahapi,
        ];
        
        if ($this->info !== null) {
            $data['info'] = $this->info->toArray();
        }
        
        if ($this->location !== null) {
            $data['location'] = $this->location->toArray();
        }
        
        if ($this->calculationMethod !== null) {
            $data['calculationMethod'] = $this->calculationMethod->toArray();
        }
        
        if ($this->dailyPrayerTimes !== null) {
            $data['dailyPrayerTimes'] = $this->dailyPrayerTimes->toArray();
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
        $info = null;
        if (isset($data['info'])) {
            $info = Info::fromArray($data['info']);
        }

        $location = null;
        if (isset($data['location'])) {
            $location = Location::fromArray($data['location']);
        }

        $calculationMethod = null;
        if (isset($data['calculationMethod'])) {
            $calculationMethod = CalculationMethod::fromArray($data['calculationMethod']);
        }

        $dailyPrayerTimes = null;
        if (isset($data['dailyPrayerTimes'])) {
            $dailyPrayerTimes = DailyPrayerTimes::fromArray($data['dailyPrayerTimes']);
        }

        return new self(
            $data['salahapi'] ?? '1.0',
            $info,
            $location,
            $calculationMethod,
            $dailyPrayerTimes
        );
    }

    /**
     * Create from JSON string
     * 
     * @param string $json JSON string
     * @return self
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if ($data === null) {
            throw new \InvalidArgumentException('Invalid JSON string');
        }
        return self::fromArray($data);
    }

    /**
     * Convert to JSON
     * 
     * @param int $options JSON encoding options
     * @return string
     */
    public function toJson(int $options = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $options);
    }
}
