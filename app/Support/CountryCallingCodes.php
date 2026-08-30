<?php

namespace App\Support;

final class CountryCallingCodes
{
    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_values(array_unique(array_column(self::options(), 'code')));
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['code' => '+1', 'label' => 'US/CA +1'],
            ['code' => '+7', 'label' => 'RU/KZ +7'],
            ['code' => '+20', 'label' => 'EG +20'],
            ['code' => '+27', 'label' => 'ZA +27'],
            ['code' => '+31', 'label' => 'NL +31'],
            ['code' => '+32', 'label' => 'BE +32'],
            ['code' => '+33', 'label' => 'FR +33'],
            ['code' => '+34', 'label' => 'ES +34'],
            ['code' => '+39', 'label' => 'IT +39'],
            ['code' => '+41', 'label' => 'CH +41'],
            ['code' => '+43', 'label' => 'AT +43'],
            ['code' => '+44', 'label' => 'UK +44'],
            ['code' => '+45', 'label' => 'DK +45'],
            ['code' => '+46', 'label' => 'SE +46'],
            ['code' => '+47', 'label' => 'NO +47'],
            ['code' => '+48', 'label' => 'PL +48'],
            ['code' => '+49', 'label' => 'DE +49'],
            ['code' => '+51', 'label' => 'PE +51'],
            ['code' => '+52', 'label' => 'MX +52'],
            ['code' => '+54', 'label' => 'AR +54'],
            ['code' => '+55', 'label' => 'BR +55'],
            ['code' => '+56', 'label' => 'CL +56'],
            ['code' => '+57', 'label' => 'CO +57'],
            ['code' => '+60', 'label' => 'MY +60'],
            ['code' => '+61', 'label' => 'AU +61'],
            ['code' => '+62', 'label' => 'ID +62'],
            ['code' => '+63', 'label' => 'PH +63'],
            ['code' => '+64', 'label' => 'NZ +64'],
            ['code' => '+65', 'label' => 'SG +65'],
            ['code' => '+66', 'label' => 'TH +66'],
            ['code' => '+81', 'label' => 'JP +81'],
            ['code' => '+82', 'label' => 'KR +82'],
            ['code' => '+84', 'label' => 'VN +84'],
            ['code' => '+86', 'label' => 'CN +86'],
            ['code' => '+90', 'label' => 'TR +90'],
            ['code' => '+91', 'label' => 'IN +91'],
            ['code' => '+92', 'label' => 'PK +92'],
            ['code' => '+94', 'label' => 'LK +94'],
            ['code' => '+234', 'label' => 'NG +234'],
            ['code' => '+254', 'label' => 'KE +254'],
            ['code' => '+351', 'label' => 'PT +351'],
            ['code' => '+353', 'label' => 'IE +353'],
            ['code' => '+358', 'label' => 'FI +358'],
            ['code' => '+852', 'label' => 'HK +852'],
            ['code' => '+880', 'label' => 'BD +880'],
            ['code' => '+962', 'label' => 'JO +962'],
            ['code' => '+965', 'label' => 'KW +965'],
            ['code' => '+966', 'label' => 'SA +966'],
            ['code' => '+968', 'label' => 'OM +968'],
            ['code' => '+971', 'label' => 'AE +971'],
            ['code' => '+972', 'label' => 'IL +972'],
            ['code' => '+973', 'label' => 'BH +973'],
            ['code' => '+974', 'label' => 'QA +974'],
        ];
    }
}
