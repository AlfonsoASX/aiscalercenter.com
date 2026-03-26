<?php
declare(strict_types=1);

function databaseConnection(): PDO
{
    /** @var array{
     *     project: array{
     *         url: string,
     *         publishable_key: string,
     *         secret_key: string
     *     },
     *     database: array{
     *         host: string,
     *         port: string,
     *         name: string,
     *         user: string,
     *         password: string,
     *         sslmode: string
     *     }
     * } $supabase
     */
    $supabase = require __DIR__ . '/supabase.php';
    $database = $supabase['database'];

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $database['host'],
        $database['port'],
        $database['name'],
        $database['sslmode']
    );

    try {
        return new PDO(
            $dsn,
            $database['user'],
            $database['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $exception) {
        throw new PDOException(
            'No se pudo conectar a Supabase. Revisa config/supabase.php.',
            (int) $exception->getCode(),
            $exception
        );
    }
}
