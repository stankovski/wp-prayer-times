<?php


namespace IslamicNetwork\Calendar\Helpers;

use IslamicNetwork\Calendar\Models\Holydays;

class Calendar
{
    public static function specialDays()
    {
        $days = [];
        foreach(Holydays::$holydays as $hol => $month) {
            foreach ($month as $day) {
                foreach ($day['events'] as $event) {
                    $days[] = ['month' => $day['month'], 'day' => $day['day'], 'name' => $event['name']];
                }
            }
        }

        return $days;

    }

    public static function getHijriHolidaysDeprected($day, $month)
    {
        $holydays = [];
        $day = (int) $day;
        $month = (int) $month;
        foreach (self::specialDays() as $hol) {
            if ($hol['day'] == $day && $hol['month'] == $month) {
                $holydays[] = $hol['name'];
            }
        }
        return $holydays;
    }

    public static function getHijriHolidays($day, $month)
    {
        $day = (int) $day;
        $month = (int) $month;

        return Holydays::getNames($day, $month);
    }

    public static function hijriWeekdays($gDay = '')
    {
        $week = [
            'Monday' => ['en' => 'Al Athnayn', 'ar' => 'الاثنين'],
            'Tuesday' => ['en' => 'Al Thalaata', 'ar' => 'الثلاثاء'],
            'Wednesday' => ['en' => 'Al Arba\'a', 'ar' => 'الاربعاء'],
            'Thursday' => ['en' => 'Al Khamees', 'ar' => 'الخميس'],
            'Friday' => ['en' => 'Al Juma\'a', 'ar' => 'الجمعة'],
            'Saturday' => ['en' => 'Al Sabt', 'ar' => 'السبت'],
            'Sunday' => ['en' => 'Al Ahad', 'ar' => 'الاحد']
        ];
        if ($gDay == '') {
            return $week;
        } else {
            return $week[$gDay];
        }
    }

    public static function getGregorianMonths(): array
    {
        return [
            1 => ['number' => 1, 'en' => 'January'],
            2 => ['number' => 2,'en' => 'February'],
            3 => ['number' => 3,'en' => 'March'],
            4 => ['number' => 4,'en' => 'April'],
            5 => ['number' => 5,'en' => 'May'],
            6 => ['number' => 6,'en' => 'June'],
            7 => ['number' => 7,'en' => 'July'],
            8 => ['number' => 8,'en' => 'August'],
            9 => ['number' => 9,'en' => 'September'],
            10 => ['number' => 10,'en' => 'October'],
            11 => ['number' => 11,'en' => 'November'],
            12 => ['number' => 12,'en' => 'December']
        ];
    }

    public static function getIslamicMonths(): array
    {
        return [
            1 => ['number' => 1, 'en' => 'Muḥarram', 'ar' => 'مُحَرَّم'],
            2 => ['number' => 2,'en' => 'Ṣafar', 'ar' => 'صَفَر'],
            3 => ['number' => 3,'en' => 'Rabīʿ al-awwal', 'ar' => 'رَبيع الأوّل'],
            4 => ['number' => 4,'en' => 'Rabīʿ al-thānī', 'ar' => 'رَبيع الثاني'],
            5 => ['number' => 5,'en' => 'Jumādá al-ūlá', 'ar' => 'جُمادى الأولى'],
            6 => ['number' => 6,'en' => 'Jumādá al-ākhirah', 'ar' => 'جُمادى الآخرة'],
            7 => ['number' => 7,'en' => 'Rajab', 'ar' => 'رَجَب'],
            8 => ['number' => 8,'en' => 'Shaʿbān', 'ar' => 'شَعْبان'],
            9 => ['number' => 9,'en' => 'Ramaḍān', 'ar' => 'رَمَضان'],
            10 => ['number' => 10,'en' => 'Shawwāl', 'ar' => 'شَوّال'],
            11 => ['number' => 11,'en' => 'Dhū al-Qaʿdah', 'ar' => 'ذوالقعدة'],
            12 => ['number' => 12,'en' => 'Dhū al-Ḥijjah', 'ar' => 'ذوالحجة']
        ];
    }

    public static function getHJCoSALunarSightings(): array
    {
        return [
            // Gregorian => Announced Hijri Date. All days in dd-mm-yyyy
            '27-10-2003' => '01-09-1424',
            '13-11-2004' => '01-10-1425',
            '11-01-2005' => '01-12-1425',
            '20-01-2005' => '10-12-1425',
            '23-09-2006' => '01-09-1427',
            '21-12-2006' => '01-12-1427',
            '30-12-2006' => '10-12-1427',
            '12-10-2007' => '01-10-1428',
            '10-12-2007' => '01-12-1428',
            '19-12-2007' => '10-12-1428',
            '30-09-2008' => '01-10-1429',
            '27-11-2011' => '01-09-1433',
            '10-07-2013' => '01-09-1434',
            '05-11-2013' => '01-01-1435',
            '29-06-2014' => '01-09-1435',
            '15-09-2015' => '01-12-1436',
            '24-09-2015' => '10-12-1436',
            '15-10-2015' => '01-01-1437',
            '03-09-2016' => '01-12-1437',
            '12-09-2016' => '10-12-1437',
            '17-05-2018' => '01-09-1439',
            '10-08-2021' => '01-01-1443',
        ];
    }

}