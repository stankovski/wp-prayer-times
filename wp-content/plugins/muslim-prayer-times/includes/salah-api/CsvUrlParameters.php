<?php

namespace SalahAPI;

/**
 * CsvUrlParameters Object
 * 
 * Specifies optional URL parameters that may be passed to the CSV endpoint.
 */
class CsvUrlParameters
{
    /**
     * @var array<string, array<string, mixed>> URL parameters configuration.
     */
    private array $parameters = [];

    /**
     * Constructor
     * 
     * @param array<string, array<string, mixed>> $parameters URL parameters configuration
     */
    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /**
     * Add a static value parameter
     * 
     * @param string $name Parameter name
     * @param string $in Location where the parameter is sent ("query", "path", or "header")
     * @param string|int|float $value Static value
     * @return self
     */
    public function addStaticParameter(string $name, string $in, $value): self
    {
        $this->parameters[$name] = [
            'in' => $in,
            'value' => $value,
        ];
        return $this;
    }

    /**
     * Add a date parameter
     * 
     * @param string $name Parameter name
     * @param string $in Location where the parameter is sent ("query", "path", or "header")
     * @param string $type Date parameter type ("fromDate" or "toDate")
     * @param string $format Date format pattern
     * @return self
     */
    public function addDateParameter(string $name, string $in, string $type, string $format): self
    {
        $this->parameters[$name] = [
            'in' => $in,
            'type' => $type,
            'format' => $format,
        ];
        return $this;
    }

    /**
     * Get a parameter by name
     * 
     * @param string $name Parameter name
     * @return array<string, mixed>|null
     */
    public function getParameter(string $name): ?array
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * Get all parameters
     * 
     * @return array<string, array<string, mixed>>
     */
    public function getAllParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Convert to array
     * 
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->parameters;
    }

    /**
     * Create from array
     * 
     * @param array<string, array<string, mixed>> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
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
