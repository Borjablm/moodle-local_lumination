<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish language strings for local_lumination.
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_create_course'] = 'Crear curso';
$string['action_generate_lesson'] = 'Generar lección';
$string['action_generate_outline'] = 'Generar esquema';
$string['action_upload_document'] = 'Subir documento';
$string['addlesson'] = 'Añadir lección';
$string['addmodule'] = 'Añadir módulo';
$string['category'] = 'Categoría del curso';
$string['coursecreated'] = '¡Curso creado correctamente!';
$string['coursecreated_desc'] = 'Su nuevo curso se ha creado con {$a->sections} secciones y {$a->activities} actividades.';
$string['coursegenerator'] = 'Generador de cursos con IA';
$string['coursegenerator_desc'] = 'Suba documentos y genere la estructura completa de un curso de Moodle con Lumination AI.';
$string['coursegenerator_nav'] = 'Generador de cursos con IA';
$string['createcourse'] = 'Crear curso de Moodle';
$string['creatingcourse'] = 'Creando su curso de Moodle...';
$string['errorapifailed'] = 'La solicitud a la API de Lumination ha fallado: {$a}';
$string['errornoapi'] = 'La API de Lumination no está configurada. Configure la URL base de la API y la clave API en los ajustes del plugin.';
$string['errornocontent'] = 'La API no devolvió ningún contenido.';
$string['generateoutline'] = 'Generar esquema';
$string['generatingcourse'] = 'Generando su curso...';
$string['generatingcourse_desc'] = 'La IA está redactando el contenido de cada lección. Esto puede tardar unos minutos -- no cierre esta página.';
$string['generatingoutline'] = 'Generando el esquema del curso a partir de sus documentos...';
$string['instructions'] = 'Instrucciones';
$string['instructions_help'] = 'Indicaciones opcionales para la generación del curso (por ejemplo, nivel del público, tono o alcance).';
$string['language'] = 'Idioma';
$string['lessonname'] = 'Lección';
$string['lumination:generatecourse'] = 'Generar cursos con Lumination AI';
$string['lumination:manage'] = 'Gestionar los ajustes de Lumination AI';
$string['lumination:viewusage'] = 'Ver las estadísticas de uso de Lumination AI';
$string['modulename'] = 'Módulo';
$string['outlinereview'] = 'Revisar el esquema del curso';
$string['outlinereview_desc'] = 'Revise y edite el esquema generado antes de crear el curso.';
$string['pluginname'] = 'Generador de cursos de Lumination AI';
$string['privacy:metadata:api'] = 'El contenido de los documentos que suben los usuarios se envía a la API de Lumination para la extracción de texto y la generación de cursos con IA.';
$string['privacy:metadata:api:document_content'] = 'El contenido de los documentos subidos por el usuario, enviado para la extracción de texto y la generación del esquema del curso.';
$string['privacy:metadata:documents'] = 'Registros de los documentos subidos a la API de Lumination para la generación de cursos.';
$string['privacy:metadata:documents:document_uuid'] = 'El identificador único asignado al documento por la API de Lumination.';
$string['privacy:metadata:documents:filename'] = 'El nombre original del archivo subido.';
$string['privacy:metadata:documents:timecreated'] = 'La fecha y hora en que se subió el documento.';
$string['privacy:metadata:documents:userid'] = 'El ID del usuario que subió el documento.';
$string['privacy:metadata:usage'] = 'Registros de las llamadas realizadas a la API de Lumination, con el consumo de tokens y créditos.';
$string['privacy:metadata:usage:action'] = 'El tipo de acción realizada en la API (por ejemplo, generate_outline o generate_lesson).';
$string['privacy:metadata:usage:credits'] = 'El número de créditos cobrados por la llamada a la API.';
$string['privacy:metadata:usage:timecreated'] = 'La fecha y hora en que se realizó la llamada a la API.';
$string['privacy:metadata:usage:tokens_in'] = 'El número de tokens de entrada consumidos por la llamada a la API.';
$string['privacy:metadata:usage:tokens_out'] = 'El número de tokens de salida consumidos por la llamada a la API.';
$string['privacy:metadata:usage:userid'] = 'El ID del usuario que originó la llamada a la API.';
$string['removelesson'] = 'Eliminar lección';
$string['removemodule'] = 'Eliminar módulo';
$string['setting_apikey'] = 'Clave API';
$string['setting_apikey_desc'] = 'Requiere una clave API de Lumination AI. Cada lección generada cuesta unos 0,007 USD (aproximadamente 0,20 USD por curso) y las cuentas nuevas reciben 20 USD de crédito gratuito (alrededor de 100 cursos). Regístrese en https://ai-tutor.ai y cree una clave en https://ai-tutor.ai/dashboard/api.';
$string['setting_baseurl'] = 'URL base de la API';
$string['setting_baseurl_desc'] = 'La URL base de la API de AI Tutor (incluye el prefijo /api/v1). Por defecto apunta a producción; cámbiela solo si usa otro entorno.';
$string['uploadfiles'] = 'Documento de origen';
$string['uploadfiles_help'] = 'Suba un archivo PDF, Word o de texto con el material del curso. El esquema se genera a partir de este documento.';
$string['uploadtitle'] = 'Título del curso';
$string['uploadtitle_help'] = 'Un título para el curso que se va a generar.';
$string['usage'] = 'Uso de la API';
$string['usage_action'] = 'Acción';
$string['usage_by_action'] = 'Uso por acción';
$string['usage_by_user'] = 'Usuarios principales';
$string['usage_credits'] = 'Créditos';
$string['usage_daily'] = 'Desglose diario';
$string['usage_date'] = 'Fecha';
$string['usage_days'] = '{$a} días';
$string['usage_desc'] = 'Consulte las estadísticas de uso de la API de Lumination AI, incluidos los tokens consumidos y los créditos cobrados.';
$string['usage_nav'] = 'Panel de uso de la API';
$string['usage_nodata'] = 'No hay datos de uso para este periodo.';
$string['usage_period'] = 'Periodo';
$string['usage_requests'] = 'Solicitudes';
$string['usage_tokens_in'] = 'Tokens de entrada';
$string['usage_tokens_out'] = 'Tokens de salida';
$string['usage_total'] = 'Total';
$string['usage_user'] = 'Usuario';
$string['viewcourse'] = 'Ver curso';
