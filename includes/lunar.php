<?php
require_once __DIR__ . '/../vendor/autoload.php';

use LucNham\LunarCalendar\LunarDateTime;

class LunarConverter
{
    public static function toString(int $day, int $month, int $year): string
    {
        try {
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $lunar = LunarDateTime::fromGregorian($dateString);

            $lunarDay   = $lunar->day;
            $lunarMonth = $lunar->month;
            $lunarYear  = $lunar->year;

            return "Ngày $lunarDay tháng $lunarMonth năm $lunarYear (Âm lịch)";
        } catch (Throwable $e) {
            return "Lỗi: " . $e->getMessage();
        }
    }
}
