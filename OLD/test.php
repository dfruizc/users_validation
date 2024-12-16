<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Model\coursesModel;
use App\Engine\loadEnv;
use App\transac\log_transac;

new loadEnv();

define('MOODLE_URL', $_ENV['MOODLE_URL']);
define('MOODLE_TOKEN', $_ENV['MOODLE_TOKEN']);
$diff = 0;
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

function get_courses()
{
    static $courses = null;
    if ($courses === null) {
        $courses = call_moodle_api('core_course_get_courses', []);
        if (!is_array($courses)) {
            throw new Exception('Error: la respuesta de la API de Moodle no es un array.');
        }
    }
    return $courses;
}

function get_course_contents($course_id)
{
    static $course_contents_cache = [];
    if (!isset($course_contents_cache[$course_id])) {
        $course_contents_cache[$course_id] = call_moodle_api('core_course_get_contents', ['courseid' => $course_id]);
    }
    return $course_contents_cache[$course_id];
}

function course_exists($course_id)
{
    $courses = get_courses();
    foreach ($courses as $course) {
        if (!is_array($course) || !isset($course['id'])) {
            continue;
        }
        if ($course['id'] == $course_id) {
            return true;
        }
    }
    return false;
}

function compare_courses($course1_id, $course2_id)
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
            $differences[] = "La sección " . $section1['name'] . " no existe en el segundo curso.";
            continue;
        }

        $section2 = $course2_contents[$index];

        if ($section1['name'] != $section2['name']) {
            $identical = false;
            $differences[] = "Los nombres de las secciones son diferentes: " . $section1['name'] . " vs " . $section2['name'];
        }

        if (count($section1['modules']) != count($section2['modules'])) {
            $identical = false;
            $differences[] = "La sección " . $section1['name'] . " tiene diferente número de actividades.";
        }

        foreach ($section1['modules'] as $mod_index => $mod1) {
            if (!isset($section2['modules'][$mod_index])) {
                $identical = false;
                $differences[] = "La actividad " . $mod1['name'] . " no existe en la segunda sección.";
                continue;
            }

            $mod2 = $section2['modules'][$mod_index];

            if ($mod1['name'] != $mod2['name']) {
                $identical = false;
                $differences[] = "Los nombres de las actividades son diferentes: " . $mod1['name'] . " vs " . $mod2['name'];
            }
        }
    }

    if ($identical) {
        echo "Los cursos son idénticos.\n";
    } else {
        echo "Los cursos tienen diferencias.\n";
        $diff++;
    }
    
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time,2);

    return [
        'semilla_id' => $course1_id,
        'fic_id' => $course2_id,
        'comparison_result' => $identical ? 'Los cursos son idénticos.' : 'Los cursos tienen diferencias.',
        'differences' => implode("; ", $differences),
        'execution_time' => $execution_time
    ];
}

$start_time = microtime(true);

$courseModel = new coursesModel();
$registers = $courseModel->getCourses(10000);

$csv_data = [];
if ($registers) {
    foreach ($registers as $register) {
        $semilla_id = $register['semillaid'];
        $curso_id = $register['LMS_ID'];
        $fic_id = $register['FIC_ID'];

        if (course_exists($semilla_id) && course_exists($curso_id)) {
            echo "-------------------------------------------------------------------------\n";
            echo "Código de semilla: $semilla_id vs Código de ficha $fic_id: $curso_id -->\n";
            $comparison_result = compare_courses($semilla_id, $curso_id);
            $csv_data[] = [
                'semilla_id' => $semilla_id,
                'fic_id' => $fic_id,
                'course_id' => $curso_id,
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
                'comparison_result' => 'Uno o ambos cursos no existen.',
                'differences' => '',
                'execution_time' => 0
            ];
        }
    }
}

$end_time = microtime(true);
$total_execution_time = round($end_time - $start_time,2);
$diferencias = 'Se encontraron cursos con diferencias: ' . "$diff \n";
echo $diferencias;
echo "El tiempo total de ejecución del script fue de " . $total_execution_time . " segundos.\n";

if ($diff > 0) {
    log_transac::execution_history($diferencias, 'integridad_cursos');
}

sleep(2);
date_default_timezone_set('America/Bogota');
$date = date('Y-m-d-H-i');
//? Generar el archivo CSV
$csv_file = "../Reports/course_comparison_results_$date".'.csv';
$csv_handle = fopen($csv_file, 'w');
fputcsv($csv_handle, ['semilla_id', 'fic_id', 'course_id', 'comparison_result', 'differences', 'execution_time']);

foreach ($csv_data as $row) {
    fputcsv($csv_handle, $row);
}

fclose($csv_handle);

echo "El archivo CSV con los resultados se ha generado en: $csv_file\n";
shell_exec('python3 ./upload_reports.py');