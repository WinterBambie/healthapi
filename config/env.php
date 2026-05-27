<?php
define('APP_URL',      getenv('APP_URL')      ?: 'http://localhost');
define('FRONTEND_URL', getenv('FRONTEND_URL') ?: 'http://localhost:5173');
define('JWT_SECRET',   getenv('JWT_SECRET')   ?: 'clave_secreta');

define('MAIL_HOST',      getenv('MAIL_HOST')      ?: 'smtp.gmail.com');
define('MAIL_PORT',      getenv('MAIL_PORT')       ?: 587);
define('MAIL_USER',      getenv('MAIL_USER')       ?: '');
define('MAIL_PASS',      getenv('MAIL_PASS')       ?: '');
define('MAIL_FROM',      getenv('MAIL_FROM')       ?: '');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME')  ?: 'softyhealth');