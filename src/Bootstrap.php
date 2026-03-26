<?php
// src/Bootstrap.php
namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

class Bootstrap {
    public static function init() {
        // 1. Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 2. Cargar variables de entorno
        try {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->load();
        } catch (\Exception $e) {
            // Manejo de error si falta el .env
        }
    }

    // Esta función bloquea el acceso a las herramientas si no pagó
    public static function protect() {
        self::init();

        // 1. Verificar Login
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login.php');
            exit;
        }

        // 2. Verificar Pago (Asumiendo que guardaste esto en sesión al login)
        // Puedes comentar esto mientras desarrollas para probar gratis
        /*
        if ($_SESSION['subscription_status'] !== 'active') {
            header('Location: /billing/checkout.php');
            exit;
        }
        */
    }
}