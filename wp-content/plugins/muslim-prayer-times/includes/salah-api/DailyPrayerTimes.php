<?php

namespace SalahAPI;

/**
 * DailyPrayerTimes Object
 * 
 * Provides a reference to the CSV data containing daily prayer times.
 */
class DailyPrayerTimes
{
    /**
     * @var string The URL of the endpoint that serves prayer times in CSV format.
     */
    public string $csvUrl;

    /**
     * @var CsvUrlParameters|null URL parameters that may be passed to the CSV endpoint.
     */
    public ?CsvUrlParameters $csvUrlParameters = null;

    /**
     * @var string The date format pattern used in the CSV (e.g., "YYYY-MM-DD").
     */
    public string $dateFormat;

    /**
     * @var string The time format pattern used in the CSV (e.g., "HH:mm" or "hh:mm A").
     */
    public string $timeFormat;

    /**
     * Constructor
     * 
     * @param string $csvUrl CSV endpoint URL
     * @param string $dateFormat Date format pattern
     * @param string $timeFormat Time format pattern
     * @param CsvUrlParameters|null $csvUrlParameters URL parameters
     */
    public function __construct(
        string $csvUrl,
        string $dateFormat,
        string $timeFormat,
        ?CsvUrlParameters $csvUrlParameters = null
    ) {
        $this->csvUrl = $csvUrl;
        $this->dateFormat = $dateFormat;
        $this->timeFormat = $timeFormat;
        $this->csvUrlParameters = $csvUrlParameters;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'csvUrl' => $this->csvUrl,
            'dateFormat' => $this->dateFormat,
            'timeFormat' => $this->timeFormat,
        ];
        
        if ($this->csvUrlParameters !== null) {
            $data['csvUrlParameters'] = $this->csvUrlParameters->toArray();
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
        $csvUrlParameters = null;
        if (isset($data['csvUrlParameters'])) {
            $csvUrlParameters = CsvUrlParameters::fromArray($data['csvUrlParameters']);
        }

        return new self(
            $data['csvUrl'],
            $data['dateFormat'],
            $data['timeFormat'],
            $csvUrlParameters
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
