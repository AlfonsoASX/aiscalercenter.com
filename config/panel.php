<?php
declare(strict_types=1);

$regularMenu = [
    [
        'id' => 'validar-mercado',
        'label' => 'Validar Mercado',
        'hover_label' => 'Idear',
        'icon_path' => 'img/ico/1.svg',
        'section_title' => 'Validar Mercado',
    ],
    [
        'id' => 'crear-oferta',
        'label' => 'Crear Oferta',
        'hover_label' => 'Diseñar',
        'icon_path' => 'img/ico/2.svg',
        'section_title' => 'Crear Oferta',
    ],
    [
        'id' => 'acelerar-ventas',
        'label' => 'Acelerar Ventas',
        'hover_label' => 'Ejecutar',
        'icon_path' => 'img/ico/3.svg',
        'section_title' => 'Acelerar Ventas',
    ],
    [
        'id' => 'academia-ia',
        'label' => 'Academia IA',
        'hover_label' => 'Aprender',
        'icon_path' => 'img/ico/4.svg',
        'section_title' => 'Academia IA',
    ],
];

$adminMenu = [
    [
        'id' => 'paginas-del-sitio',
        'label' => 'Paginas del sitio',
        'hover_label' => 'Paginas del sitio',
        'icon_path' => 'img/ico/6.svg',
        'section_title' => 'Paginas del sitio',
    ],
    [
        'id' => 'entradas-del-blog',
        'label' => 'Entradas del blog',
        'hover_label' => 'Entradas del blog',
        'icon_path' => 'img/ico/7.svg',
        'section_title' => 'Entradas del blog',
    ],
    [
        'id' => 'cursos',
        'label' => 'Cursos',
        'hover_label' => 'Cursos',
        'icon_path' => 'img/ico/8.svg',
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
        'id' => 'dashboard',
        'label' => 'Dashboard',
        'section_title' => 'Dashboard',
    ],
    'account_section' => [
        'id' => 'configuracion',
        'label' => 'Configuracion',
        'section_title' => 'Configuracion',
    ],
    'menus' => [
        'regular' => $regularMenu,
        'admin' => array_merge($regularMenu, $adminMenu),
    ],
];
