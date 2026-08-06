<?php
declare(strict_types=1);

// Credenciais lidas de variáveis de ambiente; fallback para valores locais se não configuradas.
// Em produção defina GPON_DB_HOST, GPON_DB_NAME, GPON_DB_USER, GPON_DB_PASS no ambiente Apache/PHP.
define('GPON_DB_HOST',    getenv('GPON_DB_HOST') ?: 'localhost');
define('GPON_DB_NAME',    getenv('GPON_DB_NAME') ?: 'sgdservice_gpon');
define('GPON_DB_USER',    getenv('GPON_DB_USER') ?: 'sgdservice_gpon');
define('GPON_DB_PASS',    getenv('GPON_DB_PASS') ?: 'z8JC8Ubpt3jUAeBdNdfr');
define('GPON_DB_CHARSET', 'utf8mb4');

function gpon_pdo(): PDO
{
    static $instance = null;
    if ($instance === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            GPON_DB_HOST, GPON_DB_NAME, GPON_DB_CHARSET
        );
        $instance = new PDO($dsn, GPON_DB_USER, GPON_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $instance->exec("SET time_zone = '-03:00'"); // Brasília: datas armazenadas sem conversão
    }
    return $instance;
}
