<?php
/**
 * Fonctions utilitaires, réparties par sujet dans inc/helpers/.
 */

defined( 'ABSPATH' ) || exit;

const VSG_TPL_PAROISSE    = 'templates/paroisse.php';
const VSG_TPL_CHAPELLE    = 'templates/chapelle.php';
const VSG_COMMON_CAT      = 'les-deux-paroisses';
const VSG_TPL_COMMUNAUTE  = 'templates/communaute.php';
const VSG_TPL_COMMUNAUTES = 'templates/communautes.php';
const VSG_TPL_HORAIRES    = 'templates/horaires.php';

require __DIR__ . '/helpers/dates.php';
require __DIR__ . '/helpers/lieux.php';
require __DIR__ . '/helpers/horaires.php';
require __DIR__ . '/helpers/agenda.php';
require __DIR__ . '/helpers/groupes.php';
require __DIR__ . '/helpers/rendu.php';
require __DIR__ . '/helpers/fil-ariane.php';
require __DIR__ . '/helpers/pages.php';
