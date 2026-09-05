<?php
// Configurar cabeceras para devolver JSON y aceptar peticiones CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 1. Base de datos de sanciones (Listas proporcionadas)
$sanctioned_entities = [
    "Navitas Petroleum Development", "Navitas Petroleum Atlantic Limited", "Harel Insurance Investments & Financial Services LTD",
    "Migdal Insurance and Financial Holdings LTD", "The Phoenix Holdings LTD", "Phoenix Financial LTD", 
    "The Phoenix Investments House LTD", "Meitav Investment House LTD", "Gideon Abraham Tadmor Cohen", 
    "Yacob Katz", "Jonathan Akiva Sternberg", "Boaz Yoel Cohen Tadmor", "Amit Kornhauser", 
    "Rockhopper Exploration Limited", "Noked Capital LTD", "Aedos Advisers (London) LLP", 
    "Exodus Management Israel LTD", "Ion Fund Management LTD", "Samuel Moody", "William Rees Perry", 
    "Borders & Southern", "Borders & Southern Falkland Islands Limited", "Alan Brimacombe", 
    "Zila Corporation", "Interactive Investor Limited", "Hargreaves Lansdown Asset Management Limited", 
    "Halifax Share Dealing Clients", "Mr H Mason", "Mr y Mrs Newlands", "JHI Associates Inc.", 
    "JHI Falklands Inc.", "Eco Oil & Gas LTD", "Cibc Asset Mgt", "Canaccord Genuity Limited", 
    "Avanza Bank Holding AB", "Moshe Peterburg", "Askar Alshinbayev", "Coronation Fund", 
    "Westmount Energy Limited", "Noble Corporation", "AP Moller Holding A/S", 
    "First Eagle Investment Management LLC", "Vanguard Portfolio Management LLC", 
    "Dimensional Fund Advisors LP", "Netherland, Sewell & Associates Inc.", "Fugro NV"
];

// Se incluyen traducciones comunes (inglés/español) para facilitar la búsqueda
$sanctioned_countries = [
    "Canadá", "Canada", "Estados Unidos", "United States", "US", "USA",
    "Reino Unido", "United Kingdom", "UK", "Great Britain", 
    "Países Bajos", "Netherlands", "Holland", 
    "Dinamarca", "Denmark", 
    "Suecia", "Sweden",
    "Israel",
    "Kazajistán", "Kazakhstan",
    "Sudáfrica", "South Africa",
    "Islas Vírgenes Británicas", "British Virgin Islands", "BVI",
    "Isla de Jersey", "Jersey"
];

// 2. Función para normalizar texto (quitar acentos y pasar a minúsculas)
function normalizeString($string) {
    $string = mb_strtolower(trim($string), 'UTF-8');
    $unwanted_array = array(
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 
        'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u',
        'ñ'=>'n', 'Ñ'=>'n'
    );
    return strtr($string, $unwanted_array);
}

// 3. Captura de parámetros (GET o POST)
$query_string  = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';
$query_country = isset($_REQUEST['country']) ? $_REQUEST['country'] : '';
$query_ip      = isset($_REQUEST['ip']) ? $_REQUEST['ip'] : '';

// Estructura de la respuesta por defecto
$response = [
    'is_sanctioned' => false,
    'matches'       => [],
    'metadata'      => []
];

// 4. Lógica de verificación por IP (Resuelve IP a País usando API pública gratuita ip-api.com)
if (!empty($query_ip)) {
    if (filter_var($query_ip, FILTER_VALIDATE_IP)) {
        // En producción, es recomendable usar una base de datos local como GeoIP de MaxMind
        $geo_json = @file_get_contents("http://ip-api.com/json/" . $query_ip);
        if ($geo_json) {
            $geo_data = json_decode($geo_json, true);
            if (isset($geo_data['status']) && $geo_data['status'] === 'success') {
                $query_country = $geo_data['country']; // Pasa el país al evaluador de países
                $response['metadata']['ip_resolved_country'] = $query_country;
            } else {
                $response['metadata']['ip_error'] = "No se pudo geolocalizar la IP.";
            }
        }
    } else {
        $response['metadata']['ip_error'] = "Formato de IP inválido.";
    }
}

// 5. Lógica de verificación por País / Región
if (!empty($query_country)) {
    $normalized_query_country = normalizeString($query_country);
    foreach ($sanctioned_countries as $country) {
        if (normalizeString($country) === $normalized_query_country || strpos($normalized_query_country, normalizeString($country)) !== false) {
            $response['is_sanctioned'] = true;
            $response['matches'][] = [
                'type' => 'country/region',
                'matched_term' => $country,
                'requested' => $query_country
            ];
            break; // Termina al encontrar el país
        }
    }
}

// 6. Lógica de verificación por Cadena (Empresas, Actores)
if (!empty($query_string)) {
    $normalized_query = normalizeString($query_string);
    foreach ($sanctioned_entities as $entity) {
        $normalized_entity = normalizeString($entity);
        // Verifica si la cadena enviada está dentro de la lista, o si el elemento de la lista está en la cadena
        if (strpos($normalized_entity, $normalized_query) !== false || strpos($normalized_query, $normalized_entity) !== false) {
            $response['is_sanctioned'] = true;
            $response['matches'][] = [
                'type' => 'entity/actor',
                'matched_term' => $entity
            ];
        }
    }
}

// 7. Mensaje de estado
if ($response['is_sanctioned']) {
    $response['message'] = "ALERTA: El objetivo ingresado se encuentra en la lista de sanciones.";
} else {
    $response['message'] = "El objetivo ingresado NO presenta coincidencias en la lista de sanciones.";
}

// 8. Imprimir respuesta
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);