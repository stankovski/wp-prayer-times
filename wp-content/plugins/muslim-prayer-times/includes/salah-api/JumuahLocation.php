<?php

namespace SalahAPI;

/**
 * JumuahLocation Object
 * 
 * Specifies the location for a Jumuah prayer.
 */
class JumuahLocation
{
    /**
     * @var string|null The name of the location (e.g., "Main Prayer Hall", "Community Center").
     */
    public ?string $name = null;

    /**
     * @var string|null The physical address of the location.
     */
    public ?string $address = null;

    /**
     * Constructor
     * 
     * @param string|null $name Location name
     * @param string|null $address Physical address
     */
    public function __construct(?string $name = null, ?string $address = null)
    {
        $this->name = $name;
        $this->address = $address;
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
        
        if ($this->address !== null) {
            $data['address'] = $this->address;
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
            $data['name'] ?? null,
            $data['address'] ?? null
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
