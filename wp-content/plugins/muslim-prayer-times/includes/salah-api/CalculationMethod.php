<?php

namespace SalahAPI;

/**
 * CalculationMethod Object
 * 
 * Specifies the parameters used for calculating prayer times.
 */
class CalculationMethod
{
    /**
     * @var string The calculation method identifier.
     */
    public string $name;

    /**
     * @var float|null The angle of the sun below the horizon for Fajr calculation, in degrees.
     */
    public ?float $fajrAngle = null;

    /**
     * @var float|null The angle of the sun below the horizon for Isha calculation, in degrees.
     */
    public ?float $ishaAngle = null;

    /**
     * @var string|null The method for calculating Asr prayer time.
     */
    public ?string $asrCalculationMethod = null;

    /**
     * @var string|null The adjustment method for high latitude locations.
     */
    public ?string $highLatitudeAdjustment = null;

    /**
     * @var IqamaCalculationRules|null Rules for calculating Iqama times.
     */
    public ?IqamaCalculationRules $iqamaCalculationRules = null;

    /**
     * @var array<JumuahRule>|null An array of Jumuah (Friday prayer) rules.
     */
    public ?array $jumuahRules = null;

    /**
     * Constructor
     * 
     * @param string $name Calculation method identifier
     * @param float|null $fajrAngle Fajr angle in degrees
     * @param float|null $ishaAngle Isha angle in degrees
     * @param string|null $asrCalculationMethod Asr calculation method
     * @param string|null $highLatitudeAdjustment High latitude adjustment method
     * @param IqamaCalculationRules|null $iqamaCalculationRules Iqama calculation rules
     * @param array<JumuahRule>|null $jumuahRules Jumuah rules
     */
    public function __construct(
        string $name,
        ?float $fajrAngle = null,
        ?float $ishaAngle = null,
        ?string $asrCalculationMethod = null,
        ?string $highLatitudeAdjustment = null,
        ?IqamaCalculationRules $iqamaCalculationRules = null,
        ?array $jumuahRules = null
    ) {
        $this->name = $name;
        $this->fajrAngle = $fajrAngle;
        $this->ishaAngle = $ishaAngle;
        $this->asrCalculationMethod = $asrCalculationMethod;
        $this->highLatitudeAdjustment = $highLatitudeAdjustment;
        $this->iqamaCalculationRules = $iqamaCalculationRules;
        $this->jumuahRules = $jumuahRules;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
        ];
        
        if ($this->fajrAngle !== null) {
            $data['fajrAngle'] = $this->fajrAngle;
        }
        
        if ($this->ishaAngle !== null) {
            $data['ishaAngle'] = $this->ishaAngle;
        }
        
        if ($this->asrCalculationMethod !== null) {
            $data['asrCalculationMethod'] = $this->asrCalculationMethod;
        }
        
        if ($this->highLatitudeAdjustment !== null) {
            $data['highLatitudeAdjustment'] = $this->highLatitudeAdjustment;
        }
        
        if ($this->iqamaCalculationRules !== null) {
            $data['iqamaCalculationRules'] = $this->iqamaCalculationRules->toArray();
        }
        
        if ($this->jumuahRules !== null && count($this->jumuahRules) > 0) {
            $data['jumuahRules'] = array_map(function ($rule) {
                return $rule->toArray();
            }, $this->jumuahRules);
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
        $iqamaCalculationRules = null;
        if (isset($data['iqamaCalculationRules'])) {
            $iqamaCalculationRules = IqamaCalculationRules::fromArray($data['iqamaCalculationRules']);
        }

        $jumuahRules = null;
        if (isset($data['jumuahRules']) && is_array($data['jumuahRules'])) {
            $jumuahRules = array_map(function ($ruleData) {
                return JumuahRule::fromArray($ruleData);
            }, $data['jumuahRules']);
        }

        return new self(
            $data['name'],
            $data['fajrAngle'] ?? null,
            $data['ishaAngle'] ?? null,
            $data['asrCalculationMethod'] ?? null,
            $data['highLatitudeAdjustment'] ?? null,
            $iqamaCalculationRules,
            $jumuahRules
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
