<?php

namespace SalahAPI;

/**
 * IqamaCalculationRules Object
 * 
 * Specifies the rules for calculating Iqama (congregation prayer start time) times.
 */
class IqamaCalculationRules
{
    /**
     * @var string|null The day of the week when Iqama times change.
     */
    public ?string $changeOn = null;

    /**
     * @var PrayerCalculationRule|null Calculation rule for Fajr Iqama.
     */
    public ?PrayerCalculationRule $fajr = null;

    /**
     * @var PrayerCalculationRule|null Calculation rule for Dhuhr Iqama.
     */
    public ?PrayerCalculationRule $dhuhr = null;

    /**
     * @var PrayerCalculationRule|null Calculation rule for Asr Iqama.
     */
    public ?PrayerCalculationRule $asr = null;

    /**
     * @var PrayerCalculationRule|null Calculation rule for Maghrib Iqama.
     */
    public ?PrayerCalculationRule $maghrib = null;

    /**
     * @var PrayerCalculationRule|null Calculation rule for Isha Iqama.
     */
    public ?PrayerCalculationRule $isha = null;

    /**
     * Constructor
     * 
     * @param string|null $changeOn Day of the week when Iqama times change
     * @param PrayerCalculationRule|null $fajr Fajr Iqama rule
     * @param PrayerCalculationRule|null $dhuhr Dhuhr Iqama rule
     * @param PrayerCalculationRule|null $asr Asr Iqama rule
     * @param PrayerCalculationRule|null $maghrib Maghrib Iqama rule
     * @param PrayerCalculationRule|null $isha Isha Iqama rule
     */
    public function __construct(
        ?string $changeOn = null,
        ?PrayerCalculationRule $fajr = null,
        ?PrayerCalculationRule $dhuhr = null,
        ?PrayerCalculationRule $asr = null,
        ?PrayerCalculationRule $maghrib = null,
        ?PrayerCalculationRule $isha = null
    ) {
        $this->changeOn = $changeOn;
        $this->fajr = $fajr;
        $this->dhuhr = $dhuhr;
        $this->asr = $asr;
        $this->maghrib = $maghrib;
        $this->isha = $isha;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        
        if ($this->changeOn !== null) {
            $data['changeOn'] = $this->changeOn;
        }
        
        if ($this->fajr !== null) {
            $data['fajr'] = $this->fajr->toArray();
        }
        
        if ($this->dhuhr !== null) {
            $data['dhuhr'] = $this->dhuhr->toArray();
        }
        
        if ($this->asr !== null) {
            $data['asr'] = $this->asr->toArray();
        }
        
        if ($this->maghrib !== null) {
            $data['maghrib'] = $this->maghrib->toArray();
        }
        
        if ($this->isha !== null) {
            $data['isha'] = $this->isha->toArray();
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
        $fajr = isset($data['fajr']) ? PrayerCalculationRule::fromArray($data['fajr']) : null;
        $dhuhr = isset($data['dhuhr']) ? PrayerCalculationRule::fromArray($data['dhuhr']) : null;
        $asr = isset($data['asr']) ? PrayerCalculationRule::fromArray($data['asr']) : null;
        $maghrib = isset($data['maghrib']) ? PrayerCalculationRule::fromArray($data['maghrib']) : null;
        $isha = isset($data['isha']) ? PrayerCalculationRule::fromArray($data['isha']) : null;

        return new self(
            $data['changeOn'] ?? null,
            $fajr,
            $dhuhr,
            $asr,
            $maghrib,
            $isha
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
