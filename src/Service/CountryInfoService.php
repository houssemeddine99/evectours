<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Built-in country facts for the destinations the agency serves.
 * Self-contained (no external API). Flag images via flagcdn.com;
 * flag emoji computed from the ISO code.
 */
class CountryInfoService
{
    /** @var array<string, array{code:string,name:string,capital:string,language:string,currency:string,timezone:string}> */
    private const COUNTRY_DATA = [
        'saudi arabia'         => ['code' => 'sa', 'name' => 'Saudi Arabia',         'capital' => 'Riyadh',           'language' => 'Arabic',           'currency' => 'Saudi Riyal (SAR)',      'timezone' => 'UTC+03:00'],
        'tunisia'              => ['code' => 'tn', 'name' => 'Tunisia',              'capital' => 'Tunis',            'language' => 'Arabic',           'currency' => 'Tunisian Dinar (TND)',   'timezone' => 'UTC+01:00'],
        'united arab emirates' => ['code' => 'ae', 'name' => 'United Arab Emirates', 'capital' => 'Abu Dhabi',        'language' => 'Arabic',           'currency' => 'UAE Dirham (AED)',       'timezone' => 'UTC+04:00'],
        'qatar'                => ['code' => 'qa', 'name' => 'Qatar',                'capital' => 'Doha',             'language' => 'Arabic',           'currency' => 'Qatari Riyal (QAR)',     'timezone' => 'UTC+03:00'],
        'jordan'               => ['code' => 'jo', 'name' => 'Jordan',               'capital' => 'Amman',            'language' => 'Arabic',           'currency' => 'Jordanian Dinar (JOD)',  'timezone' => 'UTC+03:00'],
        'egypt'                => ['code' => 'eg', 'name' => 'Egypt',                'capital' => 'Cairo',            'language' => 'Arabic',           'currency' => 'Egyptian Pound (EGP)',   'timezone' => 'UTC+02:00'],
        'morocco'              => ['code' => 'ma', 'name' => 'Morocco',              'capital' => 'Rabat',            'language' => 'Arabic',           'currency' => 'Moroccan Dirham (MAD)',  'timezone' => 'UTC+01:00'],
        'turkey'               => ['code' => 'tr', 'name' => 'Türkiye',              'capital' => 'Ankara',           'language' => 'Turkish',          'currency' => 'Turkish Lira (₺)',       'timezone' => 'UTC+03:00'],
        'france'               => ['code' => 'fr', 'name' => 'France',               'capital' => 'Paris',            'language' => 'French',           'currency' => 'Euro (€)',               'timezone' => 'UTC+01:00'],
        'italy'                => ['code' => 'it', 'name' => 'Italy',                'capital' => 'Rome',             'language' => 'Italian',          'currency' => 'Euro (€)',               'timezone' => 'UTC+01:00'],
        'spain'                => ['code' => 'es', 'name' => 'Spain',                'capital' => 'Madrid',           'language' => 'Spanish',          'currency' => 'Euro (€)',               'timezone' => 'UTC+01:00'],
        'germany'              => ['code' => 'de', 'name' => 'Germany',              'capital' => 'Berlin',           'language' => 'German',           'currency' => 'Euro (€)',               'timezone' => 'UTC+01:00'],
        'netherlands'          => ['code' => 'nl', 'name' => 'Netherlands',          'capital' => 'Amsterdam',        'language' => 'Dutch',            'currency' => 'Euro (€)',               'timezone' => 'UTC+01:00'],
        'austria'              => ['code' => 'at', 'name' => 'Austria',              'capital' => 'Vienna',           'language' => 'German',           'currency' => 'Euro (€)',               'timezone' => 'UTC+01:00'],
        'portugal'             => ['code' => 'pt', 'name' => 'Portugal',             'capital' => 'Lisbon',           'language' => 'Portuguese',       'currency' => 'Euro (€)',               'timezone' => 'UTC+00:00'],
        'greece'               => ['code' => 'gr', 'name' => 'Greece',               'capital' => 'Athens',           'language' => 'Greek',            'currency' => 'Euro (€)',               'timezone' => 'UTC+02:00'],
        'malta'                => ['code' => 'mt', 'name' => 'Malta',                'capital' => 'Valletta',         'language' => 'Maltese',          'currency' => 'Euro (€)',               'timezone' => 'UTC+01:00'],
        'czech republic'       => ['code' => 'cz', 'name' => 'Czech Republic',       'capital' => 'Prague',           'language' => 'Czech',            'currency' => 'Czech Koruna (Kč)',      'timezone' => 'UTC+01:00'],
        'united kingdom'       => ['code' => 'gb', 'name' => 'United Kingdom',        'capital' => 'London',           'language' => 'English',          'currency' => 'Pound Sterling (£)',     'timezone' => 'UTC+00:00'],
        'united states'        => ['code' => 'us', 'name' => 'United States',         'capital' => 'Washington, D.C.', 'language' => 'English',          'currency' => 'US Dollar ($)',          'timezone' => 'UTC−05:00'],
        'canada'               => ['code' => 'ca', 'name' => 'Canada',               'capital' => 'Ottawa',           'language' => 'English / French',  'currency' => 'Canadian Dollar (C$)',   'timezone' => 'UTC−05:00'],
        'japan'                => ['code' => 'jp', 'name' => 'Japan',                'capital' => 'Tokyo',            'language' => 'Japanese',         'currency' => 'Japanese Yen (¥)',       'timezone' => 'UTC+09:00'],
        'australia'            => ['code' => 'au', 'name' => 'Australia',            'capital' => 'Canberra',         'language' => 'English',          'currency' => 'Australian Dollar (A$)', 'timezone' => 'UTC+10:00'],
    ];

