<?php

namespace App\Services\MaidManagement\DataExtractor\Patterns;

class PersonalInformationPatterns
{
    public static function name(): array
    {
        return [
            "/^Name\s*\n([A-Z][A-Z\s',\.-]+)$/m",
            "/Name\s*:\s*([A-Z][A-Z\s',\.-]+?)(?=\s*(?:Date|DOB))/i",
            "/Name\s*of\s*FDW\s*:\s*([A-Z][A-Z\s',\.-]+)/i",
            "/FDW\s*Name\s*:\s*([A-Z][A-Z\s',\.-]+)/i",
            "/Full\s*Name\s*:\s*([A-Z][A-Z\s',\.-]+?)(?=\s*(?:Date|DOB))/i",
            "/Worker\s*Name\s*:\s*([A-Z][A-Z\s',\.-]+?)(?=\s*(?:Date|DOB))/i",
            "/\\d+\.\s*Name\s*:\s*([A-Z][A-Z\s',\.-]+?)(?=\s*\\d+\.)/i",
        ];
    }

    public static function dob(): array
    {
        return [
            "/Date of birth\s*:\s*([0-9]{1,2}\s+[A-Za-z]{3,9}\s+[0-9]{2,4})(?:\s*Age|\s*$)/iu",
            "/Date of birth\s*:\s*([0-9]{1,2}\s*[\/\-]\s*[0-9]{1,2}\s*[\/\-]\s*[0-9]{2,4})(?:\s*Age|\s*$)/iu",
            "/^Date of Birth\s*\\n([0-9]{1,2}\s+[A-Za-z]{3,9}\s+[0-9]{2,4})$/mi",
            "/^Date of Birth\s*\\n([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})$/mi",
            "/Dateofbirth:([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})\\s*Age/i",
            "/DOB\s*[:\-]?\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})(?:\s*Age|\s*$)/i",
            "/Birth\s*Date\s*[:\-]?\s*([0-9]{1,2}\s+[A-Za-z]{3,9}\s+[0-9]{2,4})/i",
            "/Birth\s*Date\s*[:\-]?\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/i",
        ];
    }

    public static function age(): array
    {
        return [
            '/^Age\s*\n([0-9]{2})$/m',
            '/Age:([0-9]+)YO/i',
            '/Age\s*:\s*([0-9]+)\s*YO/i',
            '/Date of Birth\s*:\s*Age\s*:\s*([0-9]+)/i',
            '/Age\s*:\s*\n?\s*([0-9]+)/i',
        ];
    }

    public static function birthPlace(): array
    {
        return [
            '/^Place of Birth\s*\n([A-Z]+(?:\s+[A-Z]+)*)$/m',
            '/Placeofbirth:([A-Z]+)(?=\s*\n|Height)/i',
            '/Place of birth\s*:\s*([A-Z][A-Z\s]+?)(?=\s*\n|\s*Height)/i',
            '/\\d+\.\s*Place of birth\s*:\s*([A-Z][A-Z\s]+?)(?=\s*\\d+\.)/i',
            '/[0-9A]+Place of birth\s*:\s*([A-Z][A-Z\s]+?)(?=\s*\\d|\s*Height|\n)/i',
        ];
    }

    public static function heightWeight(): array
    {
        return [
            '/Height&weight:([0-9]+)cm([0-9,]+)KG/i',
            '/Height\s*&\s*weight\s*:\s*([0-9]+)\s*cm\s*([0-9,]+)\s*Age/is',
            '/Height\s*&(?:amp;)?\s*weight\s*:\s*([0-9]+)\s*cm\s*([0-9,]+)\s*(?:Age|KG)/is',
            '/Height\s*:\s*([0-9]+)\s*CM\s*Weight\s*:\s*([0-9]+)\s*KG/i',
            '/\\d+\.\s*Height\s*&(?:amp;)?\s*weight\s*:\s*([0-9]+)?\s*cm\s*([0-9,]+)?\s*kg/i',
            '/Height\s*&(?:amp;)?\s*weight\s*:\s*([0-9]+|b)\s*cm\s*([0-9,]+)?\s*kg/i',
        ];
    }

    public static function nationality(): array
    {
        return [
            '/^Nationality\s*\n([A-Z]+)$/m',
            '/Nationality:([A-Z]+)(?=\s*\n|Address)/i',
            '/\\d+\.\s*Nationality\s*:\s*([A-Z]+)(?=\s*\\d+\.)/i',
            '/Nationality\s*:\s*([A-Z]+)(?:\s*\n|\s*Residential|\s*Address)/i',
            '/Country\s*[:\-]?\s*([A-Z]+(?:IAN)?)(?:\s*\n|\s*Address)/i',
        ];
    }

    public static function address(): array
    {
        return [
            '/^Address\s*\n([^\n]+(?:,\s*[^\n]+)*)$/m',
            '/\\d+\.\s*Address\s*:\s*([A-Z][^\n]+?)(?=\s*:?[0-9]+\.)/i',
            '/Address\s*:\s*([A-Z][^\n]+(?:\n[A-Z][^\n]+)?)(?=\s*Name of port)/is',
            '/Address:([A-Z][^\n]+?)(?=\s*\n|:7\.)/i',
            '/Residential address in home country\s*:\s*([A-Z][^\n]+(?:\n[A-Z][^\n]+){0,3})(?=\s*Name of port)/is',
            '/\\d+\.\s*Residential address in home country\s*:\s*([A-Z][^\d]+?)(?=\\d+\.)/is',
        ];
    }

