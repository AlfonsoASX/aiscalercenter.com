<?php
declare(strict_types=1);

$regularMenu = [
    [
        'id' => 'inicio-herramientas',
        'label' => 'Inicio',
        'hover_label' => 'Todas las herramientas',
        'icon_path' => 'img/ico/5.svg',
        'color' => '#2F7CEF',
        'section_title' => 'Inicio',
        'tool_category_key' => 'all',
    ],
    [
        'id' => 'Investigar',
        'label' => 'Investigar',
        'hover_label' => 'Genera ideas',
        'icon_path' => 'img/ico/1.svg',
        'color' => '#1A3C6E',
        'section_title' => 'Investigar',
        'tool_category_key' => 'investigar',
    ],
    [
        'id' => 'Diseñar',
        'label' => 'Diseñar',
        'hover_label' => 'Diseñar',
        'icon_path' => 'img/ico/2.svg',
        'color' => '#D93025',
        'section_title' => 'Diseñar',
        'tool_category_key' => 'disenar',
    ],
    [
        'id' => 'Ejecutar',
        'label' => 'Ejecutar',
        'hover_label' => 'Programa contenido',
        'icon_path' => 'img/ico/3.svg',
        'color' => '#DF9C0A',
        'section_title' => 'Ejecutar',
        'tool_category_key' => 'ejecutar',
    ],
    [
        'id' => 'Analizar',
        'label' => 'Analizar',
        'hover_label' => 'Analizar',
        'icon_path' => 'img/ico/4.svg',
        'color' => '#188038',
        'section_title' => 'Analizar',
        'tool_category_key' => 'analizar',
    ],
    [
        'id' => 'Conecta',
        'label' => 'Conecta',
        'hover_label' => 'Redes sociales',
        'icon_path' => 'img/ico/5.svg',
        'color' => '#000000',
        'section_title' => 'Conecta',
    ],
    [
        'id' => 'configuracion-proyecto',
        'label' => 'Configuracion',
        'hover_label' => 'Configurar proyecto',
        'icon_path' => 'img/ico/9.svg',
        'color' => '#202124',
        'section_title' => 'Configuracion del proyecto',
        'requires_project' => true,
    ],
];

$adminMenu = [
    [
        'id' => 'paginas-del-sitio',
        'label' => 'Paginas del sitio',
        'hover_label' => 'Paginas del sitio',
        'icon_path' => 'img/ico/6.svg',
        'color' => '#000000',
        'section_title' => 'Paginas del sitio',
    ],
    [
        'id' => 'entradas-del-blog',
        'label' => 'Entradas del blog',
        'hover_label' => 'Entradas del blog',
        'icon_path' => 'img/ico/7.svg',
        'color' => '#000000',
        'section_title' => 'Entradas del blog',
    ],
    [
        'id' => 'cursos',
        'label' => 'Cursos',
        'hover_label' => 'Cursos',
        'icon_path' => 'img/ico/8.svg',
        'color' => '#000000',
        'section_title' => 'Cursos',
    ],
];

return [
    'bootstrap_admins' => [
        'a@asx.mx',
    ],
    'roles' => [
        'regular' => [
            'label' => 'Usuario regular',
        ],
        'admin' => [
            'label' => 'Administrador',
        ],
    ],
    'dashboard' => [
        'id' => 'inicio',
        'label' => 'Inicio',
        'color' => '#2F7CEF',
        'section_title' => 'Inicio',
    ],
    'account_section' => [
        'id' => 'configuracion',
        'label' => 'Configuracion',
        'color' => '#202124',
        'section_title' => 'Configuracion',
    ],
    'menus' => [
        'regular' => $regularMenu,
        'admin' => array_merge($regularMenu, $adminMenu),
    ],
];