    /** @var array<string, string> common aliases → canonical key */
    private const COUNTRY_ALIASES = [
        'ksa' => 'saudi arabia', 'uae' => 'united arab emirates', 'emirates' => 'united arab emirates',
        'uk' => 'united kingdom', 'england' => 'united kingdom', 'britain' => 'united kingdom', 'great britain' => 'united kingdom',
        'usa' => 'united states', 'us' => 'united states', 'america' => 'united states',
        'türkiye' => 'turkey', 'turkiye' => 'turkey', 'czechia' => 'czech republic', 'holland' => 'netherlands',
    ];

    /**
     * Resolve a destination string (e.g. "Mecca & Medina, Saudi Arabia") to country facts.
     * @return array{name:string,flag_svg:string,flag_emoji:string,capital:string,language:string,currency:string,timezone:string}|null
     */
    public function forDestination(string $destination): ?array
    {
        $parts = array_map('trim', explode(',', $destination));
        $country = mb_strtolower(trim((string) end($parts)));
        if ($country === '') {
            return null;
        }

        $key = self::COUNTRY_ALIASES[$country] ?? $country;
        $info = self::COUNTRY_DATA[$key] ?? null;

        if ($info === null) {
            $haystack = mb_strtolower($destination);
            foreach (self::COUNTRY_DATA as $name => $row) {
                if (str_contains($haystack, $name)) {
                    $info = $row;
                    break;
                }
            }
            if ($info === null) {
                foreach (self::COUNTRY_ALIASES as $alias => $canonical) {
                    if (str_contains($haystack, $alias)) {
                        $info = self::COUNTRY_DATA[$canonical] ?? null;
                        break;
                    }
                }
            }
        }

        if ($info === null) {
            return null;
        }

        return [
            'name'       => $info['name'],
            'flag_svg'   => 'https://flagcdn.com/' . $info['code'] . '.svg',
            'flag_emoji' => $this->codeToFlagEmoji($info['code']),
            'capital'    => $info['capital'],
            'language'   => $info['language'],
            'currency'   => $info['currency'],
            'timezone'   => $info['timezone'],
        ];
    }

    private function codeToFlagEmoji(string $code): string
    {
        $emoji = '';
        foreach (str_split(strtoupper($code)) as $ch) {
            $emoji .= mb_chr(0x1F1E6 + (ord($ch) - ord('A')), 'UTF-8');
        }
        return $emoji;
    }
}
