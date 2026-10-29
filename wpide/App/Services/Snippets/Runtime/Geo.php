<?php

namespace WPIDE\App\Services\Snippets\Runtime;

/**
 * Visitor location for country/continent conditions.
 *
 * No external service is called. The country comes from, in order: the Cloudflare CF-IPCountry header,
 * server GeoIP variables (mod_geoip, nginx geoip, many hosts), WooCommerce geolocation when WooCommerce is
 * active, then the 'wpide_snippets_visitor_country' filter. Results vary per visitor, so pages using these
 * conditions must not be served from a shared full-page cache.
 */
class Geo
{
    /** @var string|null */
    protected static $country;

    public static function country(): string
    {
        if (self::$country !== null) {
            return self::$country;
        }

        $country = '';
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput
        foreach (['HTTP_CF_IPCOUNTRY', 'GEOIP_COUNTRY_CODE', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_GEO_COUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY'] as $key) {
            if (!empty($_SERVER[$key])) {
                $country = strtoupper(substr(sanitize_text_field(wp_unslash($_SERVER[$key])), 0, 2));
                break;
            }
        }
        // phpcs:enable

        if ($country === '' && class_exists('WC_Geolocation')) {
            $location = \WC_Geolocation::geolocate_ip('', true, false);
            $country = isset($location['country']) ? strtoupper((string) $location['country']) : '';
        }

        $country = (string) apply_filters('wpide_snippets_visitor_country', $country);

        return self::$country = isset(self::countries()[$country]) ? $country : '';
    }

    public static function continent(): string
    {
        $countries = self::countries();
        $country = self::country();

        return $country !== '' ? $countries[$country][1] : '';
    }

    public static function continents(): array
    {
        return [
            'AF' => __('Africa', 'wpide'),
            'AN' => __('Antarctica', 'wpide'),
            'AS' => __('Asia', 'wpide'),
            'EU' => __('Europe', 'wpide'),
            'NA' => __('North America', 'wpide'),
            'OC' => __('Oceania', 'wpide'),
            'SA' => __('South America', 'wpide'),
        ];
    }

    public static function countryChoices(): array
    {
        $choices = [];

        foreach (self::countries() as $code => $country) {
            $choices[] = ['value' => $code, 'label' => $country[0]];
        }

        return $choices;
    }

    public static function continentChoices(): array
    {
        $choices = [];

        foreach (self::continents() as $code => $label) {
            $choices[] = ['value' => $code, 'label' => $label];
        }

        return $choices;
    }

    /**
     * ISO 3166-1 alpha-2 code => [name, continent code].
     */
    public static function countries(): array
    {
        return [
            'AF' => ['Afghanistan', 'AS'],
            'AX' => ['Åland Islands', 'EU'],
            'AL' => ['Albania', 'EU'],
            'DZ' => ['Algeria', 'AF'],
            'AS' => ['American Samoa', 'OC'],
            'AD' => ['Andorra', 'EU'],
            'AO' => ['Angola', 'AF'],
            'AI' => ['Anguilla', 'NA'],
            'AQ' => ['Antarctica', 'AN'],
            'AG' => ['Antigua and Barbuda', 'NA'],
            'AR' => ['Argentina', 'SA'],
            'AM' => ['Armenia', 'AS'],
            'AW' => ['Aruba', 'NA'],
            'AU' => ['Australia', 'OC'],
            'AT' => ['Austria', 'EU'],
            'AZ' => ['Azerbaijan', 'AS'],
            'BS' => ['Bahamas', 'NA'],
            'BH' => ['Bahrain', 'AS'],
            'BD' => ['Bangladesh', 'AS'],
            'BB' => ['Barbados', 'NA'],
            'BY' => ['Belarus', 'EU'],
            'BE' => ['Belgium', 'EU'],
            'BZ' => ['Belize', 'NA'],
            'BJ' => ['Benin', 'AF'],
            'BM' => ['Bermuda', 'NA'],
            'BT' => ['Bhutan', 'AS'],
            'BO' => ['Bolivia', 'SA'],
            'BQ' => ['Bonaire, Sint Eustatius and Saba', 'NA'],
            'BA' => ['Bosnia and Herzegovina', 'EU'],
            'BW' => ['Botswana', 'AF'],
            'BV' => ['Bouvet Island', 'AN'],
            'BR' => ['Brazil', 'SA'],
            'IO' => ['British Indian Ocean Territory', 'AS'],
            'BN' => ['Brunei', 'AS'],
            'BG' => ['Bulgaria', 'EU'],
            'BF' => ['Burkina Faso', 'AF'],
            'BI' => ['Burundi', 'AF'],
            'CV' => ['Cabo Verde', 'AF'],
            'KH' => ['Cambodia', 'AS'],
            'CM' => ['Cameroon', 'AF'],
            'CA' => ['Canada', 'NA'],
            'KY' => ['Cayman Islands', 'NA'],
            'CF' => ['Central African Republic', 'AF'],
            'TD' => ['Chad', 'AF'],
            'CL' => ['Chile', 'SA'],
            'CN' => ['China', 'AS'],
            'CX' => ['Christmas Island', 'AS'],
            'CC' => ['Cocos (Keeling) Islands', 'AS'],
            'CO' => ['Colombia', 'SA'],
            'KM' => ['Comoros', 'AF'],
            'CG' => ['Congo', 'AF'],
            'CD' => ['Congo (Democratic Republic)', 'AF'],
            'CK' => ['Cook Islands', 'OC'],
            'CR' => ['Costa Rica', 'NA'],
            'CI' => ['Côte d\'Ivoire', 'AF'],
            'HR' => ['Croatia', 'EU'],
            'CU' => ['Cuba', 'NA'],
            'CW' => ['Curaçao', 'NA'],
            'CY' => ['Cyprus', 'EU'],
            'CZ' => ['Czechia', 'EU'],
            'DK' => ['Denmark', 'EU'],
            'DJ' => ['Djibouti', 'AF'],
            'DM' => ['Dominica', 'NA'],
            'DO' => ['Dominican Republic', 'NA'],
            'EC' => ['Ecuador', 'SA'],
            'EG' => ['Egypt', 'AF'],
            'SV' => ['El Salvador', 'NA'],
            'GQ' => ['Equatorial Guinea', 'AF'],
            'ER' => ['Eritrea', 'AF'],
            'EE' => ['Estonia', 'EU'],
            'SZ' => ['Eswatini', 'AF'],
            'ET' => ['Ethiopia', 'AF'],
            'FK' => ['Falkland Islands', 'SA'],
            'FO' => ['Faroe Islands', 'EU'],
            'FJ' => ['Fiji', 'OC'],
            'FI' => ['Finland', 'EU'],
            'FR' => ['France', 'EU'],
            'GF' => ['French Guiana', 'SA'],
            'PF' => ['French Polynesia', 'OC'],
            'TF' => ['French Southern Territories', 'AN'],
            'GA' => ['Gabon', 'AF'],
            'GM' => ['Gambia', 'AF'],
            'GE' => ['Georgia', 'AS'],
            'DE' => ['Germany', 'EU'],
            'GH' => ['Ghana', 'AF'],
            'GI' => ['Gibraltar', 'EU'],
            'GR' => ['Greece', 'EU'],
            'GL' => ['Greenland', 'NA'],
            'GD' => ['Grenada', 'NA'],
            'GP' => ['Guadeloupe', 'NA'],
            'GU' => ['Guam', 'OC'],
            'GT' => ['Guatemala', 'NA'],
            'GG' => ['Guernsey', 'EU'],
            'GN' => ['Guinea', 'AF'],
            'GW' => ['Guinea-Bissau', 'AF'],
            'GY' => ['Guyana', 'SA'],
            'HT' => ['Haiti', 'NA'],
            'HM' => ['Heard Island and McDonald Islands', 'AN'],
            'VA' => ['Holy See', 'EU'],
            'HN' => ['Honduras', 'NA'],
            'HK' => ['Hong Kong', 'AS'],
            'HU' => ['Hungary', 'EU'],
            'IS' => ['Iceland', 'EU'],
            'IN' => ['India', 'AS'],
            'ID' => ['Indonesia', 'AS'],
            'IR' => ['Iran', 'AS'],
            'IQ' => ['Iraq', 'AS'],
            'IE' => ['Ireland', 'EU'],
            'IM' => ['Isle of Man', 'EU'],
            'IL' => ['Israel', 'AS'],
            'IT' => ['Italy', 'EU'],
            'JM' => ['Jamaica', 'NA'],
            'JP' => ['Japan', 'AS'],
            'JE' => ['Jersey', 'EU'],
            'JO' => ['Jordan', 'AS'],
            'KZ' => ['Kazakhstan', 'AS'],
            'KE' => ['Kenya', 'AF'],
            'KI' => ['Kiribati', 'OC'],
            'KP' => ['North Korea', 'AS'],
            'KR' => ['South Korea', 'AS'],
            'XK' => ['Kosovo', 'EU'],
            'KW' => ['Kuwait', 'AS'],
            'KG' => ['Kyrgyzstan', 'AS'],
            'LA' => ['Laos', 'AS'],
            'LV' => ['Latvia', 'EU'],
            'LB' => ['Lebanon', 'AS'],
            'LS' => ['Lesotho', 'AF'],
            'LR' => ['Liberia', 'AF'],
            'LY' => ['Libya', 'AF'],
            'LI' => ['Liechtenstein', 'EU'],
            'LT' => ['Lithuania', 'EU'],
            'LU' => ['Luxembourg', 'EU'],
            'MO' => ['Macao', 'AS'],
            'MG' => ['Madagascar', 'AF'],
            'MW' => ['Malawi', 'AF'],
            'MY' => ['Malaysia', 'AS'],
            'MV' => ['Maldives', 'AS'],
            'ML' => ['Mali', 'AF'],
            'MT' => ['Malta', 'EU'],
            'MH' => ['Marshall Islands', 'OC'],
            'MQ' => ['Martinique', 'NA'],
            'MR' => ['Mauritania', 'AF'],
            'MU' => ['Mauritius', 'AF'],
            'YT' => ['Mayotte', 'AF'],
            'MX' => ['Mexico', 'NA'],
            'FM' => ['Micronesia', 'OC'],
            'MD' => ['Moldova', 'EU'],
            'MC' => ['Monaco', 'EU'],
            'MN' => ['Mongolia', 'AS'],
            'ME' => ['Montenegro', 'EU'],
            'MS' => ['Montserrat', 'NA'],
            'MA' => ['Morocco', 'AF'],
            'MZ' => ['Mozambique', 'AF'],
            'MM' => ['Myanmar', 'AS'],
            'NA' => ['Namibia', 'AF'],
            'NR' => ['Nauru', 'OC'],
            'NP' => ['Nepal', 'AS'],
            'NL' => ['Netherlands', 'EU'],
            'NC' => ['New Caledonia', 'OC'],
            'NZ' => ['New Zealand', 'OC'],
            'NI' => ['Nicaragua', 'NA'],
            'NE' => ['Niger', 'AF'],
            'NG' => ['Nigeria', 'AF'],
            'NU' => ['Niue', 'OC'],
            'NF' => ['Norfolk Island', 'OC'],
            'MK' => ['North Macedonia', 'EU'],
            'MP' => ['Northern Mariana Islands', 'OC'],
            'NO' => ['Norway', 'EU'],
            'OM' => ['Oman', 'AS'],
            'PK' => ['Pakistan', 'AS'],
            'PW' => ['Palau', 'OC'],
            'PS' => ['Palestine', 'AS'],
            'PA' => ['Panama', 'NA'],
            'PG' => ['Papua New Guinea', 'OC'],
            'PY' => ['Paraguay', 'SA'],
            'PE' => ['Peru', 'SA'],
            'PH' => ['Philippines', 'AS'],
            'PN' => ['Pitcairn', 'OC'],
            'PL' => ['Poland', 'EU'],
            'PT' => ['Portugal', 'EU'],
            'PR' => ['Puerto Rico', 'NA'],
            'QA' => ['Qatar', 'AS'],
            'RE' => ['Réunion', 'AF'],
            'RO' => ['Romania', 'EU'],
            'RU' => ['Russia', 'EU'],
            'RW' => ['Rwanda', 'AF'],
            'BL' => ['Saint Barthélemy', 'NA'],
            'SH' => ['Saint Helena', 'AF'],
            'KN' => ['Saint Kitts and Nevis', 'NA'],
            'LC' => ['Saint Lucia', 'NA'],
            'MF' => ['Saint Martin', 'NA'],
            'PM' => ['Saint Pierre and Miquelon', 'NA'],
            'VC' => ['Saint Vincent and the Grenadines', 'NA'],
            'WS' => ['Samoa', 'OC'],
            'SM' => ['San Marino', 'EU'],
            'ST' => ['Sao Tome and Principe', 'AF'],
            'SA' => ['Saudi Arabia', 'AS'],
            'SN' => ['Senegal', 'AF'],
            'RS' => ['Serbia', 'EU'],
            'SC' => ['Seychelles', 'AF'],
            'SL' => ['Sierra Leone', 'AF'],
            'SG' => ['Singapore', 'AS'],
            'SX' => ['Sint Maarten', 'NA'],
            'SK' => ['Slovakia', 'EU'],
            'SI' => ['Slovenia', 'EU'],
            'SB' => ['Solomon Islands', 'OC'],
            'SO' => ['Somalia', 'AF'],
            'ZA' => ['South Africa', 'AF'],
            'GS' => ['South Georgia and the South Sandwich Islands', 'AN'],
            'SS' => ['South Sudan', 'AF'],
            'ES' => ['Spain', 'EU'],
            'LK' => ['Sri Lanka', 'AS'],
            'SD' => ['Sudan', 'AF'],
            'SR' => ['Suriname', 'SA'],
            'SJ' => ['Svalbard and Jan Mayen', 'EU'],
            'SE' => ['Sweden', 'EU'],
            'CH' => ['Switzerland', 'EU'],
            'SY' => ['Syria', 'AS'],
            'TW' => ['Taiwan', 'AS'],
            'TJ' => ['Tajikistan', 'AS'],
            'TZ' => ['Tanzania', 'AF'],
            'TH' => ['Thailand', 'AS'],
            'TL' => ['Timor-Leste', 'AS'],
            'TG' => ['Togo', 'AF'],
            'TK' => ['Tokelau', 'OC'],
            'TO' => ['Tonga', 'OC'],
            'TT' => ['Trinidad and Tobago', 'NA'],
            'TN' => ['Tunisia', 'AF'],
            'TR' => ['Türkiye', 'AS'],
            'TM' => ['Turkmenistan', 'AS'],
            'TC' => ['Turks and Caicos Islands', 'NA'],
            'TV' => ['Tuvalu', 'OC'],
            'UG' => ['Uganda', 'AF'],
            'UA' => ['Ukraine', 'EU'],
            'AE' => ['United Arab Emirates', 'AS'],
            'GB' => ['United Kingdom', 'EU'],
            'US' => ['United States', 'NA'],
            'UM' => ['United States Minor Outlying Islands', 'OC'],
            'UY' => ['Uruguay', 'SA'],
            'UZ' => ['Uzbekistan', 'AS'],
            'VU' => ['Vanuatu', 'OC'],
            'VE' => ['Venezuela', 'SA'],
            'VN' => ['Vietnam', 'AS'],
            'VG' => ['Virgin Islands (British)', 'NA'],
            'VI' => ['Virgin Islands (U.S.)', 'NA'],
            'WF' => ['Wallis and Futuna', 'OC'],
            'EH' => ['Western Sahara', 'AF'],
            'YE' => ['Yemen', 'AS'],
            'ZM' => ['Zambia', 'AF'],
            'ZW' => ['Zimbabwe', 'AF'],
        ];
    }

    public static function reset()
    {
        self::$country = null;
    }
}
