<?php
/**
 * Clase para gestionar los tours
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Tours {

    /**
     * Obtener todos los tours (desde CPT o hardcodeados)
     */
    public static function get_tours() {
        // Intentar obtener desde CPT primero
        $cpt_tours = RTT_Tours_CPT::get_tours_from_cpt();

        if (!empty($cpt_tours)) {
            return $cpt_tours;
        }

        // Fallback a tours hardcodeados
        return self::get_default_tours();
    }

    /**
     * Obtener tours por defecto - Actualizados desde readytotravelperu.com
     */
    public static function get_default_tours() {
        return [
            // ========================================
            // TOURS DE MEDIO DIA (HALF DAY)
            // ========================================
            [
                'name' => 'City Tour Cusco Medio Dia',
                'name_en' => 'Cusco City Tour Half Day',
                'duration' => '1/2 DIA',
                'duration_en' => 'HALF DAY',
                'price' => 25,
                'price_full' => 37,
                'price_note' => 'No incluye entradas a los complejos arqueológicos',
                'price_note_en' => 'Entrance fees to archaeological sites not included',
                'category' => 'half_day'
            ],
            [
                'name' => 'Tour Valle Sur Medio Dia',
                'name_en' => 'South Valley Tour Half Day',
                'duration' => '1/2 DIA',
                'duration_en' => 'HALF DAY',
                'price' => 10,
                'price_full' => 14,
                'price_note' => 'No incluye entradas',
                'price_note_en' => 'Entrance fees not included',
                'category' => 'half_day'
            ],
            [
                'name' => 'Tour Maras Moray Medio Dia',
                'name_en' => 'Maras Moray Tour Half Day',
                'duration' => '1/2 DIA',
                'duration_en' => 'HALF DAY',
                'price' => 10,
                'price_full' => 14,
                'price_note' => 'No incluye entradas a Maras y Moray',
                'price_note_en' => 'Maras and Moray entrance not included',
                'category' => 'half_day'
            ],
            [
                'name' => 'Tour Cuatrimotos Maras Moray Medio Dia',
                'name_en' => 'ATV Maras Moray Tour Half Day',
                'duration' => '1/2 DIA',
                'duration_en' => 'HALF DAY',
                'price' => 27,
                'category' => 'half_day'
            ],
            [
                'name' => 'Tour Bicicletas Maras Moray Salineras Medio Dia',
                'name_en' => 'Bike Maras Moray Salt Mines Half Day',
                'duration' => '1/2 DIA',
                'duration_en' => 'HALF DAY',
                'price' => 76,
                'category' => 'half_day'
            ],

            // ========================================
            // TOURS DE 1 DIA (FULL DAY)
            // ========================================
            [
                'name' => 'Tour Valle Sagrado de los Incas Todo el Dia',
                'name_en' => 'Sacred Valley of the Incas Full Day Tour',
                'duration' => '1 DIA',
                'duration_en' => '1 DAY',
                'price' => 33,
                'category' => 'full_day'
            ],
            [
                'name' => 'Tour Valle Sagrado Maras Moray Todo el Dia',
                'name_en' => 'Sacred Valley Maras Moray Full Day Tour',
                'duration' => '1 DIA',
                'duration_en' => '1 DAY',
                'price' => 35,
                'category' => 'full_day'
            ],
            [
                'name' => 'Tour Montana 7 Colores Todo El Dia',
                'name_en' => 'Rainbow Mountain Full Day Tour',
                'duration' => '1 DIA',
                'duration_en' => '1 DAY',
                'price' => 25,
                'price_full' => 35,
                'price_note' => 'No incluye desayuno ni entrada',
                'price_note_en' => 'Breakfast and entrance not included',
                'category' => 'full_day'
            ],
            [
                'name' => 'Tour Montana Palcoyo Full Day',
                'name_en' => 'Palcoyo Mountain Full Day Tour',
                'duration' => '1 DIA',
                'duration_en' => '1 DAY',
                'price' => 25,
                'price_full' => 37,
                'price_note' => 'No incluye desayuno ni entrada',
                'price_note_en' => 'Breakfast and entrance not included',
                'category' => 'full_day'
            ],
            [
                'name' => 'Tour Laguna Humantay Todo El Dia',
                'name_en' => 'Humantay Lake Full Day Tour',
                'duration' => '1 DIA',
                'duration_en' => '1 DAY',
                'price' => 25,
                'price_full' => 35,
                'price_note' => 'No incluye desayuno',
                'price_note_en' => 'Breakfast not included',
                'category' => 'full_day'
            ],
            [
                'name' => 'Tour Machupicchu Magico Todo el Dia',
                'name_en' => 'Magical Machu Picchu Full Day Tour',
                'duration' => '1 DIA',
                'duration_en' => '1 DAY',
                'price' => 275,
                'category' => 'full_day'
            ],
            [
                'name' => 'Tour Machupicchu y Waynapicchu Enigmatico Todo el Dia',
                'name_en' => 'Machu Picchu and Huayna Picchu Enigmatic Full Day',
                'duration' => '1 DIA',
                'duration_en' => '1 DAY',
                'price' => 335,
                'category' => 'full_day'
            ],
            [
                'name' => 'Tour Puente Inca Qeswachaka 1 Dia',
                'name_en' => 'Inca Bridge Qeswachaka 1 Day Tour',
                'duration' => '1 DIA',
                'duration_en' => '1 DAY',
                'price' => 37,
                'category' => 'full_day'
            ],
            [
                'name' => 'Tour 7 Lagunas Ausangate',
                'name_en' => '7 Lagoons Ausangate Tour',
                'duration' => '1 DIA',
                'duration_en' => '1 DAY',
                'price' => 35,
                'category' => 'full_day'
            ],
            [
                'name' => 'Tour Waqrapukara en Cusco 1 Dia',
                'name_en' => 'Waqrapukara Cusco 1 Day Tour',
                'duration' => '1 DIA',
                'duration_en' => '1 DAY',
                'price' => 37,
                'category' => 'full_day'
            ],

            // ========================================
            // TOURS DE 2 DIAS
            // ========================================
            [
                'name' => 'Tour Camino Inca 2 Dias / 1 Noche',
                'name_en' => 'Inca Trail 2 Days / 1 Night',
                'duration' => '2 DIAS',
                'duration_en' => '2 DAYS',
                'price' => 519,
                'category' => '2_days'
            ],
            [
                'name' => 'City Tour Cusco y Machupicchu 2 Dias / 1 Noche',
                'name_en' => 'Cusco City Tour and Machu Picchu 2 Days / 1 Night',
                'duration' => '2 DIAS',
                'duration_en' => '2 DAYS',
                'price' => 305,
                'category' => '2_days'
            ],
            [
                'name' => 'Tour Valle Sagrado y Machupicchu 2 Dias / 1 Noche',
                'name_en' => 'Sacred Valley and Machu Picchu 2 Days / 1 Night',
                'duration' => '2 DIAS',
                'duration_en' => '2 DAYS',
                'price' => 268,
                'category' => '2_days'
            ],

            // ========================================
            // TOURS DE 3 DIAS
            // ========================================
            [
                'name' => 'Tour Inca Jungle 3 Dias / 2 Noches',
                'name_en' => 'Inca Jungle 3 Days / 2 Nights',
                'duration' => '3 DIAS',
                'duration_en' => '3 DAYS',
                'price' => 479,
                'category' => '3_days'
            ],
            [
                'name' => 'Cusco, Valle Sagrado y Machupicchu 3 Dias',
                'name_en' => 'Cusco, Sacred Valley and Machu Picchu 3 Days',
                'duration' => '3 DIAS',
                'duration_en' => '3 DAYS',
                'price' => 305,
                'category' => '3_days'
            ],
            [
                'name' => 'City Tour Cusco, Machupicchu y Laguna Humantay 3 Dias',
                'name_en' => 'Cusco City Tour, Machu Picchu and Humantay Lake 3 Days',
                'duration' => '3 DIAS',
                'duration_en' => '3 DAYS',
                'price' => 308,
                'category' => '3_days'
            ],
            [
                'name' => 'City Tour Cusco, Machupicchu y Montana 7 Colores 3 Dias',
                'name_en' => 'Cusco City Tour, Machu Picchu and Rainbow Mountain 3 Days',
                'duration' => '3 DIAS',
                'duration_en' => '3 DAYS',
                'price' => 308,
                'category' => '3_days'
            ],
            [
                'name' => 'Valle Sagrado, Machupicchu y Laguna Humantay 3 Dias',
                'name_en' => 'Sacred Valley, Machu Picchu and Humantay Lake 3 Days',
                'duration' => '3 DIAS',
                'duration_en' => '3 DAYS',
                'price' => 294,
                'category' => '3_days'
            ],
            [
                'name' => 'Valle Sagrado, Machupicchu y Montana 7 Colores 3 Dias',
                'name_en' => 'Sacred Valley, Machu Picchu and Rainbow Mountain 3 Days',
                'duration' => '3 DIAS',
                'duration_en' => '3 DAYS',
                'price' => 295,
                'category' => '3_days'
            ],

            // ========================================
            // TOURS DE 4 DIAS
            // ========================================
            [
                'name' => 'Tour Camino Inca 4 Dias / 3 Noches',
                'name_en' => 'Inca Trail 4 Days / 3 Nights',
                'duration' => '4 DIAS',
                'duration_en' => '4 DAYS',
                'price' => 745,
                'category' => '4_days'
            ],
            [
                'name' => 'Tour Inca Jungle 4 Dias / 3 Noches',
                'name_en' => 'Inca Jungle 4 Days / 3 Nights',
                'duration' => '4 DIAS',
                'duration_en' => '4 DAYS',
                'price' => 529,
                'category' => '4_days'
            ],
            [
                'name' => 'Tour Salkantay 4 Dias / 3 Noches',
                'name_en' => 'Salkantay Trek 4 Days / 3 Nights',
                'duration' => '4 DIAS',
                'duration_en' => '4 DAYS',
                'price' => 495,
                'category' => '4_days'
            ],
            [
                'name' => 'Cusco, Valle Sagrado, Machupicchu y Laguna Humantay 4 Dias',
                'name_en' => 'Cusco, Sacred Valley, Machu Picchu and Humantay Lake 4 Days',
                'duration' => '4 DIAS',
                'duration_en' => '4 DAYS',
                'price' => 313,
                'category' => '4_days'
            ],
            [
                'name' => 'Cusco, Valle Sagrado, Machupicchu y Montana 7 Colores 4 Dias',
                'name_en' => 'Cusco, Sacred Valley, Machu Picchu and Rainbow Mountain 4 Days',
                'duration' => '4 DIAS',
                'duration_en' => '4 DAYS',
                'price' => 315,
                'category' => '4_days'
            ],

            // ========================================
            // TOURS DE 5 DIAS
            // ========================================
            [
                'name' => 'Tour Salkantay 5 Dias / 4 Noches',
                'name_en' => 'Salkantay Trek 5 Days / 4 Nights',
                'duration' => '5 DIAS',
                'duration_en' => '5 DAYS',
                'price' => 509,
                'category' => '5_days'
            ],
            [
                'name' => 'Cusco, Valle Sagrado, Machupicchu, Humantay y Cuatrimotos 5 Dias',
                'name_en' => 'Cusco, Sacred Valley, Machu Picchu, Humantay and ATV 5 Days',
                'duration' => '5 DIAS',
                'duration_en' => '5 DAYS',
                'price' => 361,
                'category' => '5_days'
            ],
            [
                'name' => 'Cusco, Valle Sagrado, Machupicchu, Montana 7 Colores y Cuatrimotos 5 Dias',
                'name_en' => 'Cusco, Sacred Valley, Machu Picchu, Rainbow Mountain and ATV 5 Days',
                'duration' => '5 DIAS',
                'duration_en' => '5 DAYS',
                'price' => 363,
                'category' => '5_days'
            ],
            [
                'name' => 'Cusco, Valle Sagrado, Machupicchu, Humantay y Valle Sur 5 Dias',
                'name_en' => 'Cusco, Sacred Valley, Machu Picchu, Humantay and South Valley 5 Days',
                'duration' => '5 DIAS',
                'duration_en' => '5 DAYS',
                'price' => 342,
                'category' => '5_days'
            ],
            [
                'name' => 'Cusco, Valle Sagrado, Machupicchu, Montana 7 Colores y Valle Sur 5 Dias',
                'name_en' => 'Cusco, Sacred Valley, Machu Picchu, Rainbow Mountain and South Valley 5 Days',
                'duration' => '5 DIAS',
                'duration_en' => '5 DAYS',
                'price' => 343,
                'category' => '5_days'
            ],
        ];
    }

    /**
     * Obtener tours por idioma
     */
    public static function get_tours_by_language($lang = 'es') {
        $tours = self::get_tours();
        $result = [];

        foreach ($tours as $tour) {
            $tour_data = [
                'name' => $lang === 'en' ? $tour['name_en'] : $tour['name'],
                'duration' => $lang === 'en' ? $tour['duration_en'] : $tour['duration'],
                'price' => $tour['price'] ?? 0,
                'category' => $tour['category'] ?? ''
            ];

            // Agregar precio completo si existe
            if (isset($tour['price_full']) && $tour['price_full'] > 0) {
                $tour_data['price_full'] = $tour['price_full'];
                // Agregar notas de precio
                $tour_data['price_note'] = $tour['price_note'] ?? '';
                $tour_data['price_note_en'] = $tour['price_note_en'] ?? '';
            }

            $result[] = $tour_data;
        }

        return $result;
    }

    /**
     * Obtener tours agrupados por categoría
     */
    public static function get_tours_grouped($lang = 'es') {
        $tours = self::get_tours_by_language($lang);
        $grouped = [];

        foreach ($tours as $tour) {
            $category = $tour['duration'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }

            $tour_data = [
                'name' => $tour['name'],
                'price' => $tour['price'] ?? 0
            ];

            // Agregar precio completo y notas si existen
            if (isset($tour['price_full']) && $tour['price_full'] > 0) {
                $tour_data['price_full'] = $tour['price_full'];
                $tour_data['price_note'] = $tour['price_note'] ?? '';
                $tour_data['price_note_en'] = $tour['price_note_en'] ?? '';
            }

            $grouped[$category][] = $tour_data;
        }

        return $grouped;
    }

    /**
     * Obtener lista de países
     */
    public static function get_countries() {
        return [
            'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AD' => 'Andorra',
            'AO' => 'Angola', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia',
            'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
            'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
            'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BT' => 'Bhutan',
            'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil',
            'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
            'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde',
            'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China',
            'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CR' => 'Costa Rica',
            'HR' => 'Croatia', 'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic',
            'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic',
            'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FJ' => 'Fiji',
            'FI' => 'Finland', 'FR' => 'France', 'GA' => 'Gabon', 'GM' => 'Gambia',
            'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GR' => 'Greece',
            'GD' => 'Grenada', 'GT' => 'Guatemala', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana', 'HT' => 'Haiti', 'HN' => 'Honduras', 'HU' => 'Hungary',
            'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran',
            'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy',
            'JM' => 'Jamaica', 'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan',
            'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'North Korea', 'KR' => 'South Korea',
            'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia',
            'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya',
            'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MK' => 'Macedonia',
            'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives',
            'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MR' => 'Mauritania',
            'MU' => 'Mauritius', 'MX' => 'Mexico', 'FM' => 'Micronesia', 'MD' => 'Moldova',
            'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MA' => 'Morocco',
            'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru',
            'NP' => 'Nepal', 'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua',
            'NE' => 'Niger', 'NG' => 'Nigeria', 'NO' => 'Norway', 'OM' => 'Oman',
            'PK' => 'Pakistan', 'PW' => 'Palau', 'PA' => 'Panama', 'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland',
            'PT' => 'Portugal', 'QA' => 'Qatar', 'RO' => 'Romania', 'RU' => 'Russia',
            'RW' => 'Rwanda', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia',
            'VC' => 'Saint Vincent and the Grenadines', 'WS' => 'Samoa', 'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
            'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore',
            'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia',
            'ZA' => 'South Africa', 'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka',
            'SD' => 'Sudan', 'SR' => 'Suriname', 'SZ' => 'Swaziland', 'SE' => 'Sweden',
            'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'East Timor', 'TG' => 'Togo',
            'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
            'TM' => 'Turkmenistan', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
            'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican',
            'VE' => 'Venezuela', 'VN' => 'Vietnam', 'YE' => 'Yemen', 'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe'
        ];
    }

    /**
     * Obtener lista de países con código para banderas SVG
     */
    public static function get_countries_with_flags() {
        $countries = self::get_countries();
        $with_flags = [];
        foreach ($countries as $code => $name) {
            $with_flags[$code] = [
                'code' => strtolower($code),
                'name' => $name
            ];
        }
        return $with_flags;
    }
}
