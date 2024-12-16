from google.oauth2 import service_account
from googleapiclient.discovery import build
from googleapiclient.http import MediaFileUpload
import os
import httplib2
from google_auth_httplib2 import AuthorizedHttp
import time

#! Ruta al archivo JSON de la cuenta de servicio
SERVICE_ACCOUNT_FILE = '../../stoked-proxy-426118-a1-c382e474ca4b.json'

#!! Crear credenciales a partir del archivo de cuenta de servicio
credentials = service_account.Credentials.from_service_account_file(
    SERVICE_ACCOUNT_FILE,
    scopes=['https://www.googleapis.com/auth/drive']
)

#! Construir el servicio de Google Drive con un tiempo de espera personalizado
http = httplib2.Http(timeout=60)
authorized_http = AuthorizedHttp(credentials, http=http)
service = build('drive', 'v3', http=authorized_http)

#! Directorio local de la carpeta "reportes"
local_folder_path = '../Reports'

#! ID de la carpeta de destino en Google Drive
folder_id = '1QYmTwdDcM9dT9rxsWvUCLEx4KevN2gRp'

#! Listar todos los archivos en la carpeta local
for filename in os.listdir(local_folder_path):
    if filename.endswith(".csv"):  #! Filtrar solo archivos CSV
        local_file_path = os.path.join(local_folder_path, filename)
        
        file_metadata = {
            'name': filename,
            'parents': [folder_id]  #! Subir el archivo a la carpeta específica en Drive
        }
        
        media = MediaFileUpload(local_file_path, mimetype='text/csv')
        
        try:
            file = service.files().create(
                body=file_metadata,
                media_body=media,
                fields='id'
            ).execute()
            print(f'Archivo {filename} subido exitosamente a Google Drive. ID: {file.get("id")}')
            time.sleep(2)
            local_folder_path = '../Reports'
            csv_files = [file for file in os.listdir(local_folder_path) if file.endswith('.csv')]

            for file in csv_files:
                os.remove(os.path.join(local_folder_path, file))
        except Exception as e:
            print(f'Error al subir el archivo {filename}: {e}')

print('Todos los archivos CSV han sido subidos.')


