<?php

namespace SalahAPI;

/**
 * Info Object
 * 
 * Provides metadata about the prayer times data.
 */
class Info
{
    /**
     * @var string|null A human-readable title of the organization or service providing the prayer times.
     */
    public ?string $title = null;

    /**
     * @var string|null A textual description of the organization or service.
     */
    public ?string $description = null;

    /**
     * @var string|null The version identifier of the prayer times data document.
     */
    public ?string $version = null;

    /**
     * @var Contact|null Contact information for the service maintainer.
     */
    public ?Contact $contact = null;

    /**
     * Constructor
     * 
     * @param string|null $title Title of the organization or service
     * @param string|null $description Textual description
     * @param string|null $version Version identifier
     * @param Contact|null $contact Contact information
     */
    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?string $version = null,
        ?Contact $contact = null
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->version = $version;
        $this->contact = $contact;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        
        if ($this->title !== null) {
            $data['title'] = $this->title;
        }
        
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        
        if ($this->version !== null) {
            $data['version'] = $this->version;
        }
        
        if ($this->contact !== null) {
            $data['contact'] = $this->contact->toArray();
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
        $contact = null;
        if (isset($data['contact'])) {
            $contact = Contact::fromArray($data['contact']);
        }

        return new self(
            $data['title'] ?? null,
            $data['description'] ?? null,
            $data['version'] ?? null,
            $contact
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
