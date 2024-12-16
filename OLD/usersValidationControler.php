<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Engine\loadEnv;
use App\Model\usersModel;
use App\Model\logModel;

class UsersValidationController
{
    public function __construct()
    {
        new loadEnv();
    }

    public function usersValidation()
    {
        // Instancia del modelo de usuarios
        $userModel = new usersModel();

        // Obtener los usuarios que cumplen las condiciones de LMS_ESTADO = 2, OPERATION = 'P', LMS_ID IS NOT NULL
        $usersToValidate = $userModel->getusers('2', 'P', "AND \"LMS_ID\" IS NOT NULL");

        if (!is_array($usersToValidate) || count($usersToValidate) < 1) {
            echo "No hay usuarios con LMS_ESTADO = 2, OPERATION = 'P', y LMS_ID IS NOT NULL para validar.\n";
            return;
        }

        echo "Cantidad de usuarios a validar: " . count($usersToValidate) . "\n";

        foreach ($usersToValidate as $user) {
            // Preparar el campo 'username'
            $username = !empty($user['USR_NUM_DOC_CONCAT']) ? $user['USR_NUM_DOC_CONCAT'] : $user['USR_NUM_DOC'];
            $lmsid = $user['LMS_ID'];

            // Construir parámetros para la API
            $params = [
                'field' => 'username',
                'values[0]' => $username
            ];

            // Llamar a la API de Zajuna
            $response = $this->requestToZajuna($params);

            if (!empty($response) && isset($response[0]['username'])) {
                $userFromZajuna = $response[0];
                $zajunaUserId = $userFromZajuna['id'];

                if ($zajunaUserId == $lmsid) {
                    // Actualizar el estado a P si coincide
                    $userModel->updateException($username, 'USUARIO_LMS', 2);
                    echo "Usuario $username validado correctamente. Estado actualizado a 2.\n";
                    logModel::msj_exception("Usuario $username validado correctamente. Estado actualizado a P.", 'info', 'usersValidation');
                } else {
                    // Actualizar estado a 6767 si no coincide
                    $userModel->updateException($username, 'USUARIO_LMS', 6767);
                    echo "Usuario $username con LMS_ID no coincide. Estado actualizado a 6767.\n";
                    logModel::msj_exception("Usuario $username con LMS_ID no coincide. Estado actualizado a 6767.", 'warning', 'usersValidation');
                }
            } else {
                // Usuario no encontrado en Zajuna, actualizar a estado 1
                //$userModel->updateException($username, 'USUARIO_LMS', 1);
                //echo "Usuario $username no encontrado en Zajuna. Estado actualizado a 1.\n";
                logModel::msj_exception("Usuario $username no encontrado en Zajuna.", 'error', 'usersValidation');
            }

            // Pausa para evitar sobrecarga en la API
            sleep(1);
        }
    }

    private function requestToZajuna($params)
    {
        // Obtener el token y la URL de la API de Zajuna desde las variables de entorno
        $token = $_ENV['WSTOKEN'];
        $function = 'core_user_get_users_by_field';  // Función de Moodle para buscar usuarios por campo
        $url = $_ENV['API_ZAJUNA'] . 'wstoken=' . $token . '&wsfunction=' . $function . '&moodlewsrestformat=json';

        // Inicializar cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));  // Parámetros de búsqueda
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Ejecutar cURL y obtener la respuesta
        $response = curl_exec($ch);

        // Manejar errores de cURL
        if ($response === false) {
            $error = curl_error($ch);
            echo "Error cURL: $error \n";
            logModel::msj_exception("Error cURL: $error", 'error', 'usersValidation');
            return null;
        }

        // Cerrar cURL
        curl_close($ch);

        // Devolver la respuesta decodificada
        return json_decode($response, true);
    }
}

// Ejecución del script
$validationController = new UsersValidationController();
$validationController->usersValidation();
