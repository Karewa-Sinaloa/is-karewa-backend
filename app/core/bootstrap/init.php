<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../config/base.php';
require_once CORE_PATH . 'helpers/log.manager.php';
require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'helpers/api_response.php';
require_once CORE_PATH . 'helpers/utilities.php';
require_once CORE_PATH . 'model/conexion.php';
require_once CORE_PATH . 'model/update.php';
require_once CORE_PATH . 'model/store.php';
require_once CORE_PATH . 'model/delete.php';
require_once CORE_PATH . 'model/get.php';
require_once CORE_PATH . 'helpers/api_configuration.php';
require_once CORE_PATH . 'helpers/curl.php';
require_once CORE_PATH . 'helpers/hcaptcha.php';
require_once CORE_PATH . 'bootstrap/midelware.php';
require_once CORE_PATH . 'auth/session.set.php';
require_once CORE_PATH . 'auth/hash.auth.php';
require_once CORE_PATH . 'auth/module.access.controller.php';
require_once CORE_PATH . 'validation/fields.php';
require_once CORE_PATH . 'validation/files.php';
require_once CORE_PATH . 'components/image_upload/create_file_folder.php';
require_once CORE_PATH . 'components/image_upload/imageUpload.php';
require_once CORE_PATH . 'helpers/phpmailer.php';

require_once CORE_PATH . 'bootstrap/methods.php';
require_once CORE_PATH . 'bootstrap/post_params.php';
require_once CORE_PATH . 'bootstrap/modules.php';
require_once CORE_PATH . 'bootstrap/routes.php';
?>