    public static function repatriation(): array
    {
        return [
            '/^Name of Port\s*\n([A-Z]+)$/m',
            '/Name of port\s*\/\s*airport to be repatriated to\s*:\s*([A-Za-z\s]+?)(?=\s*Contact|\n)/i',
            '/:?\\d+\.\s*Name of port\s*\/\s*airport to be repatriated to\s*:\s*([A-Za-z\s]+?)(?=\s*Contact|\n)/i',
            '/Nameofport\/airporttoberepatriatedto:([A-Za-z\s]+?)(?=Contact|\n)/i',
            '/\\d+\.\s*Name of port\/airport to be repatriated to\s*:\s*([A-Za-z\s]+?)(?=Contact|\n)/i',
            '/Repatriation Port\s*:\s*([A-Za-z\s]+?)(?=Contact|\n)/i',
        ];
    }

    public static function contact(): array
    {
        return [
            '/^Tel No\s*\n([A-Z0-9]+)$/m',
            '/Contactnumberinhomecountry:([0-9]+)/i',
            '/Contract number in home country\s*:\s*([0-9\-+]+|--?)/i',
            '/\\d+\.\s*Contact number in home country\s*:\s*([0-9\-+]+|--)/i',
            '/Contact number in home country\s*:\s*([0-9\-+]+)(?=\s*Religion|\n)/i',
        ];
    }

    public static function religion(): array
    {
        return [
            '/^Religion\s*\n([A-Z]+)$/m',
            '/Religion:([A-Z]+)(?=\s*\n|Education)/i',
            '/\\d+\.\s*Religion\s*:\s*([A-Z]+)/i',
            '/Religion\s*:\s*([A-Z]+)(?:\s*\n|\s*Education)/i',
            '/Faith\s*[:\-]?\s*([A-Z]+)/i',
            '/Contact number in home country\s*:\s*[0-9\-+]+\s*Religion\s*:\s*([A-Z]+)/i',
            '/Religion\s*:\s*\n?\s*([A-Z][A-Za-z]+)/i',
        ];
    }

    public static function education(): array
    {
        return [
            '/^Education level:\s*\n([A-Z\s]+)$/m',
            '/Educationlevel:([A-Z]+(?:[A-Z\s]+)?)(?=\s*\n|Number)/i',
            '/Education level\s*:\s*:\s*([A-Z][A-Z\s]+?)(?=\s*\n|\s*Number)/i',
            '/\\d+\.\s*Education level\s*:\s*([A-Z][A-Z\s]+?)(?=\s*\\d+\.)/i',
            '/Education level\s*:\s*([A-Z][A-Z\s]+?)(?=\s*\n|\s*Number)/i',
            '/Religion\s*:\s*[A-Z]+\s*Education level\s*:\s*([A-Z][A-Z\s]+?)(?=\s*\n|\s*Number)/i',
            '/Education\s*[Ll]evel\s*:\s*\n?\s*([A-Z][A-Za-z\s]+?)(?=\s*\n|\s*Number|\s*\\d+\.)/i',
        ];
    }

    public static function siblings(): array
    {
        return [
            '/^No of Siblings\s*\n([0-9]+)$/m',
            '/Numberofsiblings:([0-9]+)/i',
            '/\\d+\.\s*Number of siblings\s*:\s*([0-9]+)(?=\s*Marital|\n)/i',
            '/Number of siblings\s*:\s*([0-9]+)(?=\s*Marital|\n)/i',
        ];
    }

    public static function maritalStatus(): array
    {
        return [
            '/^Maritial Status\s*\n([A-Z]+)$/m',
            '/Maritalstatus:([A-Z]+)/i',
            '/Marital status\s*:\s*:\s*([A-Z]+)/i',
            '/\\d+\.\s*Marital status\s*:\s*([A-Z]+)/i',
            '/Marital status\s*:\s*([A-Z]+)(?=\s*\n|\s*Number)/i',
        ];
    }

    public static function childrenCount(): array
    {
        return [
            '/^No of Children\s*\n([0-9]+)$/m',
            '/Numberofchildren:([0-9]+)/i',
            '/Number of children\s*:\s*:\s*([0-9]+)\s*CHILDREN?/i',
            '/Number of children\s*:\s*:\s*([0-9]+)(?:\s*CHILDREN?)?/i',
            '/\\d+\.\s*Number of children\s*:\s*([0-9]+)/i',
            '/Number of children\s*:\s*([0-9]+|--)/i',
        ];
    }

    public static function childrenAges(): array
    {
        return [
            '/Age\(s\)\s*of\s*children\s*\(if\s*any\)\s*:\s*([^\n]+?)(?=\n|$)/i',
            '/Age\(s\)\s*of\s*children\s*:\s*([^\n]+?)(?=\n|$)/i',
            '/Age\(s\)ofchildren\(ifany\):([0-9YOAND]+)/i',
            '/[-\s]*Age\(s\)\s*of children\s*(?:\(if any\))?\s*:\s*:\s*([0-9,\s]+(?:YO|yo|AND|and|\s)+[0-9,\sYOANDand]+)/i',
            '/Age\(s\)\s*of children\s*(?:\(if any\))?\s*:\s*([0-9,\s]+)\s*YEARS OLD/i',
            '/Children(?:\'s)?\s*Ages?\s*:\s*([0-9,\s]+(?:YO|years)?)/i',
            '/Number\s*of\s*children\s*:\s*[0-9]+\s*\(([^\)]*)\)/i',
            '/No\.\s*of\s*children\s*:\s*[0-9]+\s*\(([^\)]*)\)/i',
            '/Children\s*:?[0-9]+\s*\(([^\)]*)\)/i',
        ];
    }
}
