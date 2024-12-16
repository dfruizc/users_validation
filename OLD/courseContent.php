<?php
#  VERSIÓN IGUAL A couseContentIntegrity.php SIN INSERSIÓN DE DATOS EN BASE DE DATOS
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Model\coursesModel;
use App\Engine\loadEnv;
use App\transac\log_transac;
use App\Controller\db_channel\DbTelegram;

new loadEnv();

define('MOODLE_URL', $_ENV['MOODLE_URL']);
define('MOODLE_TOKEN', $_ENV['MOODLE_TOKEN']);
$diff = 0;
$complementaria_diff_count = 0;  // Contador de diferencias en cursos complementaria
$titulada_diff_count = 0;        // Contador de diferencias en cursos titulada

function call_moodle_api($function, $params)
{
    new loadEnv();
    $url = MOODLE_URL . '/webservice/rest/server.php';
    $params['wstoken'] = MOODLE_TOKEN;
    $params['wsfunction'] = $function;
    $params['moodlewsrestformat'] = 'json';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Error en la llamada cURL: ' . curl_error($ch));
    }

    $decoded_response = json_decode($response, true);
    if ($decoded_response === null) {
        throw new Exception('Error decodificando la respuesta JSON');
    }

    return $decoded_response;
}

function get_course_by_id($course_id)
{
    $params = array('field' => 'id', 'value' => $course_id);
    return call_moodle_api('core_course_get_courses_by_field', $params);
}

function course_exists($course_id)
{
    $course = get_course_by_id($course_id);
    return !empty($course);
}

function get_course_contents($course_id)
{
    static $course_contents_cache = [];
    if (!isset($course_contents_cache[$course_id])) {
        $course_contents_cache[$course_id] = call_moodle_api('core_course_get_contents', ['courseid' => $course_id]);
    }
    return $course_contents_cache[$course_id];
}

function compare_courses($course1_id, $course2_id, &$diff_count)
{
    global $diff;
    $start_time = microtime(true);

    $course1_exists = course_exists($course1_id);
    $course2_exists = course_exists($course2_id);

    if (!$course1_exists || !$course2_exists) {
        echo "Uno o ambos cursos no existen.\n";
        return [
            'semilla_id' => $course1_id,
            'fic_id' => $course2_id,
            'comparison_result' => 'Uno o ambos cursos no existen.',
            'differences' => '',
            'execution_time' => 0
        ];
    }

    $course1_contents = get_course_contents($course1_id);
    $course2_contents = get_course_contents($course2_id);

    if (empty($course1_contents) || empty($course2_contents)) {
        echo "No se pudo obtener el contenido de uno o ambos cursos.\n";
        return [
            'semilla_id' => $course1_id,
            'fic_id' => $course2_id,
            'comparison_result' => 'No se pudo obtener el contenido de uno o ambos cursos.',
            'differences' => '',
            'execution_time' => 0
        ];
    }

    $course1_section_count = count($course1_contents);
    $course2_section_count = count($course2_contents);

    echo "El curso con ID $course1_id tiene $course1_section_count secciones.\n";
    echo "El curso con ID $course2_id tiene $course2_section_count secciones.\n";

    $identical = true;
    $differences = [];

    if ($course1_section_count != $course2_section_count) {
        $identical = false;
        $differences[] = "Diferente número de secciones: $course1_section_count vs $course2_section_count";
    }

    foreach ($course1_contents as $index => $section1) {
        if (!isset($course2_contents[$index])) {
            $identical = false;
            $differences[] = "La sección " . (is_array($section1) && isset($section1['name']) ? $section1['name'] : 'Sin nombre') . " no existe en el segundo curso.";
            continue;
        }

        $section2 = $course2_contents[$index];

        if (is_array($section1) && isset($section1['name']) && is_array($section2) && isset($section2['name'])) {
            if ($section1['name'] != $section2['name']) {
                $identical = false;
                $differences[] = "Los nombres de las secciones son diferentes: " . $section1['name'] . " vs " . $section2['name'];
            }
        } else {
            $identical = false;
            $differences[] = "Error al comparar nombres de secciones.";
        }

        if (is_array($section1) && isset($section1['modules']) && is_array($section2) && isset($section2['modules'])) {
            if (count($section1['modules']) != count($section2['modules'])) {
                $identical = false;
                $differences[] = "La sección " . $section1['name'] . " tiene diferente número de actividades.";
            }

            foreach ($section1['modules'] as $mod_index => $mod1) {
                if (!isset($section2['modules'][$mod_index])) {
                    $identical = false;
                    $differences[] = "La actividad " . (is_array($mod1) && isset($mod1['name']) ? $mod1['name'] : 'Sin nombre') . " no existe en la segunda sección.";
                    continue;
                }

                $mod2 = $section2['modules'][$mod_index];

                if (is_array($mod1) && isset($mod1['name']) && is_array($mod2) && isset($mod2['name'])) {
                    if ($mod1['name'] != $mod2['name']) {
                        $identical = false;
                        $differences[] = "Los nombres de las actividades son diferentes: " . $mod1['name'] . " vs " . $mod2['name'];
                    }
                } else {
                    $identical = false;
                    $differences[] = "Error al comparar nombres de actividades.";
                }
            }
        } else {
            $identical = false;
            $differences[] = "Error al comparar módulos de una o ambas secciones.";
        }
    }

    if ($identical) {
        echo "Los cursos son idénticos.\n";
    } else {
        echo "Los cursos tienen diferencias.\n";
        $diff++;
        $diff_count++;  // Incrementar el contador de diferencias para el tipo de curso correspondiente
    }

    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);

    return [
        'semilla_id' => $course1_id,
        'fic_id' => $course2_id,
        'comparison_result' => $identical ? 'Los cursos son idénticos.' : 'Los cursos tienen diferencias.',
        'differences' => implode("; ", $differences),
        'execution_time' => $execution_time
    ];
}

