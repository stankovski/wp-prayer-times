<?php

namespace stankovski\muslimprayertimes\calendar\Types\Hijri;

class Month
{
    public int $number;
    public string $en;
    public string $ar;
    public int $days;

    public function __construct(int $number, string $en, string $ar, int $days)
    {
        $this->number = $number;
        $this->en = $en;
        $this->ar = $ar;
        $this->days = $days;
    }

}