<?php

namespace SalahAPI;

/**
 * Contact Object
 * 
 * Specifies contact information for the service maintainer.
 */
class Contact
{
    /**
     * @var string|null The identifying name of the contact person or organization.
     */
    public ?string $name = null;

    /**
     * @var string|null The email address of the contact person or organization.
     */
    public ?string $email = null;

    /**
     * Constructor
     * 
     * @param string|null $name The identifying name of the contact
     * @param string|null $email The email address
     */
    public function __construct(?string $name = null, ?string $email = null)
    {
        $this->name = $name;
        $this->email = $email;
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
        
        if ($this->email !== null) {
            $data['email'] = $this->email;
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
            $data['email'] ?? null
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
