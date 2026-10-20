<?php
/**
 * Import et export de l'agenda (Agenda › Importer / exporter).
 *
 * Fichier attendu : .xlsx ou .csv, une ligne par rendez-vous, colonnes (dans cet ordre, la 1re ligne = en-têtes) :
 * Date | Heure | Titre | Paroisse ou chapelle | Lieu précis | Type | Interne | Description
 *
 * Rapprochement sur date + titre : un rendez-vous déjà présent est mis à jour, jamais dupliqué. Rien n'est supprimé.
 */

defined( 'ABSPATH' ) || exit;

define( 'VSG_IMPORT_MAX', 2 * 1024 * 1024 );

require __DIR__ . '/import/lecture.php';
require __DIR__ . '/import/analyse.php';
require __DIR__ . '/import/enregistrement.php';
require __DIR__ . '/import/export.php';
require __DIR__ . '/import/page.php';
