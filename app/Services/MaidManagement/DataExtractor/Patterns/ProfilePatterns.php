<?php

namespace App\Services\MaidManagement\DataExtractor\Patterns;

class ProfilePatterns
{
    public static function name(): array
    {
        return [
            '/Name:\s*([^\n]+?)\s*Date of birth:/i',
            '/Name\s*:\s*([^\n]+?)\s*DOB:/i',
            '/Full Name:\s*([^\n]+?)\s*(?:Date|DOB)/i',
            '/Worker Name:\s*([^\n]+?)\s*(?:Date|DOB)/i',
        ];
    }

    public static function dob(): array
    {
        return [
            '/Date of birth:\s*([0-9\/-]+)\s*Place of birth:/i',
            '/DOB:\s*([0-9\/-]+)\s*(?:Place|POB)/i',
            '/Birth Date:\s*([0-9\/-]+)/i',
        ];
    }

    public static function age(): array
    {
        return [
            '/Age:\s*([0-9]+)/i',
            '/Age\s*:\s*([0-9]+)\s*(?:years|yrs|y\.o)/i',
        ];
    }

    public static function birthPlace(): array
    {
        return [
            '/Place of birth:\s*([^\n]+?)\s*Height/i',
            '/POB:\s*([^\n]+?)\s*Height/i',
            '/Birth Place:\s*([^\n]+?)\s*(?:Height|$)/i',
        ];
    }

    public static function heightWeight(): array
    {
        return [
            '/Height.*?:\s*([0-9]+)\s*cm\s*&\s*weight:\s*([0-9]+)\s*kg/i',
            '/Height:\s*([0-9]+)\s*cm.*?Weight:\s*([0-9]+)\s*kg/i',
            '/H:\s*([0-9]+)\s*cm.*?W:\s*([0-9]+)\s*kg/i',
        ];
    }

    public static function nationality(): array
    {
        return [
            '/Nationality:\s*([^\n]+?)\s*Address:/i',
            '/Nationality\s*:\s*([^\n]+?)\s*(?:Address|Home)/i',
            '/Country:\s*([^\n]+?)\s*Address:/i',
        ];
    }

    public static function address(): array
    {
        return [
            '/Address:\s*([^\n]+?)\s*Name of port/i',
            '/Address\s*:\s*([^\n]+?)\s*(?:Port|Repatriation)/i',
            '/Home Address:\s*([^\n]+?)\s*(?:Port|$)/i',
        ];
    }

    public static function repatriation(): array
    {
        return [
            '/Name of port \/ airport to be repatriated to:\s*([^\n]+?)\s*Contact number/i',
            '/Repatriation Port:\s*([^\n]+?)\s*Contact/i',
            '/Port\/Airport:\s*([^\n]+?)\s*Contact/i',
        ];
    }

    public static function contact(): array
    {
        return [
            '/Contact number in home country:\s*([^\n]+?)\s*Religion:/i',
            '/Contact Number:\s*([^\n]+?)\s*Religion:/i',
            '/Phone:\s*([^\n]+?)\s*Religion:/i',
        ];
    }

    public static function religion(): array
    {
        return [
            '/Religion:\s*([^\n]+?)\s*Education/i',
            '/Religion\s*:\s*([^\n]+?)\s*(?:Education|School)/i',
        ];
    }

    public static function education(): array
    {
        return [
            '/Education level:\s*([^\n]+?)\s*Number of siblings:/i',
            '/Education:\s*([^\n]+?)\s*(?:Siblings|Number)/i',
            '/School Level:\s*([^\n]+?)\s*Siblings/i',
        ];
    }

    public static function siblings(): array
    {
        return [
            '/Number of siblings:\s*([0-9]+)/i',
            '/Siblings:\s*([0-9]+)/i',
        ];
    }

    public static function maritalStatus(): array
    {
        return [
            '/Marital status:\s*([^\n]+?)\s*Number of children:/i',
            '/Marital Status\s*:\s*([^\n]+?)\s*(?:Children|Kids)/i',
            '/Status:\s*([^\n]+?)\s*Children/i',
        ];
    }

    public static function childrenCount(): array
    {
        return [
            '/Number of children:\s*([0-9]+)/i',
            '/Children:\s*([0-9]+)/i',
            '/Kids:\s*([0-9]+)/i',
        ];
    }

    public static function childrenAges(): array
    {
        return [
            '/Age\(s\) of children.*?:\s*(.*?)\s*Photo Profile/i',
            '/Children Ages?:\s*(.*?)\s*(?:Photo|Picture)/i',
            '/Kids Ages?:\s*(.*?)\s*(?:Photo|$)/i',
        ];
    }
}