// Función para analizar cursos y generar el CSV
function analyze_courses($registers, $type, &$csv_data, &$diff_count)
{
    foreach ($registers as $register) {
        $semilla_id = $register['semillaid'];
        $curso_id = $register['LMS_ID'];
        $fic_id = $register['FIC_ID'];

        if (course_exists($semilla_id) && course_exists($curso_id)) {
            echo "-------------------------------------------------------------------------\n";
            echo "Código de semilla: $semilla_id vs Código de ficha $fic_id: $curso_id ($type) -->\n";
            $comparison_result = compare_courses($semilla_id, $curso_id, $diff_count);
            $csv_data[] = [
                'semilla_id' => $semilla_id,
                'fic_id' => $fic_id,
                'course_id' => $curso_id,
                'course_type' => $type,  // Tipo de curso (Complementaria o Titulada)
                'comparison_result' => $comparison_result['comparison_result'],
                'differences' => $comparison_result['differences'],
                'execution_time' => $comparison_result['execution_time']
            ];
        } else {
            echo "Buscando...\n\n";
            $csv_data[] = [
                'semilla_id' => $semilla_id,
                'fic_id' => $fic_id,
                'course_id' => $curso_id,
                'course_type' => $type,  // Tipo de curso
                'comparison_result' => 'Uno o ambos cursos no existen.',
                'differences' => '',
                'execution_time' => 0
            ];
        }
    }
}

$start_time = microtime(true);

$courseModel = new coursesModel();
$csv_data = [];

// Analizar los cursos de complementaria (tabla SEEDS)
$registers_complementaria = $courseModel->getCourses(100000, 'SEEDS');  // Mantener lógica actual
if(!empty($registers_complementaria)){
    analyze_courses($registers_complementaria, 'Complementaria', $csv_data, $complementaria_diff_count);
}
else{
    echo "No hay registros para oferta complementaria en el rango de días especificado \n";
}

// Ahora cambiamos a la tabla SEEDS_INDUCTION para analizar los cursos de titulada
$registers_titulada = $courseModel->getCourses(100000, 'SEEDS_INDUCTION');
if(!empty($registers_titulada)){
    analyze_courses($registers_titulada, 'Titulada', $csv_data, $titulada_diff_count);
}
else{
    echo "No hay registros para oferta titulada en el rango de días especificado \n";
}

$end_time = microtime(true);
$total_execution_time = round($end_time - $start_time, 2);
if ($total_execution_time < 60) {
    // Si el tiempo es menor a 60 segundos, mostrar en segundos
    $total_execution_time = $total_execution_time . ' segundos';
} elseif ($total_execution_time < 3600) {
    // Si el tiempo es menor a 60 minutos, mostrar en minutos
    $minutes = round($total_execution_time / 60, 2);
    $total_execution_time = $minutes . ' minutos';
} else {
    // Si el tiempo es mayor o igual a 3600 segundos, mostrar en horas
    $hours = round($total_execution_time / 3600, 2);
    $total_execution_time = $hours . ' horas';
}

echo "El tiempo total de ejecución del script fue de " . $total_execution_time . "\n";

// Mostrar los resultados de diferencias
echo "Total de cursos con diferencias en Complementaria: $complementaria_diff_count\n";
echo "Total de cursos con diferencias en Titulada: $titulada_diff_count\n";

// Generación del archivo CSV
date_default_timezone_set('America/Bogota');
$date = date('Y-m-d-H-i');
$csv_file = "../Reports/course_comparison_results_$date" . '.csv';
$csv_handle = fopen($csv_file, 'w');

// Agregamos el campo course_type al encabezado del CSV
fputcsv($csv_handle, ['semilla_id', 'fic_id', 'course_id', 'course_type', 'comparison_result', 'differences', 'execution_time']);

foreach ($csv_data as $row) {
    fputcsv($csv_handle, $row);
}

fclose($csv_handle);

//echo "El archivo CSV con los resultados se ha generado en: $csv_file\n";
shell_exec('python3 ./upload_reports.py');
$output = shell_exec('python3 ./upload_reports.py 2>&1');  // Redirige errores estándar a la salida
if ($output === null) {
    echo "Ocurrió un error: Error al ejecutar el comando bash.\n";
} else {
    echo $output;  // Muestra el resultado de la ejecución del comando
}


$instanciate = new DbTelegram();

