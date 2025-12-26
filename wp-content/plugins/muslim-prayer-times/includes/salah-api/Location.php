<?php

namespace SalahAPI;

/**
 * Location Object
 * 
 * Specifies the geographic location for which prayer times are calculated.
 */
class Location
{
    /**
     * @var float Geographic latitude in decimal degrees. Valid range: -90 to 90.
     */
    public float $latitude;

    /**
     * @var float Geographic longitude in decimal degrees. Valid range: -180 to 180.
     */
    public float $longitude;

    /**
     * @var string IANA timezone identifier (e.g., "America/New_York").
     */
    public string $timezone;

    /**
     * @var string|null The name of the city.
     */
    public ?string $city = null;

    /**
     * @var string|null The name of the country.
     */
    public ?string $country = null;

    /**
     * @var string The date format pattern used throughout the document (e.g., "YYYY-MM-DD").
     */
    public string $dateFormat;

    /**
     * @var string The time format pattern used throughout the document (e.g., "HH:mm" or "hh:mm A").
     */
    public string $timeFormat;

    /**
     * Constructor
     * 
     * @param float $latitude Geographic latitude
     * @param float $longitude Geographic longitude
     * @param string $timezone IANA timezone identifier
     * @param string $dateFormat Date format pattern
     * @param string $timeFormat Time format pattern
     * @param string|null $city City name
     * @param string|null $country Country name
     */
    public function __construct(
        float $latitude,
        float $longitude,
        string $timezone,
        string $dateFormat,
        string $timeFormat,
        ?string $city = null,
        ?string $country = null
    ) {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->timezone = $timezone;
        $this->dateFormat = $dateFormat;
        $this->timeFormat = $timeFormat;
        $this->city = $city;
        $this->country = $country;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
            'dateFormat' => $this->dateFormat,
            'timeFormat' => $this->timeFormat,
        ];
        
        if ($this->city !== null) {
            $data['city'] = $this->city;
        }
        
        if ($this->country !== null) {
            $data['country'] = $this->country;
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
        return new self(
            $data['latitude'],
            $data['longitude'],
            $data['timezone'],
            $data['dateFormat'],
            $data['timeFormat'],
            $data['city'] ?? null,
            $data['country'] ?? null
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
