# QxLog - Control Quirúrgico Automatizado

![QxLog Banner](https://img.shields.io/badge/QxLog-v1.0-teal?style=for-the-badge&logo=medrt)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css)](https://tailwindcss.com)
[![Livewire](https://img.shields.io/badge/Livewire-3-4e56a6?style=for-the-badge&logo=livewire)](https://livewire.laravel.com)

**QxLog** es una mini-aplicación diseñada para modernizar y automatizar el registro de procedimientos quirúrgicos en entornos hospitalarios. Reemplaza los registros manuales en papel con una solución digital eficiente, precisa y fácil de usar.

---

## 🚀 Características Principales

### 📋 Gestión de Procedimientos
- **Registro Simplificado:** Instrumentistas y personal médico pueden registrar cirugías con ingreso de datos mínimo (Fecha, Hora, Paciente, Tipo).
- **Cálculos Automáticos:** El sistema determina automáticamente el monto a pagar basándose en reglas configurables (duración, hora del día, videocirugía).
- **Trazabilidad:** Ningún registro se elimina; solo se anulan con justificación, garantizando una auditoría completa.

### 💰 Gestión de Pagos
- **Liquidación por Lotes:** Los administradores pueden ver procedimientos pendientes y liquidarlos en bloque.
- **Vouchers Digitales:** Generación automática de comprobantes de pago listos para imprimir y firmar.
- **Historial de Pagos:** Consulta de liquidaciones anteriores por fecha o instrumentista.

### 🎨 Experiencia de Usuario (UI/UX)
- **Diseño Moderno:** Interfaz limpia y profesional construida con Tailwind CSS.
- **Modo Oscuro:** Soporte nativo para modo oscuro, ideal para entornos de baja luminosidad.
- **Dashboard Intuitivo:** Acceso rápido a las funciones más críticas según el rol del usuario.

### 🌍 Localización
- **Idioma:** Totalmente adaptado al español (Guatemala).
- **Terminología Hospitalaria:** Uso de términos familiares para el personal médico.

---

## 🛠️ Stack Tecnológico

Este proyecto está construido sobre un stack robusto y moderno:

-   **Backend:** [Laravel 12](https://laravel.com)
-   **Frontend:** [Blade](https://laravel.com/docs/blade) + [Tailwind CSS](https://tailwindcss.com)
-   **Intercalado:** [Livewire](https://livewire.laravel.com) & [Volt](https://livewire.laravel.com/docs/volt)
-   **Base de Datos:** SQLite (Configurable a MySQL/PostgreSQL)
-   **Autenticación:** Laravel Breeze

---

## ⚙️ Instalación y Configuración

Sigue estos pasos para levantar el proyecto en tu entorno local:

### Requisitos Previos
-   PHP 8.2 o superior
-   Composer
-   Node.js & NPM

### Pasos

1.  **Clonar el Repositorio**
    ```bash
    git clone https://github.com/tu-usuario/QxLog.git
    cd QxLog
    ```

2.  **Instalar Dependencias de PHP**
    ```bash
    composer install
    ```

3.  **Instalar Dependencias de Frontend**
    ```bash
    npm install
    ```

4.  **Configurar Entorno**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    *Asegúrate de configurar tu base de datos en el archivo `.env` (por defecto usa SQLite).*

5.  **Ejecutar Migraciones**
    ```bash
    php artisan migrate
    ```

6.  **Compilar Assets e Iniciar Servidor**
    ```bash
    # En una terminal para frontend
    npm run dev

    # En otra terminal para el servidor Laravel
    php artisan serve
    ```

7.  **Acceder**
    Abre tu navegador en `http://localhost:8000`.

---

## 🛡️ Roles y Permisos

-   **Super Admin:** Acceso total al sistema y gestión de admins.
-   **Admin:** Gestión de pagos, liquidaciones y configuraciones.
-   **Instrumentista/Doctor:** Registro de procedimientos y visualización de historial personal.

---

## 📄 Licencia

Este proyecto es software de código abierto licenciado bajo la [MIT license](https://opensource.org/licenses/MIT).

---

<p align="center">
  Hecho con ❤️ para la optimización hospitalaria.
</p>
